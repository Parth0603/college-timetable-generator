<?php
declare(strict_types=1);

const APP_NAME = 'College Timetable Generator';
const COLLEGE_NAME = 'ABC Institute of Engineering and Technology';
const SESSION_TIMEOUT_SECONDS = 1800;

// Use environment variable for Python command, fallback to 'python' for local
define('PYTHON_COMMAND', getenv('PYTHON_COMMAND') ?: 'python');
define('PYTHON_SCRIPT', __DIR__ . '/../python/generator.py');
const SEARCH_LIMIT = 100000;
