<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
login_required();

$db = db();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $id = post_int('id', 0);
    $action = post_string('action', 20);
    if ($action === 'publish') {
        $statement = $db->prepare("UPDATE timetables SET status='PUBLISHED', published_at=NOW() WHERE id=? AND status='DRAFT'");
        $statement->execute([$id]);
        redirect_with_flash('history.php', 'Timetable published successfully.');
    }
    if ($action === 'archive') {
        $statement = $db->prepare("UPDATE timetables SET status='ARCHIVED' WHERE id=?");
        $statement->execute([$id]);
        redirect_with_flash('history.php', 'Timetable archived.');
    }
}

$rows = $db->query('SELECT t.*, ay.name AS academic_year, d.name AS department, s.name AS semester, (SELECT COUNT(*) FROM timetable_entries te WHERE te.timetable_id=t.id) AS entries FROM timetables t JOIN academic_years ay ON ay.id=t.academic_year_id JOIN departments d ON d.id=t.department_id JOIN semesters s ON s.id=t.semester_id ORDER BY t.generated_at DESC')->fetchAll();
$pageTitle = 'Timetable History';
require __DIR__ . '/../includes/header.php';
?>
<div class="card"><div class="card-header d-flex justify-content-between align-items-center"><strong>Timetable versions</strong><a class="btn btn-primary" href="generate.php">Generate new version</a></div>
<div class="table-responsive"><table class="table table-hover align-middle mb-0">
<thead><tr><th>Scope</th><th>Version</th><th>Status</th><th>Entries</th><th>Quality</th><th>Generated</th><th class="text-end">Actions</th></tr></thead>
<tbody>
<?php if (!$rows): ?><tr><td colspan="7" class="text-center text-muted py-4">No timetable versions yet.</td></tr><?php endif; ?>
<?php foreach ($rows as $row): ?>
<tr>
<td><?= e($row['academic_year'] . ' / ' . $row['department'] . ' / ' . $row['semester']) ?></td>
<td>v<?= (int)$row['version_number'] ?></td>
<td><span class="badge status-<?= e(strtolower($row['status'])) ?>"><?= e($row['status']) ?></span></td>
<td><?= (int)$row['entries'] ?></td><td><?= (int)$row['quality_score'] ?></td>
<td><?= e(date('d M Y, h:i A', strtotime($row['generated_at']))) ?></td>
<td class="text-end"><div class="d-inline-flex gap-1">
<a class="btn btn-sm btn-outline-primary" href="view.php?timetable_id=<?= (int)$row['id'] ?>">View</a>
<?php if ($row['status'] === 'DRAFT'): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="publish"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><button class="btn btn-sm btn-outline-success">Publish</button></form><?php endif; ?>
<?php if ($row['status'] !== 'ARCHIVED'): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="archive"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><button class="btn btn-sm btn-outline-danger">Archive</button></form><?php endif; ?>
</div></td>
</tr>
<?php endforeach; ?>
</tbody></table></div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
