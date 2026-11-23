<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
login_required();
$semesterId = query_int('semester_id', 0);
if ($semesterId) {
    $statement = db()->prepare('SELECT c.id, c.name, c.capacity, c.room_type FROM classrooms c WHERE c.is_active=1 AND (c.department_id=(SELECT department_id FROM semesters WHERE id=?) OR c.department_id IS NULL) ORDER BY c.room_type, c.name');
    $statement->execute([$semesterId]);
    json_response(['success' => true, 'classrooms' => $statement->fetchAll()]);
}
json_response(['success' => true, 'classrooms' => db()->query('SELECT id, name, capacity, room_type FROM classrooms WHERE is_active=1 ORDER BY name')->fetchAll()]);
