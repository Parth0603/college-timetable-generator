<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/scheduler_client.php';
require_once __DIR__ . '/../includes/timetable_service.php';
require_once __DIR__ . '/../includes/manual_edit.php';
login_required();

$db = db();
$timetableId = query_int('timetable_id', (int)$db->query('SELECT id FROM timetables ORDER BY generated_at DESC LIMIT 1')->fetchColumn());
if (!$timetableId) {
    $pageTitle = 'Edit Timetable';
    require __DIR__ . '/../includes/header.php';
    echo '<div class="alert alert-info">Generate a timetable before editing it.</div>';
    require __DIR__ . '/../includes/footer.php';
    exit;
}
$scope = timetable_scope($db, $timetableId);
$errors = [];

$entries = fetch_all($db, 'SELECT te.*, sec.name AS section_name, sub.code, sub.name AS subject_name, t.name AS teacher_name, c.name AS classroom_name FROM timetable_entries te JOIN sections sec ON sec.id=te.section_id JOIN subjects sub ON sub.id=te.subject_id JOIN teachers t ON t.id=te.teacher_id JOIN classrooms c ON c.id=te.classroom_id WHERE te.timetable_id=? ORDER BY sec.name, sub.code, te.id', [$timetableId]);
$entryId = query_int('entry_id', (int)($entries[0]['id'] ?? 0));
$entry = null;
foreach ($entries as $candidate) if ((int)$candidate['id'] === $entryId) $entry = $candidate;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $entryId = post_int('entry_id', 0);
    $entry = null;
    foreach ($entries as $candidate) if ((int)$candidate['id'] === $entryId) $entry = $candidate;
    $dayId = post_int('day_id', 0);
    $teacherId = post_int('teacher_id', 0);
    $classroomId = post_int('classroom_id', 0);
    $slotIds = array_map('intval', (array)($_POST['slot_ids'] ?? []));
    $errors = validate_manual_allocation($db, $timetableId, $entryId, $dayId, $teacherId, $classroomId, $slotIds);
    if (!$errors) {
        try {
            save_manual_allocation($db, $timetableId, $entryId, $dayId, $teacherId, $classroomId, $slotIds);
            redirect_with_flash('view.php?timetable_id=' . $timetableId, 'Manual edit saved successfully.');
        } catch (PDOException $exception) {
            $errors[] = friendly_sql_error($exception);
        }
    }
}

if (!$entry) {
    $pageTitle = 'Edit Timetable';
    require __DIR__ . '/../includes/header.php';
    echo '<div class="alert alert-warning">Select a valid timetable entry.</div>';
    require __DIR__ . '/../includes/footer.php';
    exit;
}

$days = fetch_all($db, 'SELECT id, day_name AS label FROM working_days WHERE academic_year_id=? ORDER BY day_index', [(int)$scope['academic_year_id']]);
$slots = fetch_all($db, 'SELECT id, slot_order, label, start_time, end_time, is_break FROM time_slots WHERE academic_year_id=? ORDER BY slot_order', [(int)$scope['academic_year_id']]);
$currentSlots = fetch_all($db, 'SELECT time_slot_id, working_day_id FROM timetable_entry_slots WHERE timetable_entry_id=? ORDER BY slot_sequence', [(int)$entry['id']]);
$currentDay = (int)($currentSlots[0]['working_day_id'] ?? 0);
$teacherOptions = fetch_all($db, 'SELECT t.id, t.name AS label FROM teachers t JOIN teacher_subjects ts ON ts.teacher_id=t.id WHERE ts.subject_id=? ORDER BY t.name', [(int)$entry['subject_id']]);
$classroomOptions = fetch_all($db, 'SELECT id, CONCAT(name, \' (\', capacity, \' seats)\') AS label, room_type, capacity FROM classrooms WHERE is_active=1 ORDER BY room_type, name');
$pageTitle = 'Edit Timetable';
require __DIR__ . '/../includes/header.php';
?>
<div class="row g-4">
<div class="col-xl-4"><div class="card"><div class="card-header"><strong>Selected entry</strong></div><div class="card-body">
<p class="mb-1 text-muted small">Section</p><strong><?= e($entry['section_name']) ?></strong>
<p class="mt-3 mb-1 text-muted small">Subject</p><strong><?= e($entry['code'] . ' - ' . $entry['subject_name']) ?></strong>
<p class="mt-3 mb-1 text-muted small">Current allocation</p><strong><?= e($entry['teacher_name'] . ' / ' . $entry['classroom_name']) ?></strong>
<div class="mt-3"><a class="btn btn-outline-primary" href="view.php?timetable_id=<?= $timetableId ?>">Back to timetable</a></div>
</div></div></div>
<div class="col-xl-8">
<div class="card"><div class="card-header"><strong>Manual allocation</strong></div><div class="card-body">
<?php foreach ($errors as $error): ?><div class="alert alert-danger py-2"><?= e($error) ?></div><?php endforeach; ?>
<form method="post">
<?= csrf_field() ?>
<input type="hidden" name="entry_id" value="<?= (int)$entry['id'] ?>">
<div class="mb-3"><label class="form-label" for="entry">Timetable entry</label>
<select class="form-select" id="entry" name="entry_id" onchange="window.location='edit.php?timetable_id=<?= $timetableId ?>&entry_id='+this.value">
<?php foreach ($entries as $candidate): ?>
<option value="<?= (int)$candidate['id'] ?>" <?= (int)$candidate['id'] === (int)$entry['id'] ? 'selected' : '' ?>><?= e($candidate['section_name'] . ' - ' . $candidate['code']) ?></option>
<?php endforeach; ?>
</select></div>
<div class="row g-3">
<div class="col-md-6"><label class="form-label" for="day_id">Working day</label>
<select class="form-select" id="day_id" name="day_id"><?= option_list($days, $currentDay) ?></select></div>
<div class="col-md-6"><label class="form-label" for="teacher_id">Teacher</label>
<select class="form-select" id="teacher_id" name="teacher_id"><?= option_list($teacherOptions, (int)$entry['teacher_id']) ?></select></div>
<div class="col-md-6"><label class="form-label" for="classroom_id">Classroom or laboratory</label>
<select class="form-select" id="classroom_id" name="classroom_id"><?= option_list($classroomOptions, (int)$entry['classroom_id']) ?></select></div>
<div class="col-md-6"><label class="form-label">Occupied periods</label>
<div class="border rounded p-2" style="max-height:180px; overflow:auto;">
<?php foreach ($slots as $slot): ?>
<div class="form-check"><input class="form-check-input" type="checkbox" name="slot_ids[]" id="slot<?= (int)$slot['id'] ?>" value="<?= (int)$slot['id'] ?>" <?= in_array((int)$slot['id'], array_map('intval', array_column($currentSlots, 'time_slot_id')), true) ? 'checked' : '' ?> <?= (int)$slot['is_break'] ? 'disabled' : '' ?>>
<label class="form-check-label" for="slot<?= (int)$slot['id'] ?>"><?= e($slot['label'] . ' (' . date('h:i A', strtotime($slot['start_time'])) . ')') ?></label></div>
<?php endforeach; ?>
</div><div class="form-text">Select exactly <?= (int)$entry['periods_count'] ?> consecutive period(s).</div></div>
</div>
<div class="mt-3 d-flex gap-2"><button class="btn btn-primary" type="submit">Validate and save</button><a class="btn btn-outline-secondary" href="edit.php?timetable_id=<?= $timetableId ?>">Reset</a></div>
</form>
</div></div>
</div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
