<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/timetable_service.php';
require_once __DIR__ . '/../includes/manual_edit.php';

function check_test(bool $condition, string $name): void
{
    if (!$condition) {
        fwrite(STDERR, "FAILED: $name\n");
        exit(1);
    }
    echo "PASS: $name\n";
}

$db = db();
$admin = fetch_one($db, 'SELECT password_hash FROM admins WHERE username=?', ['admin']);
check_test($admin && password_verify('admin123', $admin['password_hash']), 'Admin password hashing and verification');

$before = (int)$db->query('SELECT COUNT(*) FROM departments')->fetchColumn();
$db->beginTransaction();
$db->prepare('INSERT INTO departments (code,name) VALUES (?,?)')->execute(['TMP1','Temporary Test Department']);
$tempId = (int)$db->lastInsertId();
check_test((int)$db->query('SELECT COUNT(*) FROM departments')->fetchColumn() === $before + 1, 'Department create');
$db->prepare('UPDATE departments SET name=? WHERE id=?')->execute(['Temporary Department Updated', $tempId]);
check_test(fetch_one($db, 'SELECT name FROM departments WHERE id=?', [$tempId])['name'] === 'Temporary Department Updated', 'Department update');
$db->prepare('DELETE FROM departments WHERE id=?')->execute([$tempId]);
$db->rollBack();
check_test((int)$db->query('SELECT COUNT(*) FROM departments')->fetchColumn() === $before, 'Transactional CRUD rollback');

try {
    $db->prepare('DELETE FROM academic_years WHERE id=?')->execute([1]);
    check_test(false, 'Foreign key prevents deleting referenced academic year');
} catch (PDOException $exception) {
    check_test($exception->getCode() === '23000', 'Foreign key prevents deleting referenced academic year');
}

$timetable = fetch_one($db, 'SELECT * FROM timetables ORDER BY id LIMIT 1');
check_test((bool)$timetable, 'Timetable version exists');
if ($timetable) {
    $entry = fetch_one($db, 'SELECT * FROM timetable_entries WHERE timetable_id=? ORDER BY id LIMIT 1', [(int)$timetable['id']]);
    $slots = fetch_all($db, 'SELECT working_day_id, time_slot_id FROM timetable_entry_slots WHERE timetable_entry_id=? ORDER BY slot_sequence', [(int)$entry['id']]);
    $errors = validate_manual_allocation($db, (int)$timetable['id'], (int)$entry['id'], (int)$slots[0]['working_day_id'], (int)$entry['teacher_id'], (int)$entry['classroom_id'], array_map('intval', array_column($slots, 'time_slot_id')));
    check_test($errors === [], 'Manual edit validator accepts the current valid allocation');

    $errors = validate_manual_allocation($db, (int)$timetable['id'], (int)$entry['id'], (int)$slots[0]['working_day_id'], (int)$entry['teacher_id'] + 100, (int)$entry['classroom_id'], array_map('intval', array_column($slots, 'time_slot_id')));
    check_test(in_array('HC-10: selected teacher is not assigned to this subject.', $errors, true), 'Manual edit rejects unassigned teacher');

    save_manual_allocation($db, (int)$timetable['id'], (int)$entry['id'], (int)$slots[0]['working_day_id'], (int)$entry['teacher_id'], (int)$entry['classroom_id'], array_map('intval', array_column($slots, 'time_slot_id')));
    $afterSlots = fetch_all($db, 'SELECT working_day_id, time_slot_id FROM timetable_entry_slots WHERE timetable_entry_id=? ORDER BY slot_sequence', [(int)$entry['id']]);
    check_test($afterSlots == $slots, 'Manual edit transaction preserves a valid allocation');

    $teacherConflicts = (int)$db->query('SELECT COUNT(*) FROM (SELECT teacher_id,working_day_id,time_slot_id,COUNT(*) c FROM timetable_entry_slots GROUP BY timetable_id,teacher_id,working_day_id,time_slot_id HAVING c>1) x')->fetchColumn();
    $roomConflicts = (int)$db->query('SELECT COUNT(*) FROM (SELECT classroom_id,working_day_id,time_slot_id,COUNT(*) c FROM timetable_entry_slots GROUP BY timetable_id,classroom_id,working_day_id,time_slot_id HAVING c>1) y')->fetchColumn();
    $sectionConflicts = (int)$db->query('SELECT COUNT(*) FROM (SELECT section_id,working_day_id,time_slot_id,COUNT(*) c FROM timetable_entry_slots GROUP BY timetable_id,section_id,working_day_id,time_slot_id HAVING c>1) z')->fetchColumn();
    check_test($teacherConflicts === 0 && $roomConflicts === 0 && $sectionConflicts === 0, 'Database has zero teacher, room, and section conflicts');
}

$pdf = file_get_contents(__DIR__ . '/../exports/sample-timetable.pdf');
check_test($pdf && str_starts_with($pdf, '%PDF-1.4') && str_contains($pdf, '%%EOF'), 'PDF export contains valid PDF structure');
echo "PHP integration tests completed successfully.\n";
