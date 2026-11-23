<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/crud.php';
login_required();

$sectionOptions = 'SELECT sec.id, CONCAT(ay.name, " / ", s.name, " / ", sec.name) AS label FROM sections sec JOIN semesters s ON s.id=sec.semester_id JOIN academic_years ay ON ay.id=s.academic_year_id ORDER BY ay.start_date DESC, s.semester_number, sec.name';
$subjectOptions = 'SELECT id, CONCAT(code, " - ", name) AS label FROM subjects ORDER BY code';
$roomTypeOptions = "SELECT 'CLASSROOM' AS id, 'Classroom' AS label UNION ALL SELECT 'LABORATORY' AS id, 'Laboratory' AS label UNION ALL SELECT 'ANY' AS id, 'Any suitable room' AS label";
$fields = [
    ['name' => 'section_id', 'label' => 'Section', 'type' => 'select', 'required' => true, 'options' => $sectionOptions],
    ['name' => 'subject_id', 'label' => 'Subject', 'type' => 'select', 'required' => true, 'options' => $subjectOptions],
    ['name' => 'weekly_lectures', 'label' => 'Sessions per week', 'type' => 'number', 'required' => true, 'min' => 1, 'max' => 7],
    ['name' => 'periods_per_session', 'label' => 'Periods per session', 'type' => 'number', 'required' => true, 'min' => 1, 'max' => 4],
    ['name' => 'room_type', 'label' => 'Required room', 'type' => 'select', 'required' => true, 'options' => $roomTypeOptions],
];
$config = [
    'title' => 'Weekly Subject Requirements', 'singular' => 'Requirement', 'table' => 'subject_requirements', 'fields' => $fields,
    'validate' => function (PDO $db, array $data, ?array $current) use ($fields): array {
        $errors = validate_fields(['fields' => $fields], $data);
        if (!in_array($data['room_type'], ['CLASSROOM', 'LABORATORY', 'ANY'], true)) $errors[] = 'Select a valid room requirement.';
        $statement = $db->prepare('SELECT s.semester_id, s.is_practical FROM subjects s WHERE s.id=?');
        $statement->execute([$data['subject_id']]);
        $subject = $statement->fetch();
        $statement = $db->prepare('SELECT semester_id FROM sections WHERE id=?');
        $statement->execute([$data['section_id']]);
        $section = $statement->fetch();
        if ($subject && $section && (int)$subject['semester_id'] !== (int)$section['semester_id']) $errors[] = 'The subject does not belong to the selected section semester.';
        if ($subject && (int)$subject['is_practical'] === 1 && (int)$data['periods_per_session'] < 2) $errors[] = 'Practical subjects require at least two consecutive periods.';
        if ($subject && (int)$subject['is_practical'] === 1 && $data['room_type'] === 'CLASSROOM') $errors[] = 'Practical subjects must require a laboratory.';
        $statement = $db->prepare('SELECT id FROM subject_requirements WHERE section_id=? AND subject_id=? AND id<>?');
        $statement->execute([$data['section_id'], $data['subject_id'], (int)($current['id'] ?? 0)]);
        if ($statement->fetch()) $errors[] = 'This section already has a requirement for this subject.';
        return $errors;
    },
    'delete_blockers' => ['timetable_entries' => ['column' => 'requirement_id', 'label' => 'timetable entries']],
    'filters' => [['name' => 'section_id', 'label' => 'sections', 'column' => 'sr.section_id', 'options' => $sectionOptions]],
    'list_sql' => 'SELECT sr.*, sec.name AS section, sub.code, sub.name AS subject FROM subject_requirements sr JOIN sections sec ON sec.id=sr.section_id JOIN subjects sub ON sub.id=sr.subject_id',
    'count_sql' => 'SELECT COUNT(*) FROM subject_requirements sr', 'search_columns' => ['sec.name', 'sub.name', 'sub.code'], 'order' => 'sec.name, sub.code',
    'columns' => [
        ['label' => 'Section', 'value' => fn($row) => e($row['section'])],
        ['label' => 'Subject', 'value' => fn($row) => e($row['code'] . ' - ' . $row['subject'])],
        ['label' => 'Weekly sessions', 'value' => fn($row) => (int)$row['weekly_lectures']],
        ['label' => 'Periods each', 'value' => fn($row) => (int)$row['periods_per_session']],
        ['label' => 'Room', 'value' => fn($row) => e(ucfirst(strtolower($row['room_type'])))],
    ],
];
run_crud($config);
