<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/crud.php';
login_required();

$config = [
    'title' => 'Academic Years', 'singular' => 'Academic year', 'table' => 'academic_years',
    'fields' => [
        ['name' => 'name', 'label' => 'Year name', 'type' => 'text', 'required' => true, 'max' => 60],
        ['name' => 'start_date', 'label' => 'Start date', 'type' => 'date', 'required' => true],
        ['name' => 'end_date', 'label' => 'End date', 'type' => 'date', 'required' => true],
        ['name' => 'is_active', 'label' => 'Active', 'type' => 'checkbox'],
    ],
    'validate' => function (PDO $db, array $data, ?array $current): array {
        $errors = validate_fields(['fields' => [
            ['name' => 'name', 'label' => 'Year name', 'type' => 'text', 'required' => true],
            ['name' => 'start_date', 'label' => 'Start date', 'type' => 'date', 'required' => true],
            ['name' => 'end_date', 'label' => 'End date', 'type' => 'date', 'required' => true],
        ]], $data);
        if ($data['start_date'] >= $data['end_date']) $errors[] = 'End date must be after start date.';
        $statement = $db->prepare('SELECT id FROM academic_years WHERE name = ? AND id <> ?');
        $statement->execute([$data['name'], (int)($current['id'] ?? 0)]);
        if ($statement->fetch()) $errors[] = 'This academic year name already exists.';
        return $errors;
    },
    'delete_blockers' => ['semesters' => ['column' => 'academic_year_id', 'label' => 'semesters'], 'working_days' => ['column' => 'academic_year_id', 'label' => 'working days'], 'time_slots' => ['column' => 'academic_year_id', 'label' => 'time slots'], 'timetables' => ['column' => 'academic_year_id', 'label' => 'timetables']],
    'list_sql' => 'SELECT * FROM academic_years', 'count_sql' => 'SELECT COUNT(*) FROM academic_years',
    'search_columns' => ['name'], 'order' => 'start_date DESC',
    'columns' => [
        ['label' => 'Name', 'value' => fn($row) => e($row['name'])],
        ['label' => 'Duration', 'value' => fn($row) => e(date('d M Y', strtotime($row['start_date']))) . ' &ndash; ' . e(date('d M Y', strtotime($row['end_date'])))],
        ['label' => 'Status', 'value' => fn($row) => $row['is_active'] ? '<span class="badge bg-success-subtle text-success">Active</span>' : '<span class="badge bg-secondary-subtle text-secondary">Inactive</span>'],
    ],
];
run_crud($config);
