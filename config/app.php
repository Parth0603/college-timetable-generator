<?php
declare(strict_types=1);

const APP_NAME = 'College Timetable Generator';
const COLLEGE_NAME = 'ABC Institute of Engineering and Technology';
const SESSION_TIMEOUT_SECONDS = 1800;

// Use environment variable for Python command, fallback to 'python' for local
define('PYTHON_COMMAND', getenv('PYTHON_COMMAND') ?: 'python');
define('PYTHON_SCRIPT', __DIR__ . '/../python/generator.py');
const SEARCH_LIMIT = 100000;

// Auto-detect the app's URL base path so links and assets work both at a
// domain root (Render.com) and inside a subfolder (XAMPP htdocs).
$documentRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
$appRoot = realpath(__DIR__ . '/..');
$detectedBase = '';
if ($documentRoot && $appRoot && str_starts_with($appRoot, $documentRoot)) {
    $detectedBase = str_replace('\\', '/', substr($appRoot, strlen($documentRoot)));
}
define('BASE_PATH', rtrim($detectedBase, '/'));
