<?php
declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function post_string(string $key, int $maxLength = 255): string
{
    $value = trim((string)($_POST[$key] ?? ''));
    return function_exists('mb_substr') ? mb_substr($value, 0, $maxLength) : substr($value, 0, $maxLength);
}

function post_int(string $key, ?int $default = null): ?int
{
    if (!array_key_exists($key, $_POST) || $_POST[$key] === '') {
        return $default;
    }
    return filter_var($_POST[$key], FILTER_VALIDATE_INT);
}

function query_int(string $key, ?int $default = null): ?int
{
    if (!array_key_exists($key, $_GET) || $_GET[$key] === '') {
        return $default;
    }
    return filter_var($_GET[$key], FILTER_VALIDATE_INT);
}

function option_list(array $rows, $selected = null): string
{
    $html = '';
    foreach ($rows as $row) {
        $value = (string)($row['id'] ?? '');
        $label = (string)($row['label'] ?? '');
        $state = ((string)$selected === $value) ? ' selected' : '';
        $html .= '<option value="' . e($value) . '"' . $state . '>' . e($label) . '</option>';
    }
    return $html;
}

function select_options(PDO $db, string $sql, array $params = [], $selected = null): string
{
    $statement = $db->prepare($sql);
    $statement->execute($params);
    return option_list($statement->fetchAll(), $selected);
}

function redirect_with_flash(string $url, string $message, string $type = 'success'): void
{
    flash($message, $type);
    header('Location: ' . $url);
    exit;
}

function is_ajax(): bool
{
    return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
}

function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function weekday_options(PDO $db, int $academicYearId): array
{
    $statement = $db->prepare('SELECT id, day_name AS label FROM working_days WHERE academic_year_id = ? ORDER BY day_index');
    $statement->execute([$academicYearId]);
    return $statement->fetchAll();
}

function slot_options(PDO $db, int $academicYearId): array
{
    $statement = $db->prepare('SELECT id, label, start_time, end_time, is_break FROM time_slots WHERE academic_year_id = ? ORDER BY slot_order');
    $statement->execute([$academicYearId]);
    $rows = $statement->fetchAll();
    foreach ($rows as &$row) {
        $row['label'] = $row['label'] . ' (' . date('h:i A', strtotime($row['start_time'])) . ' - ' . date('h:i A', strtotime($row['end_time'])) . ')';
    }
    return $rows;
}


function fetch_one(PDO $db, string $sql, array $params = []): ?array
{
    $statement = $db->prepare($sql);
    $statement->execute($params);
    $row = $statement->fetch();
    return $row ?: null;
}

function fetch_all(PDO $db, string $sql, array $params = []): array
{
    $statement = $db->prepare($sql);
    $statement->execute($params);
    return $statement->fetchAll();
}
