<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
login_required();
$semesterId = query_int('semester_id', 0);
if ($semesterId) {
    $statement = db()->prepare('SELECT DISTINCT t.id, t.name, t.short_code FROM teachers t JOIN teacher_subjects ts ON ts.teacher_id=t.id JOIN subjects sub ON sub.id=ts.subject_id WHERE sub.semester_id=? ORDER BY t.name');
    $statement->execute([$semesterId]);
    json_response(['success' => true, 'teachers' => $statement->fetchAll()]);
}
json_response(['success' => true, 'teachers' => db()->query('SELECT id, name, short_code FROM teachers ORDER BY name')->fetchAll()]);
