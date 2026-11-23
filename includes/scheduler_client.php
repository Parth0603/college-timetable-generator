<?php
declare(strict_types=1);

/**
 * Build the exact JSON scope required by the Python scheduler.
 * Python never reads or writes MySQL.
 */
function build_scheduler_input(PDO $db, int $academicYearId, int $departmentId, int $semesterId): array
{
    $semester = fetch_one($db, 'SELECT s.*, ay.id AS year_id, d.id AS department_id FROM semesters s JOIN academic_years ay ON ay.id=s.academic_year_id JOIN departments d ON d.id=s.department_id WHERE s.id=?', [$semesterId]);
    if (!$semester || (int)$semester['academic_year_id'] !== $academicYearId || (int)$semester['department_id'] !== $departmentId) {
        throw new RuntimeException('Selected semester does not belong to the chosen academic year and department.');
    }

    $sections = fetch_all($db, 'SELECT id, name, student_count FROM sections WHERE semester_id=? ORDER BY name', [$semesterId]);
    if (!$sections) throw new RuntimeException('No student sections are configured for this semester.');

    $subjects = fetch_all($db, 'SELECT id, department_id, semester_id, code, name, is_practical, periods_per_session FROM subjects WHERE semester_id=? ORDER BY code', [$semesterId]);
    if (!$subjects) throw new RuntimeException('No subjects are configured for this semester.');

    $requirements = fetch_all($db, 'SELECT sr.id, sr.section_id, sr.subject_id, sr.weekly_lectures, sr.periods_per_session, sr.room_type FROM subject_requirements sr JOIN sections sec ON sec.id=sr.section_id JOIN subjects sub ON sub.id=sr.subject_id WHERE sec.semester_id=? AND sub.semester_id=? ORDER BY sec.name, sub.code', [$semesterId, $semesterId]);
    if (!$requirements) throw new RuntimeException('No weekly subject requirements are configured for this semester.');

    $teacherRows = fetch_all($db, 'SELECT DISTINCT t.id, t.name, t.short_code, t.max_weekly_load, t.max_daily_load FROM teachers t JOIN teacher_subjects ts ON ts.teacher_id=t.id JOIN subjects sub ON sub.id=ts.subject_id WHERE sub.semester_id=? ORDER BY t.name', [$semesterId]);
    if (!$teacherRows) throw new RuntimeException('No teachers are assigned to subjects in this semester.');

    $teachers = [];
    foreach ($teacherRows as $row) {
        $statement = $db->prepare('SELECT subject_id FROM teacher_subjects WHERE teacher_id=?');
        $statement->execute([(int)$row['id']]);
        $row['subject_ids'] = array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
        $availabilityStatement = $db->prepare('SELECT working_day_id, time_slot_id, is_available FROM teacher_availability WHERE teacher_id=? AND working_day_id IN (SELECT id FROM working_days WHERE academic_year_id=?)');
        $availabilityStatement->execute([(int)$row['id'], $academicYearId]);
        $available = [];
        foreach ($availabilityStatement->fetchAll() as $availability) {
            if ((int)$availability['is_available'] === 1) {
                $available[(int)$availability['working_day_id'] . '-' . (int)$availability['time_slot_id']] = true;
            }
        }
        $row['available_slots'] = [];
        foreach ($available as $key => $value) {
            [$dayId, $slotId] = array_map('intval', explode('-', $key));
            $row['available_slots'][$dayId . ',' . $slotId] = true;
        }
        $teachers[] = $row;
    }

    $rooms = fetch_all($db, 'SELECT id, name, capacity, room_type, is_active FROM classrooms WHERE is_active=1 ORDER BY room_type, capacity DESC, name');
    $days = fetch_all($db, 'SELECT id, day_index, day_name FROM working_days WHERE academic_year_id=? ORDER BY day_index', [$academicYearId]);
    $slots = fetch_all($db, 'SELECT ts.id, wd.id AS day_id, ts.slot_order, ts.start_time, ts.end_time, ts.is_break FROM time_slots ts JOIN working_days wd ON wd.academic_year_id=ts.academic_year_id WHERE ts.academic_year_id=? ORDER BY wd.day_index, ts.slot_order', [$academicYearId]);
    if (!$days || !$slots) throw new RuntimeException('Working days and time slots must be configured first.');

    return [
        'academic_year_id' => $academicYearId,
        'department_id' => $departmentId,
        'semester_id' => $semesterId,
        'days' => $days,
        'slots' => $slots,
        'sections' => $sections,
        'subjects' => $subjects,
        'teachers' => $teachers,
        'rooms' => $rooms,
        'requirements' => $requirements,
    ];
}

/**
 * Execute Python safely. No user input is part of the command line.
 */
function run_python_scheduler(array $input): array
{
    $command = [PYTHON_COMMAND, PYTHON_SCRIPT];
    $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $process = proc_open($command, $descriptors, $pipes, dirname(PYTHON_SCRIPT), ['PYTHONIOENCODING' => 'utf-8'], ['bypass_shell' => true]);
    if (!is_resource($process)) {
        throw new RuntimeException('Could not start the Python scheduler.');
    }

    $json = json_encode($input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) throw new RuntimeException('Scheduler input encoding failed.');

    stream_set_blocking($pipes[0], false);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $inputLeft = $json;
    $stdout = '';
    $stderr = '';
    $started = time();

    while (true) {
        if (time() - $started > 45) {
            proc_terminate($process);
            throw new RuntimeException('Python scheduler timed out.');
        }

        $read = [$pipes[1], $pipes[2]];
        $write = [];
        if ($inputLeft !== '') $write[] = $pipes[0];
        $except = null;
        stream_select($read, $write, $except, 1);

        if ($write && $inputLeft !== '') {
            $written = fwrite($pipes[0], $inputLeft);
            if ($written !== false) $inputLeft = substr($inputLeft, (int)$written);
            if ($inputLeft === '') fclose($pipes[0]);
        }
        foreach ($read as $pipe) {
            $chunk = fread($pipe, 8192);
            if ($chunk !== false && $chunk !== '') {
                if ($pipe === $pipes[1]) $stdout .= $chunk; else $stderr .= $chunk;
            }
        }
        $status = proc_get_status($process);
        if (!$status['running'] && feof($pipes[1]) && feof($pipes[2])) break;
        if (feof($pipes[1]) && feof($pipes[2])) break;
    }

    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    if ($exitCode !== 0) {
        throw new RuntimeException('Python scheduler failed: ' . trim($stderr));
    }

    $decoded = json_decode($stdout, true);
    if (!is_array($decoded)) throw new RuntimeException('Python scheduler returned invalid JSON.');
    return $decoded;
}
