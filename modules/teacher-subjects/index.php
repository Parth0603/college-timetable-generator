<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/crud.php';
login_required();

$teacherOptions = 'SELECT id, name AS label FROM teachers ORDER BY name';
$subjectOptions = 'SELECT id, CONCAT(code, \' - \', name) AS label FROM subjects ORDER BY code';
$fields = [
    ['name' => 'teacher_id', 'label' => 'Teacher', 'type' => 'select', 'required' => true, 'options' => $teacherOptions],
    ['name' => 'subject_id', 'label' => 'Subject', 'type' => 'select', 'required' => true, 'options' => $subjectOptions],
];
$config = [
    'title' => 'Teacher-Subject Assignments', 'singular' => 'Assignment', 'table' => 'teacher_subjects', 'fields' => $fields,
    'validate' => function (PDO $db, array $data, ?array $current): array {
        $errors = [];
        $statement = $db->prepare('SELECT id FROM teacher_subjects WHERE teacher_id=? AND subject_id=? AND id<>?');
        $statement->execute([$data['teacher_id'], $data['subject_id'], (int)($current['id'] ?? 0)]);
        if ($statement->fetch()) $errors[] = 'This teacher is already assigned to this subject.';
        return $errors;
    },
    'delete_blockers' => [],
    'filters' => [
        ['name' => 'teacher_id', 'label' => 'teachers', 'column' => 'ts.teacher_id', 'options' => $teacherOptions],
        ['name' => 'subject_id', 'label' => 'subjects', 'column' => 'ts.subject_id', 'options' => $subjectOptions],
    ],
    'list_sql' => 'SELECT ts.*, t.name AS teacher, sub.code, sub.name AS subject FROM teacher_subjects ts JOIN teachers t ON t.id=ts.teacher_id JOIN subjects sub ON sub.id=ts.subject_id',
    'count_sql' => 'SELECT COUNT(*) FROM teacher_subjects ts', 'search_columns' => ['t.name', 'sub.name', 'sub.code'], 'order' => 't.name, sub.code',
    'columns' => [
        ['label' => 'Teacher', 'value' => fn($row) => e($row['teacher'])],
        ['label' => 'Subject', 'value' => fn($row) => e($row['code'] . ' - ' . $row['subject'])],
    ],
];
run_crud($config);
