<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
login_required();

$db = db();
$stats = [
    'teachers' => (int)$db->query('SELECT COUNT(*) FROM teachers')->fetchColumn(),
    'subjects' => (int)$db->query('SELECT COUNT(*) FROM subjects')->fetchColumn(),
    'classrooms' => (int)$db->query("SELECT COUNT(*) FROM classrooms WHERE room_type = 'CLASSROOM' AND is_active = 1")->fetchColumn(),
    'labs' => (int)$db->query("SELECT COUNT(*) FROM classrooms WHERE room_type = 'LABORATORY' AND is_active = 1")->fetchColumn(),
    'sections' => (int)$db->query('SELECT COUNT(*) FROM sections')->fetchColumn(),
    'timetables' => (int)$db->query('SELECT COUNT(*) FROM timetables')->fetchColumn(),
];

$recent = $db->query('SELECT t.id, t.version_number, t.status, t.generated_at, ay.name AS academic_year, d.name AS department, s.name AS semester FROM timetables t JOIN academic_years ay ON ay.id=t.academic_year_id JOIN departments d ON d.id=t.department_id JOIN semesters s ON s.id=t.semester_id ORDER BY t.generated_at DESC LIMIT 5')->fetchAll();

$pageTitle = 'Dashboard';
require __DIR__ . '/includes/header.php';
?>
<div class="row g-3 mb-4">
    <?php foreach ($stats as $label => $count): ?>
    <div class="col-6 col-lg-2">
        <div class="card stat-card h-100"><div class="card-body">
            <div class="text-muted small text-uppercase"><?= e($label) ?></div>
            <div class="stat-value"><?= $count ?></div>
        </div></div>
    </div>
    <?php endforeach; ?>
</div>
<div class="row g-4">
    <div class="col-lg-8">
        <div class="card"><div class="card-header"><strong>Recent timetable activity</strong></div>
            <div class="table-responsive"><table class="table table-hover align-middle mb-0">
                <thead><tr><th>Academic year</th><th>Semester</th><th>Version</th><th>Status</th><th>Generated</th><th></th></tr></thead>
                <tbody>
                <?php if (!$recent): ?><tr><td colspan="6" class="text-center text-muted py-4">No timetable generated yet.</td></tr><?php endif; ?>
                <?php foreach ($recent as $row): ?>
                    <tr>
                        <td><?= e($row['academic_year']) ?></td><td><?= e($row['semester']) ?></td>
                        <td>v<?= (int)$row['version_number'] ?></td><td><span class="badge status-<?= e(strtolower($row['status'])) ?>"><?= e($row['status']) ?></span></td>
                        <td><?= e(date('d M Y, h:i A', strtotime($row['generated_at']))) ?></td>
                        <td><a class="btn btn-sm btn-outline-primary" href="<?= url('timetable/view.php') ?>?timetable_id=<?= (int)$row['id'] ?>">View</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card"><div class="card-body">
            <h2 class="h6 text-muted text-uppercase">Quick actions</h2>
            <div class="d-grid gap-2">
                <a class="btn btn-primary" href="<?= url('timetable/generate.php') ?>">Generate timetable</a>
                <a class="btn btn-outline-primary" href="<?= url('modules/requirements/index.php') ?>">Manage weekly requirements</a>
                <a class="btn btn-outline-primary" href="<?= url('modules/availability/index.php') ?>">Set teacher availability</a>
                <a class="btn btn-outline-primary" href="<?= url('timetable/history.php') ?>">Timetable history</a>
            </div>
        </div></div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
