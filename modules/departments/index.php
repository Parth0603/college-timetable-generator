<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/crud.php';
login_required();

$config = [
    'title' => 'Departments', 'singular' => 'Department', 'table' => 'departments',
    'fields' => [
        ['name' => 'code', 'label' => 'Department code', 'type' => 'text', 'required' => true, 'max' => 20],
        ['name' => 'name', 'label' => 'Department name', 'type' => 'text', 'required' => true, 'max' => 120],
    ],
    'validate' => function (PDO $db, array $data, ?array $current): array {
        $errors = [];
        if (!preg_match('/^[A-Z]{2,10}$/', $data['code'])) $errors[] = 'Code must contain 2-10 uppercase letters.';
        foreach (['code', 'name'] as $column) {
            $statement = $db->prepare("SELECT id FROM departments WHERE $column = ? AND id <> ?");
            $statement->execute([$data[$column], (int)($current['id'] ?? 0)]);
            if ($statement->fetch()) $errors[] = 'This department ' . $column . ' already exists.';
        }
        return $errors;
    },
    'delete_blockers' => ['semesters' => ['column' => 'department_id', 'label' => 'semesters'], 'teachers' => ['column' => 'department_id', 'label' => 'teachers'], 'subjects' => ['column' => 'department_id', 'label' => 'subjects'], 'timetables' => ['column' => 'department_id', 'label' => 'timetables']],
    'list_sql' => 'SELECT * FROM departments', 'count_sql' => 'SELECT COUNT(*) FROM departments',
    'search_columns' => ['code', 'name'], 'order' => 'name',
    'columns' => [
        ['label' => 'Code', 'value' => fn($row) => e($row['code'])],
        ['label' => 'Name', 'value' => fn($row) => e($row['name'])],
    ],
];
run_crud($config);
