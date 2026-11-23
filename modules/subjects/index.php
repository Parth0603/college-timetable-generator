<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/crud.php';
login_required();

$departmentOptions = 'SELECT id, name AS label FROM departments ORDER BY name';
$semesterOptions = 'SELECT s.id, CONCAT(ay.name, " - ", s.name) AS label FROM semesters s JOIN academic_years ay ON ay.id=s.academic_year_id ORDER BY ay.start_date DESC, s.semester_number';
$fields = [
    ['name' => 'department_id', 'label' => 'Department', 'type' => 'select', 'required' => true, 'options' => $departmentOptions],
    ['name' => 'semester_id', 'label' => 'Semester', 'type' => 'select', 'required' => true, 'options' => $semesterOptions],
    ['name' => 'code', 'label' => 'Subject code', 'type' => 'text', 'required' => true, 'max' => 20],
    ['name' => 'name', 'label' => 'Subject name', 'type' => 'text', 'required' => true, 'max' => 160],
    ['name' => 'is_practical', 'label' => 'Practical subject', 'type' => 'checkbox'],
    ['name' => 'default_weekly_lectures', 'label' => 'Default sessions per week', 'type' => 'number', 'required' => true, 'min' => 1, 'max' => 7],
    ['name' => 'periods_per_session', 'label' => 'Periods per session', 'type' => 'number', 'required' => true, 'min' => 1, 'max' => 4, 'help' => 'Practical subjects normally use 2 consecutive periods.'],
];
$config = [
    'title' => 'Subjects', 'singular' => 'Subject', 'table' => 'subjects', 'fields' => $fields,
    'validate' => function (PDO $db, array $data, ?array $current) use ($fields): array {
        $errors = validate_fields(['fields' => $fields], $data);
        if (!preg_match('/^[A-Z]{2,4}\d{3}$/', $data['code'])) $errors[] = 'Subject code should look like CS301.';
        if ($data['is_practical'] && $data['periods_per_session'] < 2) $errors[] = 'Practical subjects need at least two periods per session.';
        $statement = $db->prepare('SELECT id FROM subjects WHERE code=? AND id<>?');
        $statement->execute([$data['code'], (int)($current['id'] ?? 0)]);
        if ($statement->fetch()) $errors[] = 'This subject code already exists.';
        return $errors;
    },
    'delete_blockers' => ['teacher_subjects' => ['column' => 'subject_id', 'label' => 'teacher assignments'], 'subject_requirements' => ['column' => 'subject_id', 'label' => 'requirements'], 'timetable_entries' => ['column' => 'subject_id', 'label' => 'timetable entries']],
    'filters' => [['name' => 'semester_id', 'label' => 'semesters', 'column' => 'sub.semester_id', 'options' => $semesterOptions], ['name' => 'department_id', 'label' => 'departments', 'column' => 'sub.department_id', 'options' => $departmentOptions]],
    'list_sql' => 'SELECT sub.*, s.name AS semester FROM subjects sub JOIN semesters s ON s.id=sub.semester_id',
    'count_sql' => 'SELECT COUNT(*) FROM subjects sub', 'search_columns' => ['sub.code', 'sub.name'], 'order' => 'sub.code',
    'columns' => [
        ['label' => 'Code', 'value' => fn($row) => e($row['code'])],
        ['label' => 'Subject', 'value' => fn($row) => e($row['name'])],
        ['label' => 'Semester', 'value' => fn($row) => e($row['semester'])],
        ['label' => 'Type', 'value' => fn($row) => $row['is_practical'] ? '<span class="badge bg-success-subtle text-success">Practical</span>' : '<span class="badge bg-primary-subtle text-primary">Theory</span>'],
    ],
];
run_crud($config);
