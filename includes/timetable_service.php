<?php
declare(strict_types=1);

/**
 * Validate a whole generated timetable before saving it.
 * This prevents trusting any malformed process output.
 */
function validate_generated_timetable(PDO $db, array $scope, array $result): array
{
    if (empty($result['success'])) {
        throw new RuntimeException((string)($result['error'] ?? 'The scheduler failed without an error message.'));
    }
    $entries = $result['entries'] ?? [];
    if (!is_array($entries) || !$entries) throw new RuntimeException('The scheduler returned no timetable entries.');

    $requirements = [];
    foreach ($scope['requirements'] as $requirement) $requirements[(int)$requirement['id']] = $requirement;
    $teachers = [];
    foreach ($scope['teachers'] as $teacher) $teachers[(int)$teacher['id']] = $teacher;
    $rooms = [];
    foreach ($scope['rooms'] as $room) $rooms[(int)$room['id']] = $room;
    $sections = [];
    foreach ($scope['sections'] as $section) $sections[(int)$section['id']] = $section;
    $subjects = [];
    foreach ($scope['subjects'] as $subject) $subjects[(int)$subject['id']] = $subject;
    $days = [];
    foreach ($scope['days'] as $day) $days[(int)$day['id']] = $day;
    $slots = [];
    foreach ($scope['slots'] as $slot) $slots[(int)$slot['id']] = $slot;

    $entriesByRequirement = [];
    $teacherBusy = [];
    $roomBusy = [];
    $sectionBusy = [];
    $weeklyLoad = [];
    $dailyLoad = [];

    foreach ($entries as $entry) {
        validate_entry_shape($entry);
        $requirementId = (int)$entry['requirement_id'];
        if (!isset($requirements[$requirementId])) throw new RuntimeException('The scheduler returned a requirement outside the selected scope.');
        $requirement = $requirements[$requirementId];
        $sectionId = (int)$entry['section_id'];
        $subjectId = (int)$entry['subject_id'];
        $teacherId = (int)$entry['teacher_id'];
        $classroomId = (int)$entry['classroom_id'];
        $dayId = (int)$entry['day_id'];
        $slotIds = array_map('intval', (array)$entry['slots']);

        if ($sectionId !== (int)$requirement['section_id'] || $subjectId !== (int)$requirement['subject_id']) throw new RuntimeException('Entry section or subject does not match its requirement.');
        if (!isset($teachers[$teacherId]) || !isset($rooms[$classroomId]) || !isset($sections[$sectionId]) || !isset($subjects[$subjectId]) || !isset($days[$dayId])) throw new RuntimeException('Entry contains an unknown teacher, room, section, subject, or day.');
        if (!in_array($subjectId, $teachers[$teacherId]['subject_ids'], true)) throw new RuntimeException('HC-10 violation: teacher is not assigned to the subject.');
        $room = $rooms[$classroomId];
        $section = $sections[$sectionId];
        if ((int)$room['capacity'] < (int)$section['student_count']) throw new RuntimeException('HC-06 violation: room capacity is insufficient.');
        if ($requirement['room_type'] === 'LABORATORY' && $room['room_type'] !== 'LABORATORY') throw new RuntimeException('HC-07 violation: practical is not in a laboratory.');
        if ($requirement['room_type'] === 'CLASSROOM' && $room['room_type'] === 'LABORATORY') throw new RuntimeException('A theory session cannot be placed in a laboratory.');

        if (count($slotIds) !== (int)$requirement['periods_per_session']) throw new RuntimeException('HC-08 violation: practical period count is incorrect.');
        $orderedSlots = [];
        foreach ($slotIds as $slotId) {
            if (!isset($slots[$slotId])) throw new RuntimeException('Entry contains an unknown time slot.');
            if ((int)$slots[$slotId]['is_break'] === 1) throw new RuntimeException('HC-09 violation: lecture was placed in a break.');
            $orderedSlots[] = $slots[$slotId];
        }
        usort($orderedSlots, fn($first, $second) => (int)$first['slot_order'] <=> (int)$second['slot_order']);
        for ($index = 1; $index < count($orderedSlots); $index++) {
            if ((int)$orderedSlots[$index]['slot_order'] !== (int)$orderedSlots[$index - 1]['slot_order'] + 1) {
                throw new RuntimeException('HC-08 violation: practical periods are not consecutive.');
            }
        }

        foreach ($slotIds as $slotId) {
            $teacherKey = $teacherId . '-' . $dayId . '-' . $slotId;
            $roomKey = $classroomId . '-' . $dayId . '-' . $slotId;
            $sectionKey = $sectionId . '-' . $dayId . '-' . $slotId;
            if (isset($teacherBusy[$teacherKey])) throw new RuntimeException('HC-01 violation: teacher double booking returned by scheduler.');
            if (isset($roomBusy[$roomKey])) throw new RuntimeException('HC-02 violation: room double booking returned by scheduler.');
            if (isset($sectionBusy[$sectionKey])) throw new RuntimeException('HC-03 violation: section double booking returned by scheduler.');
            if (empty($teachers[$teacherId]['available_slots'][$dayId . ',' . $slotId])) throw new RuntimeException('HC-04 violation: teacher unavailable in returned slot.');
            $teacherBusy[$teacherKey] = true; $roomBusy[$roomKey] = true; $sectionBusy[$sectionKey] = true;
        }

        $weeklyLoad[$teacherId] = ($weeklyLoad[$teacherId] ?? 0) + count($slotIds);
        $dailyKey = $teacherId . '-' . $dayId;
        $dailyLoad[$dailyKey] = ($dailyLoad[$dailyKey] ?? 0) + count($slotIds);
        if ($weeklyLoad[$teacherId] > (int)$teachers[$teacherId]['max_weekly_load']) throw new RuntimeException('HC-11 violation: weekly teacher overload.');
        if ($dailyLoad[$dailyKey] > (int)$teachers[$teacherId]['max_daily_load']) throw new RuntimeException('HC-11 violation: daily teacher overload.');
        $entriesByRequirement[$requirementId][] = $entry;
    }

    foreach ($requirements as $requirementId => $requirement) {
        $count = count($entriesByRequirement[$requirementId] ?? []);
        if ($count !== (int)$requirement['weekly_lectures']) {
            throw new RuntimeException(sprintf('HC-05 violation: requirement #%d has %d of %d sessions.', $requirementId, $count, (int)$requirement['weekly_lectures']));
        }
    }

    return $entries;
}

