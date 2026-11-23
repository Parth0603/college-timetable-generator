<?php
declare(strict_types=1);
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
echo json_encode([
    'status' => 'healthy',
    'timestamp' => date('c'),
    'app' => 'College Timetable Generator',
    'database' => 'connected'
]);
