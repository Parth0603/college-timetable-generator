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
    ['name' => 'slot_order', 'label' => 'Slot order', 'type' => 'number', 'required' => true, 'min' => 1, 'max' => 20],
    ['name' => 'start_time', 'label' => 'Start time', 'type' => 'time', 'required' => true],
    ['name' => 'end_time', 'label' => 'End time', 'type' => 'time', 'required' => true],
    ['name' => 'label', 'label' => 'Display label', 'type' => 'text', 'required' => true, 'max' => 60],
    ['name' => 'is_break', 'label' => 'Break period', 'type' => 'checkbox'],
];
$config = [
    'title' => 'Lecture Time Slots', 'singular' => 'Time slot', 'table' => 'time_slots', 'fields' => $fields,
    'validate' => function (PDO $db, array $data, ?array $current) use ($fields): array {
        $errors = validate_fields(['fields' => $fields], $data);
        if ($data['start_time'] >= $data['end_time']) $errors[] = 'End time must be after start time.';
        $statement = $db->prepare('SELECT id FROM time_slots WHERE academic_year_id=? AND slot_order=? AND id<>?');
        $statement->execute([$data['academic_year_id'], $data['slot_order'], (int)($current['id'] ?? 0)]);
        if ($statement->fetch()) $errors[] = 'This slot order already exists in the selected academic year.';
        return $errors;
    },
    'delete_blockers' => ['teacher_availability' => ['column' => 'time_slot_id', 'label' => 'teacher availability'], 'timetable_entry_slots' => ['column' => 'time_slot_id', 'label' => 'timetable slots']],
    'filters' => [['name' => 'academic_year_id', 'label' => 'academic years', 'column' => 'ts.academic_year_id', 'options' => $yearOptions]],
    'list_sql' => 'SELECT ts.*, ay.name AS academic_year FROM time_slots ts JOIN academic_years ay ON ay.id=ts.academic_year_id',
    'count_sql' => 'SELECT COUNT(*) FROM time_slots ts', 'search_columns' => ['ts.label'], 'order' => 'ts.academic_year_id, ts.slot_order',
    'columns' => [
        ['label' => 'Academic year', 'value' => fn($row) => e($row['academic_year'])],
        ['label' => 'Slot', 'value' => fn($row) => (int)$row['slot_order']],
        ['label' => 'Label', 'value' => fn($row) => e($row['label'])],
        ['label' => 'Time', 'value' => fn($row) => e(date('h:i A', strtotime($row['start_time']))) . ' - ' . e(date('h:i A', strtotime($row['end_time'])))],
        ['label' => 'Type', 'value' => fn($row) => $row['is_break'] ? '<span class="badge bg-warning-subtle text-warning-emphasis">Break</span>' : '<span class="badge bg-primary-subtle text-primary">Lecture</span>'],
    ],
];
run_crud($config);