function validate_entry_shape(array $entry): void
{
    foreach (['requirement_id','section_id','subject_id','teacher_id','classroom_id','periods_count','day_id'] as $key) {
        if (!isset($entry[$key]) || !is_numeric($entry[$key])) throw new RuntimeException('Malformed scheduler entry: missing ' . $key . '.');
    }
    if (!isset($entry['entry_type'])) throw new RuntimeException('Malformed scheduler entry: missing entry_type.');
    if (!isset($entry['slots']) || !is_array($entry['slots']) || !$entry['slots']) throw new RuntimeException('Malformed scheduler entry: missing time slots.');
    if (!in_array($entry['entry_type'], ['THEORY', 'PRACTICAL'], true)) throw new RuntimeException('Malformed scheduler entry: invalid entry type.');
}

function save_generated_timetable(PDO $db, array $scope, array $entries, int $qualityScore, string $notes = ''): int
{
    $db->beginTransaction();
    try {
        $statement = $db->prepare('SELECT COALESCE(MAX(version_number),0)+1 FROM timetables WHERE academic_year_id=? AND department_id=? AND semester_id=?');
        $statement->execute([(int)$scope['academic_year_id'], (int)$scope['department_id'], (int)$scope['semester_id']]);
        $version = (int)$statement->fetchColumn();

        $statement = $db->prepare('INSERT INTO timetables (academic_year_id, department_id, semester_id, version_number, status, algorithm, quality_score, notes) VALUES (?,?,?,?,?, ?, ?, ?)');
        $statement->execute([(int)$scope['academic_year_id'], (int)$scope['department_id'], (int)$scope['semester_id'], $version, 'DRAFT', 'Backtracking + MRV', $qualityScore, $notes]);
        $timetableId = (int)$db->lastInsertId();

        $insertEntry = $db->prepare('INSERT INTO timetable_entries (timetable_id, requirement_id, section_id, subject_id, teacher_id, classroom_id, entry_type, periods_count) VALUES (?,?,?,?,?,?,?,?)');
        $insertSlot = $db->prepare('INSERT INTO timetable_entry_slots (timetable_entry_id, timetable_id, requirement_id, section_id, teacher_id, classroom_id, working_day_id, time_slot_id, slot_sequence) VALUES (?,?,?,?,?,?,?,?,?)');

        foreach ($entries as $entry) {
            $insertEntry->execute([$timetableId, (int)$entry['requirement_id'], (int)$entry['section_id'], (int)$entry['subject_id'], (int)$entry['teacher_id'], (int)$entry['classroom_id'], $entry['entry_type'], (int)$entry['periods_count']]);
            $entryId = (int)$db->lastInsertId();
            foreach ($entry['slots'] as $sequence => $slotId) {
                $insertSlot->execute([$entryId, $timetableId, (int)$entry['requirement_id'], (int)$entry['section_id'], (int)$entry['teacher_id'], (int)$entry['classroom_id'], (int)$entry['day_id'], (int)$slotId, $sequence + 1]);
            }
        }
        $db->commit();
        return $timetableId;
    } catch (Throwable $exception) {
        $db->rollBack();
        throw $exception;
    }
}

