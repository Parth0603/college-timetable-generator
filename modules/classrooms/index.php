<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/crud.php';
login_required();

$departmentOptions = 'SELECT id, name AS label FROM departments ORDER BY name';
$fields = [
    ['name' => 'department_id', 'label' => 'Department', 'type' => 'select', 'required' => false, 'options' => $departmentOptions],
    ['name' => 'name', 'label' => 'Room name', 'type' => 'text', 'required' => true, 'max' => 80],
    ['name' => 'capacity', 'label' => 'Capacity', 'type' => 'number', 'required' => true, 'min' => 1, 'max' => 500],
    ['name' => 'room_type', 'label' => 'Room type', 'type' => 'select', 'required' => true, 'options' => ''],
    ['name' => 'is_active', 'label' => 'Active', 'type' => 'checkbox'],
];
// Select options with static values need a simple query-compatible source.
$fields[3]['options'] = "SELECT 'CLASSROOM' AS id, 'Classroom' AS label UNION ALL SELECT 'LABORATORY' AS id, 'Laboratory' AS label";
$config = [
    'title' => 'Classrooms & Laboratories', 'singular' => 'Room', 'table' => 'classrooms', 'fields' => $fields,
    'validate' => function (PDO $db, array $data, ?array $current) use ($fields): array {
        $errors = validate_fields(['fields' => $fields], $data);
        if (!in_array($data['room_type'], ['CLASSROOM', 'LABORATORY'], true)) $errors[] = 'Select a valid room type.';
        $statement = $db->prepare('SELECT id FROM classrooms WHERE name=? AND id<>?');
        $statement->execute([$data['name'], (int)($current['id'] ?? 0)]);
        if ($statement->fetch()) $errors[] = 'This room name already exists.';
        return $errors;
    },
    'delete_blockers' => ['timetable_entries' => ['column' => 'classroom_id', 'label' => 'timetable entries']],
    'filters' => [['name' => 'department_id', 'label' => 'departments', 'column' => 'c.department_id', 'options' => $departmentOptions]],
    'list_sql' => 'SELECT c.*, d.name AS department FROM classrooms c LEFT JOIN departments d ON d.id=c.department_id',
    'count_sql' => 'SELECT COUNT(*) FROM classrooms c', 'search_columns' => ['c.name'], 'order' => 'c.room_type, c.name',
    'columns' => [
        ['label' => 'Room', 'value' => fn($row) => e($row['name'])],
        ['label' => 'Department', 'value' => fn($row) => e($row['department'] ?? 'Shared')],
        ['label' => 'Capacity', 'value' => fn($row) => (int)$row['capacity']],
        ['label' => 'Type', 'value' => fn($row) => $row['room_type'] === 'LABORATORY' ? '<span class="badge bg-success-subtle text-success">Laboratory</span>' : '<span class="badge bg-primary-subtle text-primary">Classroom</span>'],
        ['label' => 'Status', 'value' => fn($row) => $row['is_active'] ? '<span class="badge bg-success-subtle text-success">Active</span>' : '<span class="badge bg-secondary-subtle text-secondary">Inactive</span>'],
    ],
];
run_crud($config);
