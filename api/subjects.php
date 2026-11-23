<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
login_required();
$semesterId = query_int('semester_id', 0);
if ($semesterId) {
    $statement = db()->prepare('SELECT id, code, name FROM subjects WHERE semester_id=? ORDER BY code');
    $statement->execute([$semesterId]);
    json_response(['success' => true, 'subjects' => $statement->fetchAll()]);
}
json_response(['success' => true, 'subjects' => db()->query('SELECT id, code, name FROM subjects ORDER BY code')->fetchAll()]);
