<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
login_required();

$db = db();
$years = $db->query('SELECT id, name FROM academic_years ORDER BY is_active DESC, start_date DESC')->fetchAll();
$departments = $db->query('SELECT id, name FROM departments ORDER BY name')->fetchAll();
$semesters = $db->query('SELECT s.id, s.name, s.academic_year_id, s.department_id FROM semesters s ORDER BY s.semester_number')->fetchAll();
$pageTitle = 'Generate Timetable';
require __DIR__ . '/../includes/header.php';
?>
<div class="row g-4">
    <div class="col-xl-5">
        <div class="card"><div class="card-body">
            <h2 class="h6 text-muted text-uppercase">Automatic scheduling</h2>
            <p class="text-muted small">The generator schedules every section in the selected semester together. It uses Python backtracking with the Minimum Remaining Values heuristic and enforces all hard constraints.</p>
            <form id="generate-form">
                <div class="mb-3"><label class="form-label" for="academic_year_id">Academic year</label>
                    <select class="form-select" id="academic_year_id" name="academic_year_id" required>
                        <?php foreach ($years as $year): ?><option value="<?= (int)$year['id'] ?>"><?= e($year['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3"><label class="form-label" for="department_id">Department</label>
                    <select class="form-select" id="department_id" name="department_id" required>
                        <?php foreach ($departments as $department): ?><option value="<?= (int)$department['id'] ?>"><?= e($department['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3"><label class="form-label" for="semester_id">Semester</label>
                    <select class="form-select" id="semester_id" name="semester_id" required>
                        <?php foreach ($semesters as $semester): ?><option value="<?= (int)$semester['id'] ?>" data-year="<?= (int)$semester['academic_year_id'] ?>" data-department="<?= (int)$semester['department_id'] ?>"><?= e($semester['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <button class="btn btn-primary w-100" id="generate-button" type="submit"><span class="spinner-border spinner-border-sm d-none me-2"></span>Generate timetable</button>
            </form>
            <div id="generate-result" class="mt-3"></div>
        </div></div>
    </div>
    <div class="col-xl-7">
        <div class="card"><div class="card-header"><strong>Generation checklist</strong></div><div class="card-body">
            <ul class="list-unstyled small text-muted mb-0">
                <li class="mb-2"><strong>Scope:</strong> all sections in the selected department, academic year, and semester.</li>
                <li class="mb-2"><strong>Teacher safety:</strong> availability, subject eligibility, daily and weekly workload.</li>
                <li class="mb-2"><strong>Room safety:</strong> capacity, laboratory use, and no double booking.</li>
                <li class="mb-2"><strong>Section safety:</strong> one lecture at a time and practical periods are consecutive.</li>
                <li><strong>Result:</strong> a new DRAFT version; existing versions are never overwritten.</li>
            </ul>
        </div></div>
    </div>
</div>
<script>
$(function () {
  $('#generate-form').on('submit', function (event) {
    event.preventDefault();
    const button = $('#generate-button');
    const result = $('#generate-result');
    button.prop('disabled', true).find('.spinner-border').removeClass('d-none');
    result.html('<div class="alert alert-info mb-0">Running the Python backtracking scheduler?</div>');
    $.ajax({
      url: '../api/timetable.php',
      method: 'POST',
      contentType: 'application/json',
      headers: {'X-CSRF-Token': $('meta[name="csrf-token"]').attr('content')},
      data: JSON.stringify({
        action: 'generate',
        academic_year_id: Number($('#academic_year_id').val()),
        department_id: Number($('#department_id').val()),
        semester_id: Number($('#semester_id').val())
      })
    }).done(function (response) {
      if (response.success) {
        result.html('<div class="alert alert-success mb-0">Generated successfully. Quality score: ' + response.quality_score + '</div><a class="btn btn-primary mt-3" href="timetable/view.php?timetable_id=' + response.timetable_id + '">Open timetable</a>');
      } else {
        result.html('<div class="alert alert-danger mb-0">' + $('<div>').text(response.message).html() + '</div>');
      }
    }).fail(function () {
      result.html('<div class="alert alert-danger mb-0">Request failed. Check the PHP and Python configuration.</div>');
    }).always(function () {
      button.prop('disabled', false).find('.spinner-border').addClass('d-none');
    });
  });
});
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
