<?php
declare(strict_types=1);

/**
 * Small CRUD engine shared by every management module.
 * All SQL remains prepared and all output is escaped.
 */
function run_crud(array $config): void
{
    $db = db();
    $editing = null;
    $errors = [];

    if (query_int('edit') !== null) {
        $statement = $db->prepare('SELECT * FROM ' . $config['table'] . ' WHERE id = ?');
        $statement->execute([query_int('edit')]);
        $editing = $statement->fetch();
        if (!$editing) {
            redirect_with_flash($_SERVER['SCRIPT_NAME'], 'Record not found.', 'danger');
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        $action = post_string('action', 20);

        if ($action === 'delete') {
            $id = post_int('id', 0);
            $blockers = find_delete_blockers($db, $config, (int)$id);
            if ($blockers) {
                redirect_with_flash($_SERVER['SCRIPT_NAME'], 'Cannot delete because it is used by: ' . implode(', ', $blockers) . '.', 'danger');
            }
            $statement = $db->prepare('DELETE FROM ' . $config['table'] . ' WHERE id = ?');
            $statement->execute([$id]);
            redirect_with_flash($_SERVER['SCRIPT_NAME'], $config['singular'] . ' deleted successfully.');
        }

        $data = [];
        foreach ($config['fields'] as $field) {
            $data[$field['name']] = read_field($field);
        }

        $errors = $config['validate']($db, $data, $editing);
        if (!$errors) {
            try {
                save_crud_record($db, $config, $data, $editing);
                redirect_with_flash($_SERVER['SCRIPT_NAME'], $config['singular'] . ($editing ? ' updated successfully.' : ' added successfully.'));
            } catch (PDOException $exception) {
                $errors[] = friendly_sql_error($exception);
            }
        }
    }

    $page = max(1, query_int('page', 1));
    $search = trim((string)($_GET['search'] ?? ''));
    $where = [];
    $params = [];

    foreach ($config['filters'] ?? [] as $filter) {
        $value = query_int($filter['name']);
        if ($value !== null) {
            $where[] = $filter['column'] . ' = ?';
            $params[] = $value;
        }
    }

    if ($search !== '' && !empty($config['search_columns'])) {
        $parts = [];
        foreach ($config['search_columns'] as $column) {
            $parts[] = $column . ' LIKE ?';
            $params[] = '%' . $search . '%';
        }
        $where[] = '(' . implode(' OR ', $parts) . ')';
    }

    $sql = $config['list_sql'];
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY ' . $config['order'] . ' LIMIT 25 OFFSET ' . (($page - 1) * 25);
    $statement = $db->prepare($sql);
    $statement->execute($params);
    $rows = $statement->fetchAll();

    $countSql = $config['count_sql'];
    if ($where) {
        $countSql .= ' WHERE ' . implode(' AND ', $where);
    }
    $statement = $db->prepare($countSql);
    $statement->execute($params);
    $total = (int)$statement->fetchColumn();

    $pageTitle = $config['title'];
    require __DIR__ . '/../includes/header.php';
    render_crud_page($config, $rows, $editing, $errors, $page, $total, $search, $db);
    require __DIR__ . '/../includes/footer.php';
}

function read_field(array $field)
{
    $name = $field['name'];
    if ($field['type'] === 'checkbox') {
        return isset($_POST[$name]) ? 1 : 0;
    }
    if ($field['type'] === 'number') {
        return post_int($name, 0);
    }
    if ($field['type'] === 'date') {
        return post_string($name, 20) ?: null;
    }
    if ($field['type'] === 'time') {
        return post_string($name, 10) ?: null;
    }
    return post_string($name, (int)($field['max'] ?? 255));
}

function validate_fields(array $config, array $data): array
{
    $errors = [];
    foreach ($config['fields'] as $field) {
        $value = $data[$field['name']];
        if (($field['required'] ?? false) && ($value === null || $value === '')) {
            $errors[] = $field['label'] . ' is required.';
        }
        if ($field['type'] === 'number') {
            $min = (int)($field['min'] ?? 0);
            $max = (int)($field['max'] ?? 1000000);
            if ((int)$value < $min || (int)$value > $max) {
                $errors[] = $field['label'] . ' must be between ' . $min . ' and ' . $max . '.';
            }
        }
    }
    return $errors;
}

function save_crud_record(PDO $db, array $config, array $data, ?array $current): void
{
    $assignments = [];
    $params = [];
    foreach ($config['fields'] as $field) {
        $assignments[] = $field['name'] . ' = ?';
        $params[] = $data[$field['name']];
    }

    if ($current) {
        $sql = 'UPDATE ' . $config['table'] . ' SET ' . implode(', ', $assignments) . ' WHERE id = ?';
        $params[] = (int)$current['id'];
    } else {
        $columns = array_column($config['fields'], 'name');
        $sql = 'INSERT INTO ' . $config['table'] . ' (' . implode(', ', $columns) . ') VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')';
    }

    $statement = $db->prepare($sql);
    $statement->execute($params);
}

