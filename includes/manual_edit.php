<?php
declare(strict_types=1);

function validate_manual_allocation(PDO $db, int $timetableId, int $entryId, int $dayId, int $teacherId, int $classroomId, array $slotIds): array
{
    $errors = [];
    $entry = fetch_one($db, 'SELECT te.*, sr.weekly_lectures, sr.periods_per_session, sr.room_type, sec.semester_id, sec.student_count, sub.is_practical, sub.id AS subject_identifier FROM timetable_entries te JOIN subject_requirements sr ON sr.id=te.requirement_id JOIN sections sec ON sec.id=te.section_id JOIN subjects sub ON sub.id=te.subject_id WHERE te.id=? AND te.timetable_id=?', [$entryId, $timetableId]);
    if (!$entry) return ['The timetable entry does not exist.'];

    $timetable = timetable_scope($db, $timetableId);
    $day = fetch_one($db, 'SELECT * FROM working_days WHERE id=? AND academic_year_id=?', [$dayId, (int)$timetable['academic_year_id']]);
    if (!$day) $errors[] = 'Selected working day is invalid for this timetable.';

    $teacher = fetch_one($db, 'SELECT t.*, ts.id AS assignment_id FROM teachers t LEFT JOIN teacher_subjects ts ON ts.teacher_id=t.id AND ts.subject_id=? WHERE t.id=?', [(int)$entry['subject_id'], $teacherId]);
    if (!$teacher || $teacher['assignment_id'] === null) $errors[] = 'HC-10: selected teacher is not assigned to this subject.';
    $room = fetch_one($db, 'SELECT * FROM classrooms WHERE id=? AND is_active=1', [$classroomId]);
    if (!$room) $errors[] = 'Selected classroom is unavailable.';

    $slotRows = [];
    foreach ($slotIds as $slotId) {
        $slot = fetch_one($db, 'SELECT * FROM time_slots WHERE id=? AND academic_year_id=?', [(int)$slotId, (int)$timetable['academic_year_id']]);
        if (!$slot) {
            $errors[] = 'One or more selected time slots are invalid.';
            continue;
        }
        if ((int)$slot['is_break'] === 1) $errors[] = 'HC-09: lectures cannot be placed in a break period.';
        $slotRows[] = $slot;
    }

    usort($slotRows, fn($first, $second) => (int)$first['slot_order'] <=> (int)$second['slot_order']);
    for ($index = 1; $index < count($slotRows); $index++) {
        if ((int)$slotRows[$index]['slot_order'] !== (int)$slotRows[$index - 1]['slot_order'] + 1) {
            $errors[] = 'HC-08: selected periods must be consecutive.';
        }
    }
    if (count($slotRows) !== (int)$entry['periods_per_session']) $errors[] = 'This entry must occupy exactly ' . (int)$entry['periods_per_session'] . ' period(s).';
    if (!$room) return $errors;
    if ((int)$room['capacity'] < (int)$entry['student_count']) $errors[] = 'HC-06: selected room capacity is smaller than the section.';
    if ($entry['room_type'] === 'LABORATORY' && $room['room_type'] !== 'LABORATORY') $errors[] = 'HC-07: practicals require a laboratory.';
    if ($entry['room_type'] === 'CLASSROOM' && $room['room_type'] === 'LABORATORY') $errors[] = 'Theory lectures cannot use a laboratory.';

    $busyStatement = $db->prepare('SELECT tes.* FROM timetable_entry_slots tes WHERE tes.timetable_id=? AND tes.timetable_entry_id<>?');
    $busyStatement->execute([$timetableId, $entryId]);
    $busySlots = $busyStatement->fetchAll();
    $teacherWeekly = 0;
    $teacherDaily = 0;

    foreach ($slotIds as $slotId) {
        foreach ($busySlots as $busy) {
            if ((int)$busy['working_day_id'] !== $dayId || (int)$busy['time_slot_id'] !== (int)$slotId) continue;
            if ((int)$busy['teacher_id'] === $teacherId) $errors[] = 'HC-01: selected teacher is already booked at this time.';
            if ((int)$busy['classroom_id'] === $classroomId) $errors[] = 'HC-02: selected classroom is already occupied at this time.';
            if ((int)$busy['section_id'] === (int)$entry['section_id']) $errors[] = 'HC-03: selected section already has a lecture at this time.';
        }
        if ($teacher && !teacher_is_available($db, $teacherId, $dayId, (int)$slotId)) $errors[] = 'HC-04: selected teacher is unavailable at this time.';
    }

    if ($teacher) {
        foreach ($busySlots as $busy) {
            if ((int)$busy['teacher_id'] === $teacherId) {
                $teacherWeekly++;
                if ((int)$busy['working_day_id'] === $dayId) $teacherDaily++;
            }
        }
        if ($teacherWeekly + count($slotIds) > (int)$teacher['max_weekly_load']) $errors[] = 'HC-11: teacher exceeds weekly workload.';
        if ($teacherDaily + count($slotIds) > (int)$teacher['max_daily_load']) $errors[] = 'HC-11: teacher exceeds daily workload.';
    }

    return array_values(array_unique($errors));
}

function teacher_is_available(PDO $db, int $teacherId, int $dayId, int $slotId): bool
{
    $statement = $db->prepare('SELECT is_available FROM teacher_availability WHERE teacher_id=? AND working_day_id=? AND time_slot_id=?');
    $statement->execute([$teacherId, $dayId, $slotId]);
    $row = $statement->fetch();
    return $row && (int)$row['is_available'] === 1;
}

function save_manual_allocation(PDO $db, int $timetableId, int $entryId, int $dayId, int $teacherId, int $classroomId, array $slotIds): void
{
    $db->beginTransaction();
    try {
        $entry = fetch_one($db, 'SELECT * FROM timetable_entries WHERE id=? AND timetable_id=?', [$entryId, $timetableId]);
        if (!$entry) throw new RuntimeException('Timetable entry not found.');
        $statement = $db->prepare('UPDATE timetable_entries SET teacher_id=?, classroom_id=? WHERE id=?');
        $statement->execute([$teacherId, $classroomId, $entryId]);
        $statement = $db->prepare('DELETE FROM timetable_entry_slots WHERE timetable_entry_id=?');
        $statement->execute([$entryId]);
        $statement = $db->prepare('INSERT INTO timetable_entry_slots (timetable_entry_id, timetable_id, requirement_id, section_id, teacher_id, classroom_id, working_day_id, time_slot_id, slot_sequence) VALUES (?,?,?,?,?,?,?,?,?)');
        foreach ($slotIds as $sequence => $slotId) {
            $statement->execute([$entryId, $timetableId, (int)$entry['requirement_id'], (int)$entry['section_id'], $teacherId, $classroomId, $dayId, (int)$slotId, $sequence + 1]);
        }
        $db->commit();
    } catch (Throwable $exception) {
        $db->rollBack();
        throw $exception;
    }
}
