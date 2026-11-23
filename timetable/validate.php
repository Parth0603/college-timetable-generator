<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/timetable_service.php';
login_required();

$db = db();
$timetableId = query_int('timetable_id', (int)$db->query('SELECT id FROM timetables ORDER BY generated_at DESC LIMIT 1')->fetchColumn());
$issues = [];
$checks = [];
if ($timetableId) {
    $scope = timetable_scope($db, $timetableId);
    $conflicts = $db->prepare('SELECT tes.teacher_id, tes.working_day_id, tes.time_slot_id, COUNT(*) AS total FROM timetable_entry_slots tes WHERE tes.timetable_id=? GROUP BY tes.timetable_id, tes.teacher_id, tes.working_day_id, tes.time_slot_id HAVING COUNT(*)>1');
    $conflicts->execute([$timetableId]);
    $teacherIssues = $conflicts->fetchAll();
    $roomConflicts = $db->prepare('SELECT tes.classroom_id, tes.working_day_id, tes.time_slot_id, COUNT(*) AS total FROM timetable_entry_slots tes WHERE tes.timetable_id=? GROUP BY tes.timetable_id, tes.classroom_id, tes.working_day_id, tes.time_slot_id HAVING COUNT(*)>1');
    $roomConflicts->execute([$timetableId]);
    $roomIssues = $roomConflicts->fetchAll();
    $sectionConflicts = $db->prepare('SELECT tes.section_id, tes.working_day_id, tes.time_slot_id, COUNT(*) AS total FROM timetable_entry_slots tes WHERE tes.timetable_id=? GROUP BY tes.timetable_id, tes.section_id, tes.working_day_id, tes.time_slot_id HAVING COUNT(*)>1');
    $sectionConflicts->execute([$timetableId]);
    $sectionIssues = $sectionConflicts->fetchAll();
    $issues = array_merge($teacherIssues, $roomIssues, $sectionIssues);
    $checks[] = ['Teacher double booking', count($teacherIssues) === 0 ? 'Passed' : 'Failed'];
    $checks[] = ['Classroom double booking', count($roomIssues) === 0 ? 'Passed' : 'Failed'];
    $checks[] = ['Section double booking', count($sectionIssues) === 0 ? 'Passed' : 'Failed'];
    $breaks = $db->prepare('SELECT COUNT(*) FROM timetable_entry_slots tes JOIN time_slots ts ON ts.id=tes.time_slot_id WHERE tes.timetable_id=? AND ts.is_break=1');
    $breaks->execute([$timetableId]);
    $checks[] = ['Break-period exclusion', (int)$breaks->fetchColumn() === 0 ? 'Passed' : 'Failed'];
    $requirements = $db->prepare('SELECT sr.id, sr.weekly_lectures, (SELECT COUNT(*) FROM timetable_entries te WHERE te.timetable_id=? AND te.requirement_id=sr.id) AS scheduled FROM subject_requirements sr JOIN sections sec ON sec.id=sr.section_id WHERE sec.semester_id=?');
    $requirements->execute([$timetableId, (int)$scope['semester_id']]);
    $lectureCheck = true;
    foreach ($requirements->fetchAll() as $requirement) if ((int)$requirement['scheduled'] !== (int)$requirement['weekly_lectures']) $lectureCheck = false;
    $checks[] = ['Weekly lecture requirement count', $lectureCheck ? 'Passed' : 'Failed'];
} else {
    $checks[] = ['Timetable data', 'No timetable available'];
}
$pageTitle = 'Validate Timetable';
require __DIR__ . '/../includes/header.php';
?>
<div class="row g-4">
<div class="col-xl-5"><div class="card"><div class="card-header"><strong>Hard constraint checks</strong></div><div class="card-body">
<?php foreach ($checks as $check): ?>
<div class="d-flex justify-content-between border-bottom py-2"><span><?= e($check[0]) ?></span><strong class="<?= $check[1] === 'Passed' ? 'text-success' : 'text-danger' ?>"><?= e($check[1]) ?></strong></div>
<?php endforeach; ?>
</div></div></div>
<div class="col-xl-7"><div class="card"><div class="card-header"><strong>Detected issues</strong></div><div class="card-body">
<?php if (!$issues): ?><div class="alert alert-success mb-0">No double-booking issues detected.</div><?php else: ?>
<table class="table"><thead><tr><th>Type</th><th>Day</th><th>Slot</th><th>Count</th></tr></thead><tbody>
<?php foreach ($issues as $issue): ?>
<tr><td>Occupancy conflict</td><td><?= (int)$issue['working_day_id'] ?></td><td><?= (int)$issue['time_slot_id'] ?></td><td><?= (int)$issue['total'] ?></td></tr>
<?php endforeach; ?>
</tbody></table>
<?php endif; ?>
</div></div></div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