function find_delete_blockers(PDO $db, array $config, int $id): array
{
    $blockers = [];
    foreach ($config['delete_blockers'] ?? [] as $table => $info) {
        $statement = $db->prepare('SELECT COUNT(*) FROM ' . $table . ' WHERE ' . $info['column'] . ' = ?');
        $statement->execute([$id]);
        if ((int)$statement->fetchColumn() > 0) {
            $blockers[] = $info['label'];
        }
    }
    return $blockers;
}

function friendly_sql_error(PDOException $exception): string
{
    if ($exception->getCode() === '23000') {
        return 'The value is already used or linked to another record. Please use unique values.';
    }
    return 'Database operation failed. Please check the values and try again.';
}

function render_crud_page(array $config, array $rows, ?array $editing, array $errors, int $page, int $total, string $search, PDO $db): void
{
    $formTitle = $editing ? 'Edit ' . $config['singular'] : 'Add ' . $config['singular'];
    ?>
    <div class="row g-4">
        <div class="col-xl-4">
            <div class="card">
                <div class="card-header"><strong><?= e($formTitle) ?></strong></div>
                <div class="card-body">
                    <?php foreach ($errors as $error): ?><div class="alert alert-danger py-2"><?= e($error) ?></div><?php endforeach; ?>
                    <form method="post" novalidate>
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="save">
                        <?php foreach ($config['fields'] as $field): render_field($field, $editing, $db); endforeach; ?>
                        <div class="d-flex gap-2">
                            <button class="btn btn-primary" type="submit"><?= $editing ? 'Update' : 'Save' ?></button>
                            <?php if ($editing): ?><a class="btn btn-outline-secondary" href="<?= e($_SERVER['SCRIPT_NAME']) ?>">Cancel</a><?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-xl-8">
            <div class="card">
                <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
                    <strong><?= e($config['title']) ?></strong>
                    <form class="d-flex gap-2" method="get">
                        <?php foreach ($config['filters'] ?? [] as $filter): ?>
                            <select class="form-select form-select-sm" name="<?= e($filter['name']) ?>" onchange="this.form.submit()">
                                <option value="">All <?= e($filter['label']) ?></option>
                                <?= select_options($db, $filter['options'], [], query_int($filter['name'])) ?>
                            </select>
                        <?php endforeach; ?>
                        <input class="form-control form-control-sm" name="search" value="<?= e($search) ?>" placeholder="Search">
                        <button class="btn btn-sm btn-outline-primary">Search</button>
                    </form>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead><tr>
                            <?php foreach ($config['columns'] as $column): ?><th><?= e($column['label']) ?></th><?php endforeach; ?><th class="text-end">Actions</th>
                        </tr></thead>
                        <tbody>
                        <?php if (!$rows): ?><tr><td colspan="<?= count($config['columns']) + 1 ?>" class="text-center text-muted py-4">No records found.</td></tr><?php endif; ?>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <?php foreach ($config['columns'] as $column): ?>
                                    <td><?= $column['value']($row) ?></td>
                                <?php endforeach; ?>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-1">
                                        <a class="btn btn-sm btn-outline-primary" href="?edit=<?= (int)$row['id'] ?>">Edit</a>
                                        <form method="post" data-confirm="Delete this record?">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                            <button class="btn btn-sm btn-outline-danger">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($total > 25): ?>
                    <div class="card-footer text-muted small">Page <?= $page ?> of <?= (int)ceil($total / 25) ?> &middot; <?= $total ?> records</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}

function render_field(array $field, ?array $row, PDO $db): void
{
    $value = $row[$field['name']] ?? '';
    $type = $field['type'];
    ?>
    <div class="mb-3">
        <label class="form-label" for="<?= e($field['name']) ?>"><?= e($field['label']) ?></label>
        <?php if ($type === 'select'): ?>
            <select class="form-select" id="<?= e($field['name']) ?>" name="<?= e($field['name']) ?>" <?= ($field['required'] ?? false) ? 'required' : '' ?>>
                <option value="">Select <?= e($field['label']) ?></option>
                <?= select_options($db, $field['options'], [], $value) ?>
            </select>
        <?php elseif ($type === 'checkbox'): ?>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="<?= e($field['name']) ?>" name="<?= e($field['name']) ?>" value="1" <?= $value ? 'checked' : '' ?>>
                <label class="form-check-label" for="<?= e($field['name']) ?>">Enabled</label>
            </div>
        <?php else: ?>
            <input class="form-control" type="<?= $type === 'time' ? 'time' : $type ?>" id="<?= e($field['name']) ?>" name="<?= e($field['name']) ?>" value="<?= e((string)$value) ?>" <?= ($field['required'] ?? false) ? 'required' : '' ?>>
        <?php endif; ?>
        <?php if (!empty($field['help'])): ?><div class="form-text"><?= e($field['help']) ?></div><?php endif; ?>
    </div>
    <?php
}
