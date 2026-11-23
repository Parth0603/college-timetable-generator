<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/crud.php';
login_required();

$departmentOptions = 'SELECT id, name AS label FROM departments ORDER BY name';
$fields = [
    ['name' => 'department_id', 'label' => 'Department', 'type' => 'select', 'required' => true, 'options' => $departmentOptions],
    ['name' => 'name', 'label' => 'Teacher name', 'type' => 'text', 'required' => true, 'max' => 120],
    ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true, 'max' => 160],
    ['name' => 'phone', 'label' => 'Phone', 'type' => 'text', 'max' => 20],
    ['name' => 'short_code', 'label' => 'Short code', 'type' => 'text', 'required' => true, 'max' => 12],
    ['name' => 'max_weekly_load', 'label' => 'Maximum weekly periods', 'type' => 'number', 'required' => true, 'min' => 1, 'max' => 40],
    ['name' => 'max_daily_load', 'label' => 'Maximum daily periods', 'type' => 'number', 'required' => true, 'min' => 1, 'max' => 10],
];
$config = [
    'title' => 'Teachers', 'singular' => 'Teacher', 'table' => 'teachers', 'fields' => $fields,
    'validate' => function (PDO $db, array $data, ?array $current) use ($fields): array {
        $errors = validate_fields(['fields' => $fields], $data);
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
        if (!preg_match('/^[A-Z]{1,6}$/', $data['short_code'])) $errors[] = 'Short code must contain 1-6 uppercase letters.';
        if ($data['max_daily_load'] > $data['max_weekly_load']) $errors[] = 'Daily load cannot exceed weekly load.';
        foreach (['email', 'short_code'] as $column) {
            $statement = $db->prepare("SELECT id FROM teachers WHERE $column=? AND id<>?");
            $statement->execute([$data[$column], (int)($current['id'] ?? 0)]);
            if ($statement->fetch()) $errors[] = 'This teacher ' . $column . ' already exists.';
        }
        return $errors;
    },
    'delete_blockers' => ['teacher_subjects' => ['column' => 'teacher_id', 'label' => 'subject assignments'], 'teacher_availability' => ['column' => 'teacher_id', 'label' => 'availability'], 'timetable_entries' => ['column' => 'teacher_id', 'label' => 'timetable entries']],
    'filters' => [['name' => 'department_id', 'label' => 'departments', 'column' => 't.department_id', 'options' => $departmentOptions]],
    'list_sql' => 'SELECT t.*, d.name AS department FROM teachers t JOIN departments d ON d.id=t.department_id',
    'count_sql' => 'SELECT COUNT(*) FROM teachers t', 'search_columns' => ['t.name', 't.email', 't.short_code'], 'order' => 't.name',
    'columns' => [
        ['label' => 'Name', 'value' => fn($row) => e($row['name'])],
        ['label' => 'Department', 'value' => fn($row) => e($row['department'])],
        ['label' => 'Email', 'value' => fn($row) => e($row['email'])],
        ['label' => 'Code', 'value' => fn($row) => e($row['short_code'])],
        ['label' => 'Load', 'value' => fn($row) => (int)$row['max_daily_load'] . ' daily / ' . (int)$row['max_weekly_load'] . ' weekly'],
    ],
];
run_crud($config);
