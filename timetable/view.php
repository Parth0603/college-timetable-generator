<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/timetable_service.php';
login_required();

$db = db();
$timetableId = query_int('timetable_id');
if ($timetableId === null) {
    $row = $db->query('SELECT id FROM timetables ORDER BY generated_at DESC LIMIT 1')->fetch();
    $timetableId = $row ? (int)$row['id'] : 0;
}
if (!$timetableId) {
    $pageTitle = 'View Timetable';
    require __DIR__ . '/../includes/header.php';
    echo '<div class="alert alert-info">No timetable has been generated yet. <a href="generate.php">Generate one now</a>.</div>';
    require __DIR__ . '/../includes/footer.php';
    exit;
}

$scope = timetable_scope($db, $timetableId);
$mode = in_array($_GET['mode'] ?? 'section', ['section','teacher','classroom'], true) ? ($_GET['mode'] ?? 'section') : 'section';

if ($mode === 'section') {
    $resources = fetch_all($db, 'SELECT id, name AS label FROM sections WHERE semester_id=? ORDER BY name', [(int)$scope['semester_id']]);
} elseif ($mode === 'teacher') {
    $resources = fetch_all($db, 'SELECT DISTINCT t.id, t.name AS label FROM teachers t JOIN timetable_entries te ON te.teacher_id=t.id WHERE te.timetable_id=? ORDER BY t.name', [$timetableId]);
} else {
    $resources = fetch_all($db, 'SELECT DISTINCT c.id, c.name AS label FROM classrooms c JOIN timetable_entries te ON te.classroom_id=c.id WHERE te.timetable_id=? ORDER BY c.name', [$timetableId]);
}
$resourceId = query_int('resource_id', (int)($resources[0]['id'] ?? 0));
if (!$resourceId && $resources) $resourceId = (int)$resources[0]['id'];

[$scope, $days, $slots, $cells] = timetable_grid($db, $timetableId, $mode, $resourceId);
$pageTitle = 'View Timetable';
require __DIR__ . '/../includes/header.php';
?>
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <form class="card flex-grow-1 p-3" method="get">
        <input type="hidden" name="timetable_id" value="<?= $timetableId ?>">
        <div class="row g-2">
            <div class="col-md-3"><label class="form-label">Timetable</label>
                <select class="form-select" name="timetable_id" onchange="this.form.submit()">
                    <?= select_options($db, 'SELECT t.id, CONCAT(ay.name, " / ", s.name, " / v", t.version_number) AS label FROM timetables t JOIN academic_years ay ON ay.id=t.academic_year_id JOIN semesters s ON s.id=t.semester_id ORDER BY t.generated_at DESC', [], $timetableId) ?>
                </select>
            </div>
            <div class="col-md-3"><label class="form-label">Mode</label>
                <select class="form-select" name="mode" onchange="this.form.submit()">
                    <option value="section" <?= $mode === 'section' ? 'selected' : '' ?>>Section-wise</option>
                    <option value="teacher" <?= $mode === 'teacher' ? 'selected' : '' ?>>Teacher-wise</option>
                    <option value="classroom" <?= $mode === 'classroom' ? 'selected' : '' ?>>Classroom-wise</option>
                </select>
            </div>
            <div class="col-md-3"><label class="form-label"><?= ucfirst($mode) ?></label>
                <select class="form-select" name="resource_id" onchange="this.form.submit()"><?= option_list($resources, $resourceId) ?></select>
            </div>
            <div class="col-md-3"><label class="form-label">Actions</label><div class="d-grid gap-2 d-md-flex">
                <a class="btn btn-outline-primary" href="export.php?timetable_id=<?= $timetableId ?>&mode=<?= e($mode) ?>&resource_id=<?= $resourceId ?>&format=print">Print</a>
                <a class="btn btn-primary" href="export.php?timetable_id=<?= $timetableId ?>&mode=<?= e($mode) ?>&resource_id=<?= $resourceId ?>&format=pdf">PDF</a>
            </div></div>
        </div>
    </form>
</div>
<div class="card mb-3"><div class="card-body d-flex flex-wrap gap-4">
    <div><div class="text-muted small">Academic year</div><strong><?= e($scope['academic_year']) ?></strong></div>
    <div><div class="text-muted small">Department</div><strong><?= e($scope['department']) ?></strong></div>
    <div><div class="text-muted small">Semester</div><strong><?= e($scope['semester']) ?></strong></div>
    <div><div class="text-muted small">Version</div><strong>v<?= (int)$scope['version_number'] ?></strong></div>
    <div><div class="text-muted small">Status</div><span class="badge status-<?= e(strtolower($scope['status'])) ?>"><?= e($scope['status']) ?></span></div>
    <div><div class="text-muted small">Quality score</div><strong><?= (int)$scope['quality_score'] ?></strong></div>
    <div class="ms-auto"><a class="btn btn-outline-primary" href="edit.php?timetable_id=<?= $timetableId ?>">Edit manually</a></div>
</div></div>
<div class="timetable-card">
<div class="table-responsive">
<table class="timetable-table">
<thead><tr><th>Time</th><?php foreach ($days as $day): ?><th><?= e($day['day_name']) ?></th><?php endforeach; ?></tr></thead>
<tbody>
<?php foreach ($slots as $slot): ?>
<tr>
    <th><?= e($slot['label']) ?><div class="text-muted small"><?= e(date('h:i A', strtotime($slot['start_time']))) ?> - <?= e(date('h:i A', strtotime($slot['end_time']))) ?></div></th>
    <?php foreach ($days as $day): ?>
        <?php if ((int)$slot['is_break'] === 1): ?>
            <td class="timetable-break">Break</td>
        <?php else: $items = $cells[(int)$day['id']][(int)$slot['id']] ?? []; ?>
            <td class="<?= $items && (int)$items[0]['is_practical'] ? 'timetable-practical' : '' ?>">
                <?php if (!$items): ?><span class="timetable-empty">?</span><?php endif; ?>
                <?php foreach ($items as $item): ?>
                    <div class="mb-1">
                        <div class="timetable-cell-subject"><?= e($item['subject_name']) ?></div>
                        <div class="timetable-cell-meta">
                            <?= e($item['teacher_name']) ?><br>
                            <?= e($item['room_name']) ?><?php if ($mode !== 'section'): ?> &middot; <?= e($item['section_name']) ?><?php endif; ?>
                            <?php if ($item['is_practical']): ?><br><strong>Practical</strong><?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </td>
        <?php endif; ?>
    <?php endforeach; ?>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
