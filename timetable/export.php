<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/timetable_service.php';
require_once __DIR__ . '/../includes/pdf.php';
login_required();

$timetableId = query_int('timetable_id', 0);
$mode = in_array($_GET['mode'] ?? 'section', ['section','teacher','classroom'], true) ? ($_GET['mode'] ?? 'section') : 'section';
$resourceId = query_int('resource_id', 0);
$format = in_array($_GET['format'] ?? 'print', ['print','pdf','html'], true) ? ($_GET['format'] ?? 'print') : 'print';

if (!$timetableId) {
    http_response_code(404);
    exit('Timetable not found.');
}

[$scope, $days, $slots, $cells] = timetable_grid(db(), $timetableId, $mode, $resourceId);
$resourceName = 'All';
if ($mode === 'section') {
    $resource = fetch_one(db(), 'SELECT name FROM sections WHERE id=?', [$resourceId]);
    $resourceName = $resource['name'] ?? 'Unknown section';
} elseif ($mode === 'teacher') {
    $resource = fetch_one(db(), 'SELECT name FROM teachers WHERE id=?', [$resourceId]);
    $resourceName = $resource['name'] ?? 'Unknown teacher';
} else {
    $resource = fetch_one(db(), 'SELECT name FROM classrooms WHERE id=?', [$resourceId]);
    $resourceName = $resource['name'] ?? 'Unknown room';
}

if ($format === 'pdf') {
    $pdf = new SimplePdf();
    $pdf->text(30, 550, COLLEGE_NAME, 18, true);
    $pdf->text(30, 529, 'Department of ' . $scope['department'] . ' | ' . $scope['academic_year'] . ' | ' . $scope['semester'], 10);
    $pdf->text(30, 513, ucfirst($mode) . ' timetable: ' . $resourceName, 11, true);

    $left = 28;
    $top = 480;
    $timeWidth = 78;
    $dayWidth = ($pdf ? 786 : 786) / max(1, count($days));
    $rowHeight = 60;
    $pdf->cell($left, $top, $timeWidth, $rowHeight, 'Time', true);
    foreach ($days as $index => $day) {
        $pdf->cell($left + $timeWidth + ($index * $dayWidth), $top, $dayWidth, $rowHeight, $day['day_name'], true);
    }

    $y = $top - $rowHeight;
    foreach ($slots as $slot) {
        $pdf->cell($left, $y, $timeWidth, $rowHeight, $slot['label'] . ' ' . date('h:i A', strtotime($slot['start_time'])), false);
        foreach ($days as $index => $day) {
            $items = $cells[(int)$day['id']][(int)$slot['id']] ?? [];
            if ((int)$slot['is_break'] === 1) {
                $text = 'BREAK';
            } elseif ($items) {
                $first = $items[0];
                $text = $first['subject_name'] . ' / ' . $first['teacher_name'] . ' / ' . $first['room_name'];
                if ($mode !== 'section') $text .= ' / ' . $first['section_name'];
            } else {
                $text = '-';
            }
            $pdf->cell($left + $timeWidth + ($index * $dayWidth), $y, $dayWidth, $rowHeight, $text, false);
        }
        $y -= $rowHeight;
    }

    $pdf->text(30, 22, 'Version v' . $scope['version_number'] . ' | Status: ' . $scope['status'] . ' | Generated: ' . date('d M Y h:i A', strtotime($scope['generated_at'])), 7);
    $pdf->output('timetable-' . $mode . '-' . $timetableId . '.pdf');
}

$pageTitle = 'Print Timetable';
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Timetable export</title><style>
body{font-family:Arial,Helvetica,sans-serif;background:#fff;color:#111;margin:0;padding:24px}
.sheet{max-width:1120px;margin:auto}
h1{margin:0;font-size:22px}h2{margin:4px 0 16px;font-size:15px;color:#333}
table{width:100%;border-collapse:collapse;table-layout:fixed}th,td{border:1px solid #333;padding:7px;font-size:11px;vertical-align:top}
th{background:#eaf1ff} .break{background:#fff5e5;text-align:center;font-weight:bold}
.subject{font-weight:bold}.meta{color:#555;font-size:10px}.toolbar{max-width:1120px;margin:0 auto 16px;text-align:right;gap:8px}
@media print{.toolbar{display:none} body{padding:0}}
</style></head><body>
<div class="toolbar no-print"><button onclick="window.print()">Print</button></div>
<div class="sheet">
<h1><?= e(COLLEGE_NAME) ?></h1>
<h2>Department: <?= e($scope['department']) ?> | Academic Year: <?= e($scope['academic_year']) ?> | Semester: <?= e($scope['semester']) ?> | <?= ucfirst($mode) ?>: <?= e($resourceName) ?></h2>
<table><thead><tr><th style="width:130px">Time</th><?php foreach ($days as $day): ?><th><?= e($day['day_name']) ?></th><?php endforeach; ?></tr></thead><tbody>
<?php foreach ($slots as $slot): ?><tr><th><?= e($slot['label']) ?><div><?= e(date('h:i A', strtotime($slot['start_time']))) ?></div></th>
<?php foreach ($days as $day): $items = $cells[(int)$day['id']][(int)$slot['id']] ?? []; ?>
<?php if ((int)$slot['is_break']): ?><td class="break">Break</td>
<?php elseif ($items): $first=$items[0]; ?><td><div class="subject"><?= e($first['subject_name']) ?></div><div class="meta"><?= e($first['teacher_name']) ?><br><?= e($first['room_name']) ?><?php if ($mode!=='section'): ?> &middot; <?= e($first['section_name']) ?><?php endif; ?></div></td>
<?php else: ?><td style="text-align:center">?</td><?php endif; ?>
<?php endforeach; ?></tr><?php endforeach; ?>
</tbody></table>
<p style="font-size:10px;color:#555;margin-top:14px">Version v<?= (int)$scope['version_number'] ?> &middot; <?= e($scope['status']) ?> &middot; Generated <?= e(date('d M Y h:i A', strtotime($scope['generated_at']))) ?></p>
</div>
</body></html>
