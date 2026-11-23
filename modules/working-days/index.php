<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/crud.php';
login_required();

$yearOptions = 'SELECT id, name AS label FROM academic_years ORDER BY start_date DESC';
$fields = [
    ['name' => 'academic_year_id', 'label' => 'Academic year', 'type' => 'select', 'required' => true, 'options' => $yearOptions],
    ['name' => 'day_index', 'label' => 'Day order', 'type' => 'number', 'required' => true, 'min' => 1, 'max' => 7],
    ['name' => 'day_name', 'label' => 'Day name', 'type' => 'text', 'required' => true, 'max' => 20],
];
$config = [
    'title' => 'Working Days', 'singular' => 'Working day', 'table' => 'working_days', 'fields' => $fields,
    'validate' => function (PDO $db, array $data, ?array $current) use ($fields): array {
        $errors = validate_fields(['fields' => $fields], $data);
        if (!in_array($data['day_name'], ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'], true)) $errors[] = 'Enter a valid weekday name.';
        $statement = $db->prepare('SELECT id FROM working_days WHERE academic_year_id=? AND day_index=? AND id<>?');
        $statement->execute([$data['academic_year_id'], $data['day_index'], (int)($current['id'] ?? 0)]);
        if ($statement->fetch()) $errors[] = 'This day order already exists in the selected academic year.';
        return $errors;
    },
    'delete_blockers' => ['teacher_availability' => ['column' => 'working_day_id', 'label' => 'teacher availability'], 'timetable_entry_slots' => ['column' => 'working_day_id', 'label' => 'timetable slots']],
    'filters' => [['name' => 'academic_year_id', 'label' => 'academic years', 'column' => 'wd.academic_year_id', 'options' => $yearOptions]],
    'list_sql' => 'SELECT wd.*, ay.name AS academic_year FROM working_days wd JOIN academic_years ay ON ay.id=wd.academic_year_id',
    'count_sql' => 'SELECT COUNT(*) FROM working_days wd', 'search_columns' => ['wd.day_name'], 'order' => 'wd.academic_year_id, wd.day_index',
    'columns' => [
        ['label' => 'Academic year', 'value' => fn($row) => e($row['academic_year'])],
        ['label' => 'Order', 'value' => fn($row) => (int)$row['day_index']],
        ['label' => 'Day', 'value' => fn($row) => e($row['day_name'])],
    ],
];
run_crud($config);
