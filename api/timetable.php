<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/scheduler_client.php';
require_once __DIR__ . '/../includes/timetable_service.php';

login_required();
header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new RuntimeException('POST request required.');
    require_csrf();
    $payload = json_decode(file_get_contents('php://input') ?: '[]', true);
    if (!is_array($payload)) throw new RuntimeException('Invalid JSON request.');
    if (($payload['action'] ?? '') !== 'generate') throw new RuntimeException('Unsupported API action.');

    $academicYearId = filter_var($payload['academic_year_id'] ?? 0, FILTER_VALIDATE_INT);
    $departmentId = filter_var($payload['department_id'] ?? 0, FILTER_VALIDATE_INT);
    $semesterId = filter_var($payload['semester_id'] ?? 0, FILTER_VALIDATE_INT);
    if (!$academicYearId || !$departmentId || !$semesterId) throw new RuntimeException('Academic year, department, and semester are required.');

    $scope = build_scheduler_input(db(), $academicYearId, $departmentId, $semesterId);
    $result = run_python_scheduler($scope);
    $entries = validate_generated_timetable(db(), $scope, $result);
    $timetableId = save_generated_timetable(db(), $scope, $entries, (int)($result['quality_score'] ?? 0), 'Generated automatically');
    echo json_encode([
        'success' => true,
        'timetable_id' => $timetableId,
        'message' => 'Timetable generated successfully with all hard constraints satisfied.',
        'quality_score' => (int)($result['quality_score'] ?? 0),
    ]);
} catch (Throwable $exception) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
}
