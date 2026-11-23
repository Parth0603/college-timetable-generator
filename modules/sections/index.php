<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/crud.php';
login_required();

$semesterOptions = 'SELECT s.id, CONCAT(ay.name, \' - \', s.name) AS label FROM semesters s JOIN academic_years ay ON ay.id=s.academic_year_id ORDER BY ay.start_date DESC, s.semester_number';
$config = [
    'title' => 'Student Sections', 'singular' => 'Section', 'table' => 'sections',
    'fields' => [
        ['name' => 'semester_id', 'label' => 'Semester', 'type' => 'select', 'required' => true, 'options' => $semesterOptions],
        ['name' => 'name', 'label' => 'Section name', 'type' => 'text', 'required' => true, 'max' => 60],
        ['name' => 'student_count', 'label' => 'Student count', 'type' => 'number', 'required' => true, 'min' => 1, 'max' => 500],
    ],
    'validate' => function (PDO $db, array $data, ?array $current): array {
        $errors = validate_fields(['fields' => [
            ['name' => 'name', 'label' => 'Section name', 'type' => 'text', 'required' => true],
            ['name' => 'student_count', 'label' => 'Student count', 'type' => 'number', 'required' => true, 'min' => 1, 'max' => 500],
        ]], $data);
        $statement = $db->prepare('SELECT id FROM sections WHERE semester_id=? AND name=? AND id<>?');
        $statement->execute([$data['semester_id'], $data['name'], (int)($current['id'] ?? 0)]);
        if ($statement->fetch()) $errors[] = 'This section already exists in the selected semester.';
        return $errors;
    },
    'delete_blockers' => ['subject_requirements' => ['column' => 'section_id', 'label' => 'subject requirements'], 'timetable_entries' => ['column' => 'section_id', 'label' => 'timetable entries']],
    'filters' => [['name' => 'semester_id', 'label' => 'semesters', 'column' => 'sec.semester_id', 'options' => $semesterOptions]],
    'list_sql' => 'SELECT sec.*, s.name AS semester, ay.name AS academic_year, d.name AS department FROM sections sec JOIN semesters s ON s.id=sec.semester_id JOIN academic_years ay ON ay.id=s.academic_year_id JOIN departments d ON d.id=s.department_id',
    'count_sql' => 'SELECT COUNT(*) FROM sections sec', 'search_columns' => ['sec.name'], 'order' => 'ay.start_date DESC, d.name, s.semester_number, sec.name',
    'columns' => [
        ['label' => 'Semester', 'value' => fn($row) => e($row['semester'])],
        ['label' => 'Section', 'value' => fn($row) => e($row['name'])],
        ['label' => 'Students', 'value' => fn($row) => (int)$row['student_count']],
    ],
];
run_crud($config);
