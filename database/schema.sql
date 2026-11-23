-- College Timetable Generator database schema
-- MySQL 8+, InnoDB, utf8mb4

CREATE DATABASE IF NOT EXISTS timetable_generator
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE timetable_generator;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS timetable_entry_slots;
DROP TABLE IF EXISTS timetable_entries;
DROP TABLE IF EXISTS timetables;
DROP TABLE IF EXISTS subject_requirements;
DROP TABLE IF EXISTS teacher_availability;
DROP TABLE IF EXISTS time_slots;
DROP TABLE IF EXISTS working_days;
DROP TABLE IF EXISTS classrooms;
DROP TABLE IF EXISTS teacher_subjects;
DROP TABLE IF EXISTS subjects;
DROP TABLE IF EXISTS teachers;
DROP TABLE IF EXISTS sections;
DROP TABLE IF EXISTS semesters;
DROP TABLE IF EXISTS departments;
DROP TABLE IF EXISTS academic_years;
DROP TABLE IF EXISTS admins;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE admins (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  username VARCHAR(60) NOT NULL UNIQUE,
  email VARCHAR(160) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE academic_years (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(60) NOT NULL UNIQUE,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT chk_academic_dates CHECK (start_date < end_date),
  INDEX idx_academic_active (is_active)
) ENGINE=InnoDB;

CREATE TABLE departments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL UNIQUE,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE semesters (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  academic_year_id INT UNSIGNED NOT NULL,
  department_id INT UNSIGNED NOT NULL,
  semester_number TINYINT UNSIGNED NOT NULL,
  name VARCHAR(100) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE ux_semester_scope (academic_year_id, department_id, semester_number),
  CONSTRAINT fk_semesters_year FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_semesters_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT chk_semester_number CHECK (semester_number BETWEEN 1 AND 12)
) ENGINE=InnoDB;

CREATE TABLE sections (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  semester_id INT UNSIGNED NOT NULL,
  name VARCHAR(60) NOT NULL,
  student_count SMALLINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE ux_section_semester_name (semester_id, name),
  CONSTRAINT fk_sections_semester FOREIGN KEY (semester_id) REFERENCES semesters(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT chk_section_students CHECK (student_count > 0)
) ENGINE=InnoDB;

CREATE TABLE teachers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  department_id INT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(160) NOT NULL UNIQUE,
  phone VARCHAR(20) NULL,
  short_code VARCHAR(12) NOT NULL UNIQUE,
  max_weekly_load TINYINT UNSIGNED NOT NULL DEFAULT 20,
  max_daily_load TINYINT UNSIGNED NOT NULL DEFAULT 5,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_teachers_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  INDEX idx_teachers_department (department_id),
  CONSTRAINT chk_teacher_loads CHECK (max_weekly_load > 0 AND max_daily_load > 0 AND max_daily_load <= max_weekly_load)
) ENGINE=InnoDB;

CREATE TABLE subjects (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  department_id INT UNSIGNED NOT NULL,
  semester_id INT UNSIGNED NOT NULL,
  code VARCHAR(20) NOT NULL UNIQUE,
  name VARCHAR(160) NOT NULL,
  is_practical TINYINT(1) NOT NULL DEFAULT 0,
  default_weekly_lectures TINYINT UNSIGNED NOT NULL DEFAULT 3,
  periods_per_session TINYINT UNSIGNED NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_subjects_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_subjects_semester FOREIGN KEY (semester_id) REFERENCES semesters(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  INDEX idx_subjects_semester (semester_id),
  CONSTRAINT chk_subject_counts CHECK (default_weekly_lectures > 0 AND periods_per_session > 0 AND periods_per_session <= 4)
) ENGINE=InnoDB;

CREATE TABLE teacher_subjects (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  teacher_id INT UNSIGNED NOT NULL,
  subject_id INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE ux_teacher_subject (teacher_id, subject_id),
  CONSTRAINT fk_ts_teacher FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_ts_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX idx_teacher_subject_subject (subject_id)
) ENGINE=InnoDB;

CREATE TABLE classrooms (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  department_id INT UNSIGNED NULL,
  name VARCHAR(80) NOT NULL UNIQUE,
  capacity SMALLINT UNSIGNED NOT NULL,
  room_type ENUM('CLASSROOM','LABORATORY') NOT NULL DEFAULT 'CLASSROOM',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_classrooms_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL ON UPDATE CASCADE,
  INDEX idx_classrooms_type_capacity (room_type, capacity, is_active),
  CONSTRAINT chk_classroom_capacity CHECK (capacity > 0)
) ENGINE=InnoDB;

CREATE TABLE working_days (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  academic_year_id INT UNSIGNED NOT NULL,
  day_index TINYINT UNSIGNED NOT NULL,
  day_name VARCHAR(20) NOT NULL,
  UNIQUE ux_working_day (academic_year_id, day_index),
  CONSTRAINT fk_working_days_year FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX idx_working_days_year (academic_year_id)
) ENGINE=InnoDB;

CREATE TABLE time_slots (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  academic_year_id INT UNSIGNED NOT NULL,
  slot_order TINYINT UNSIGNED NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  is_break TINYINT(1) NOT NULL DEFAULT 0,
  label VARCHAR(60) NULL,
  UNIQUE ux_time_slot (academic_year_id, slot_order),
  CONSTRAINT fk_time_slots_year FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX idx_time_slots_year (academic_year_id),
  CONSTRAINT chk_time_slot_times CHECK (start_time < end_time)
) ENGINE=InnoDB;

CREATE TABLE teacher_availability (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  teacher_id INT UNSIGNED NOT NULL,
  working_day_id INT UNSIGNED NOT NULL,
  time_slot_id INT UNSIGNED NOT NULL,
  is_available TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE ux_teacher_slot (teacher_id, working_day_id, time_slot_id),
  CONSTRAINT fk_availability_teacher FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_availability_day FOREIGN KEY (working_day_id) REFERENCES working_days(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_availability_slot FOREIGN KEY (time_slot_id) REFERENCES time_slots(id) ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX idx_availability_slot (working_day_id, time_slot_id, is_available)
) ENGINE=InnoDB;

CREATE TABLE subject_requirements (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  section_id INT UNSIGNED NOT NULL,
  subject_id INT UNSIGNED NOT NULL,
  weekly_lectures TINYINT UNSIGNED NOT NULL,
  periods_per_session TINYINT UNSIGNED NOT NULL DEFAULT 1,
  room_type ENUM('ANY','CLASSROOM','LABORATORY') NOT NULL DEFAULT 'CLASSROOM',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE ux_requirement_section_subject (section_id, subject_id),
  CONSTRAINT fk_requirements_section FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_requirements_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  INDEX idx_requirements_section (section_id),
  CONSTRAINT chk_requirement_counts CHECK (weekly_lectures > 0 AND periods_per_session > 0 AND periods_per_session <= 4)
) ENGINE=InnoDB;

CREATE TABLE timetables (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  academic_year_id INT UNSIGNED NOT NULL,
  department_id INT UNSIGNED NOT NULL,
  semester_id INT UNSIGNED NOT NULL,
  version_number INT UNSIGNED NOT NULL,
  status ENUM('DRAFT','PUBLISHED','ARCHIVED') NOT NULL DEFAULT 'DRAFT',
  algorithm VARCHAR(60) NOT NULL DEFAULT 'Backtracking + MRV',
  quality_score INT NOT NULL DEFAULT 0,
  notes TEXT NULL,
  generated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  published_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE ux_timetable_version (academic_year_id, department_id, semester_id, version_number),
  CONSTRAINT fk_timetables_year FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_timetables_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_timetables_semester FOREIGN KEY (semester_id) REFERENCES semesters(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  INDEX idx_timetables_scope (academic_year_id, department_id, semester_id, status)
) ENGINE=InnoDB;

CREATE TABLE timetable_entries (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  timetable_id INT UNSIGNED NOT NULL,
  requirement_id INT UNSIGNED NOT NULL,
  section_id INT UNSIGNED NOT NULL,
  subject_id INT UNSIGNED NOT NULL,
  teacher_id INT UNSIGNED NOT NULL,
  classroom_id INT UNSIGNED NOT NULL,
  entry_type ENUM('THEORY','PRACTICAL') NOT NULL,
  periods_count TINYINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_entries_timetable FOREIGN KEY (timetable_id) REFERENCES timetables(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_entries_requirement FOREIGN KEY (requirement_id) REFERENCES subject_requirements(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_entries_section FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_entries_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_entries_teacher FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_entries_classroom FOREIGN KEY (classroom_id) REFERENCES classrooms(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  INDEX idx_entries_timetable (timetable_id),
  INDEX idx_entries_teacher (teacher_id),
  INDEX idx_entries_classroom (classroom_id)
) ENGINE=InnoDB;

CREATE TABLE timetable_entry_slots (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  timetable_entry_id INT UNSIGNED NOT NULL,
  timetable_id INT UNSIGNED NOT NULL,
  requirement_id INT UNSIGNED NOT NULL,
  section_id INT UNSIGNED NOT NULL,
  teacher_id INT UNSIGNED NOT NULL,
  classroom_id INT UNSIGNED NOT NULL,
  working_day_id INT UNSIGNED NOT NULL,
  time_slot_id INT UNSIGNED NOT NULL,
  slot_sequence TINYINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE ux_entry_slot (timetable_entry_id, time_slot_id),
  UNIQUE ux_slot_teacher (timetable_id, teacher_id, working_day_id, time_slot_id),
  UNIQUE ux_slot_classroom (timetable_id, classroom_id, working_day_id, time_slot_id),
  UNIQUE ux_slot_section (timetable_id, section_id, working_day_id, time_slot_id),
  CONSTRAINT fk_entry_slots_entry FOREIGN KEY (timetable_entry_id) REFERENCES timetable_entries(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_entry_slots_timetable FOREIGN KEY (timetable_id) REFERENCES timetables(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_entry_slots_requirement FOREIGN KEY (requirement_id) REFERENCES subject_requirements(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_entry_slots_section FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_entry_slots_teacher FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_entry_slots_classroom FOREIGN KEY (classroom_id) REFERENCES classrooms(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_entry_slots_day FOREIGN KEY (working_day_id) REFERENCES working_days(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_entry_slots_slot FOREIGN KEY (time_slot_id) REFERENCES time_slots(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  INDEX idx_entry_slots_grid (timetable_id, working_day_id, time_slot_id),
  INDEX idx_entry_slots_teacher (timetable_id, teacher_id),
  INDEX idx_entry_slots_classroom (timetable_id, classroom_id),
  CONSTRAINT chk_slot_sequence CHECK (slot_sequence > 0)
) ENGINE=InnoDB;
