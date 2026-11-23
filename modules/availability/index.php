<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
login_required();

$db = db();
$teachers = $db->query('SELECT id, name FROM teachers ORDER BY name')->fetchAll();
$years = $db->query('SELECT id, name FROM academic_years ORDER BY start_date DESC')->fetchAll();
$teacherId = query_int('teacher_id', (int)($teachers[0]['id'] ?? 0));
$yearId = query_int('year_id', (int)($years[0]['id'] ?? 0));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    if (post_string('action', 20) === 'initialize') {
        $teacherId = post_int('teacher_id', 0);
        $yearId = post_int('year_id', 0);
        $statement = $db->prepare('INSERT IGNORE INTO teacher_availability (teacher_id, working_day_id, time_slot_id, is_available) SELECT ?, wd.id, ts.id, 1 FROM working_days wd JOIN time_slots ts ON ts.academic_year_id=wd.academic_year_id WHERE wd.academic_year_id=?');
        $statement->execute([$teacherId, $yearId]);
        redirect_with_flash('index.php?teacher_id=' . $teacherId . '&year_id=' . $yearId, 'Availability grid created.');
    }
    if (post_string('action', 20) === 'toggle') {
        $available = post_int('is_available', 0) === 0 ? 0 : 1;
        $statement = $db->prepare('UPDATE teacher_availability SET is_available=? WHERE id=?');
        $statement->execute([$available, post_int('availability_id', 0)]);
        if (is_ajax()) json_response(['success' => true]);
        redirect_with_flash($_SERVER['REQUEST_URI'], 'Availability updated.');
    }
}

$days = [];
$slots = [];
$availability = [];
if ($teacherId && $yearId) {
    $statement = $db->prepare('SELECT * FROM working_days WHERE academic_year_id=? ORDER BY day_index');
    $statement->execute([$yearId]);
    $days = $statement->fetchAll();
    $statement = $db->prepare('SELECT * FROM time_slots WHERE academic_year_id=? ORDER BY slot_order');
    $statement->execute([$yearId]);
    $slots = $statement->fetchAll();
    $statement = $db->prepare('SELECT * FROM teacher_availability WHERE teacher_id=? AND working_day_id IN (SELECT id FROM working_days WHERE academic_year_id=?)');
    $statement->execute([$teacherId, $yearId]);
    foreach ($statement->fetchAll() as $row) {
        $availability[(int)$row['working_day_id']][(int)$row['time_slot_id']] = $row;
    }
}

$pageTitle = 'Teacher Availability';
require __DIR__ . '/../../includes/header.php';
?>
<div class="card mb-4"><div class="card-body">
    <form class="row g-3 align-items-end" method="get">
        <div class="col-md-4"><label class="form-label" for="teacher_id">Teacher</label>
            <select class="form-select" id="teacher_id" name="teacher_id" onchange="this.form.submit()">
                <?php foreach ($teachers as $teacher): ?><option value="<?= (int)$teacher['id'] ?>" <?= $teacherId === (int)$teacher['id'] ? 'selected' : '' ?>><?= e($teacher['name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4"><label class="form-label" for="year_id">Academic year</label>
            <select class="form-select" id="year_id" name="year_id" onchange="this.form.submit()">
                <?php foreach ($years as $year): ?><option value="<?= (int)$year['id'] ?>" <?= $yearId === (int)$year['id'] ? 'selected' : '' ?>><?= e($year['name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <button class="btn btn-primary" type="submit">Show grid</button>
            <button class="btn btn-outline-primary" type="submit" name="initialize_grid" value="1">Create missing grid</button>
        </div>
    </form>
</div></div>
<?php if ($initialize_grid ?? false): ?>
<form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="initialize"><input type="hidden" name="teacher_id" value="<?= (int)$teacherId ?>"><input type="hidden" name="year_id" value="<?= (int)$yearId ?>"><button class="btn btn-outline-primary">Initialize availability</button></form>
<?php endif; ?>
<?php if (!$days || !$slots): ?>
<div class="alert alert-info">Select a teacher and academic year with configured working days and time slots.</div>
<?php else: ?>
<form method="post" id="initialize-form"><?= csrf_field() ?><input type="hidden" name="action" value="initialize"><input type="hidden" name="teacher_id" value="<?= (int)$teacherId ?>"><input type="hidden" name="year_id" value="<?= (int)$yearId ?>"></form>
<div class="card"><div class="card-header d-flex justify-content-between align-items-center"><strong>Weekly availability</strong>
<button class="btn btn-sm btn-outline-primary" form="initialize-form" type="submit">Create missing slots</button></div>
<div class="table-responsive"><table class="table table-bordered align-middle mb-0">
<thead><tr><th>Time slot</th><?php foreach ($days as $day): ?><th><?= e($day['day_name']) ?></th><?php endforeach; ?></tr></thead>
<tbody>
<?php foreach ($slots as $slot): ?>
<tr><th><?= e($slot['label']) ?><div class="text-muted small"><?= e(date('h:i A', strtotime($slot['start_time']))) ?></div></th>
<?php foreach ($days as $day): ?>
<td>
<?php $record = $availability[(int)$day['id']][(int)$slot['id']] ?? null; ?>
<?php if ($record): ?>
<input class="form-check-input toggle-availability" type="checkbox" data-id="<?= (int)$record['id'] ?>" <?= $record['is_available'] ? 'checked' : '' ?>>
<?php else: ?><span class="text-muted small">Not created</span><?php endif; ?>
</td>
<?php endforeach; ?></tr>
<?php endforeach; ?>
</tbody></table></div></div>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
