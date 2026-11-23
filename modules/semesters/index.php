<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/crud.php';
login_required();

$yearOptions = 'SELECT id, name AS label FROM academic_years ORDER BY start_date DESC';
$departmentOptions = 'SELECT id, name AS label FROM departments ORDER BY name';
$config = [
    'title' => 'Semesters', 'singular' => 'Semester', 'table' => 'semesters',
    'fields' => [
        ['name' => 'academic_year_id', 'label' => 'Academic year', 'type' => 'select', 'required' => true, 'options' => $yearOptions],
        ['name' => 'department_id', 'label' => 'Department', 'type' => 'select', 'required' => true, 'options' => $departmentOptions],
        ['name' => 'semester_number', 'label' => 'Semester number', 'type' => 'number', 'required' => true, 'min' => 1, 'max' => 12],
        ['name' => 'name', 'label' => 'Semester name', 'type' => 'text', 'required' => true, 'max' => 100],
    ],
    'validate' => function (PDO $db, array $data, ?array $current): array {
        $errors = validate_fields(['fields' => [
            ['name' => 'semester_number', 'label' => 'Semester number', 'type' => 'number', 'required' => true, 'min' => 1, 'max' => 12],
            ['name' => 'name', 'label' => 'Semester name', 'type' => 'text', 'required' => true],
        ]], $data);
        $statement = $db->prepare('SELECT id FROM semesters WHERE academic_year_id=? AND department_id=? AND semester_number=? AND id<>?');
        $statement->execute([$data['academic_year_id'], $data['department_id'], $data['semester_number'], (int)($current['id'] ?? 0)]);
        if ($statement->fetch()) $errors[] = 'This semester already exists in the selected academic year and department.';
        return $errors;
    },
    'delete_blockers' => ['sections' => ['column' => 'semester_id', 'label' => 'sections'], 'subjects' => ['column' => 'semester_id', 'label' => 'subjects'], 'timetables' => ['column' => 'semester_id', 'label' => 'timetables']],
    'filters' => [
        ['name' => 'academic_year_id', 'label' => 'years', 'column' => 's.academic_year_id', 'options' => $yearOptions],
        ['name' => 'department_id', 'label' => 'departments', 'column' => 's.department_id', 'options' => $departmentOptions],
    ],
    'list_sql' => 'SELECT s.*, ay.name AS academic_year, d.name AS department FROM semesters s JOIN academic_years ay ON ay.id=s.academic_year_id JOIN departments d ON d.id=s.department_id',
    'count_sql' => 'SELECT COUNT(*) FROM semesters s', 'search_columns' => ['s.name'], 'order' => 'ay.start_date DESC, d.name, s.semester_number',
    'columns' => [
        ['label' => 'Academic year', 'value' => fn($row) => e($row['academic_year'])],
        ['label' => 'Department', 'value' => fn($row) => e($row['department'])],
        ['label' => 'Semester', 'value' => fn($row) => (int)$row['semester_number']],
        ['label' => 'Name', 'value' => fn($row) => e($row['name'])],
    ],
];
run_crud($config);