function timetable_scope(PDO $db, int $timetableId): array
{
    $row = fetch_one($db, 'SELECT t.*, ay.name AS academic_year, d.name AS department, d.code AS department_code, s.name AS semester FROM timetables t JOIN academic_years ay ON ay.id=t.academic_year_id JOIN departments d ON d.id=t.department_id JOIN semesters s ON s.id=t.semester_id WHERE t.id=?', [$timetableId]);
    if (!$row) throw new RuntimeException('Timetable not found.');
    return $row;
}

function timetable_grid(PDO $db, int $timetableId, string $mode, int $resourceId): array
{
    $scope = timetable_scope($db, $timetableId);
    $days = fetch_all($db, 'SELECT * FROM working_days WHERE academic_year_id=? ORDER BY day_index', [(int)$scope['academic_year_id']]);
    $slots = fetch_all($db, 'SELECT * FROM time_slots WHERE academic_year_id=? ORDER BY slot_order', [(int)$scope['academic_year_id']]);

    $where = match ($mode) {
        'section' => 'tes.section_id=?',
        'teacher' => 'tes.teacher_id=?',
        'classroom' => 'tes.classroom_id=?',
        default => throw new RuntimeException('Invalid timetable view mode.'),
    };

    $sql = 'SELECT tes.*, sub.code, sub.name AS subject_name, sub.is_practical, t.name AS teacher_name, c.name AS room_name, sec.name AS section_name FROM timetable_entry_slots tes JOIN timetable_entries te ON te.id=tes.timetable_entry_id JOIN subjects sub ON sub.id=te.subject_id JOIN teachers t ON t.id=te.teacher_id JOIN classrooms c ON c.id=te.classroom_id JOIN sections sec ON sec.id=te.section_id WHERE tes.timetable_id=? AND ' . $where . ' ORDER BY wd.day_index, ts.slot_order';
    $sql = str_replace('wd.day_index', '(SELECT day_index FROM working_days WHERE id=tes.working_day_id)', $sql);
    $sql = str_replace('ts.slot_order', '(SELECT slot_order FROM time_slots WHERE id=tes.time_slot_id)', $sql);
    $statement = $db->prepare($sql);
    $statement->execute([$timetableId, $resourceId]);
    $cells = [];
    foreach ($statement->fetchAll() as $row) {
        $cells[(int)$row['working_day_id']][(int)$row['time_slot_id']][] = $row;
    }
    return [$scope, $days, $slots, $cells];
}
