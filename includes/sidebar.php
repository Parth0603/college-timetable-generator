<?php $current = basename($_SERVER['SCRIPT_NAME']); ?>
<aside class="sidebar d-flex flex-column">
    <div class="brand">
        <div class="brand-mark">TT</div>
        <div>
            <div class="fw-semibold">Timetable</div>
            <div class="text-white-50 small">Generator</div>
        </div>
    </div>
    <nav class="nav flex-column sidebar-nav">
        <a class="nav-link <?= $current === 'dashboard.php' ? 'active' : '' ?>" href="<?= url('dashboard.php') ?>"><span>Dashboard</span></a>
        <a class="nav-link <?= $current === 'index.php' ? 'active' : '' ?>" href="<?= url('timetable/generate.php') ?>"><span>Generate</span></a>
        <a class="nav-link <?= str_contains($_SERVER['PHP_SELF'], 'timetable/view.php') ? 'active' : '' ?>" href="<?= url('timetable/view.php') ?>"><span>View</span></a>
        <a class="nav-link <?= str_contains($_SERVER['PHP_SELF'], 'timetable/history.php') ? 'active' : '' ?>" href="<?= url('timetable/history.php') ?>"><span>History</span></a>
        <div class="sidebar-heading">Academic Setup</div>
        <a class="nav-link <?= str_contains($_SERVER['PHP_SELF'], 'modules/academic-years/') ? 'active' : '' ?>" href="<?= url('modules/academic-years/index.php') ?>">Academic Years</a>
        <a class="nav-link <?= str_contains($_SERVER['PHP_SELF'], 'modules/departments/') ? 'active' : '' ?>" href="<?= url('modules/departments/index.php') ?>">Departments</a>
        <a class="nav-link <?= str_contains($_SERVER['PHP_SELF'], 'modules/semesters/') ? 'active' : '' ?>" href="<?= url('modules/semesters/index.php') ?>">Semesters</a>
        <a class="nav-link <?= str_contains($_SERVER['PHP_SELF'], 'modules/sections/') ? 'active' : '' ?>" href="<?= url('modules/sections/index.php') ?>">Sections</a>
        <div class="sidebar-heading">Resources</div>
        <a class="nav-link <?= str_contains($_SERVER['PHP_SELF'], 'modules/teachers/') ? 'active' : '' ?>" href="<?= url('modules/teachers/index.php') ?>">Teachers</a>
        <a class="nav-link <?= str_contains($_SERVER['PHP_SELF'], 'modules/subjects/') ? 'active' : '' ?>" href="<?= url('modules/subjects/index.php') ?>">Subjects</a>
        <a class="nav-link <?= str_contains($_SERVER['PHP_SELF'], 'modules/teacher-subjects/') ? 'active' : '' ?>" href="<?= url('modules/teacher-subjects/index.php') ?>">Teacher Subjects</a>
        <a class="nav-link <?= str_contains($_SERVER['PHP_SELF'], 'modules/classrooms/') ? 'active' : '' ?>" href="<?= url('modules/classrooms/index.php') ?>">Classrooms</a>
        <div class="sidebar-heading">Schedule Rules</div>
        <a class="nav-link <?= str_contains($_SERVER['PHP_SELF'], 'modules/working-days/') ? 'active' : '' ?>" href="<?= url('modules/working-days/index.php') ?>">Working Days</a>
        <a class="nav-link <?= str_contains($_SERVER['PHP_SELF'], 'modules/time-slots/') ? 'active' : '' ?>" href="<?= url('modules/time-slots/index.php') ?>">Time Slots</a>
        <a class="nav-link <?= str_contains($_SERVER['PHP_SELF'], 'modules/availability/') ? 'active' : '' ?>" href="<?= url('modules/availability/index.php') ?>">Availability</a>
        <a class="nav-link <?= str_contains($_SERVER['PHP_SELF'], 'modules/requirements/') ? 'active' : '' ?>" href="<?= url('modules/requirements/index.php') ?>">Requirements</a>
    </nav>
</aside>
