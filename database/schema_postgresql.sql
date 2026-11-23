-- College Timetable Generator - PostgreSQL Schema
-- For Render.com deployment

CREATE TABLE admins (
  id SERIAL PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  username VARCHAR(60) NOT NULL UNIQUE,
  email VARCHAR(160) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE academic_years (
  id SERIAL PRIMARY KEY,
  name VARCHAR(60) NOT NULL UNIQUE,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  is_active SMALLINT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT chk_academic_dates CHECK (start_date < end_date)
);
CREATE INDEX idx_academic_active ON academic_years(is_active);

CREATE TABLE departments (
  id SERIAL PRIMARY KEY,
  code VARCHAR(20) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL UNIQUE,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE semesters (
  id SERIAL PRIMARY KEY,
  academic_year_id INTEGER NOT NULL,
  department_id INTEGER NOT NULL,
  semester_number SMALLINT NOT NULL,
  name VARCHAR(100) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE(academic_year_id, department_id, semester_number),
  CONSTRAINT fk_semesters_year FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_semesters_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT chk_semester_number CHECK (semester_number BETWEEN 1 AND 12)
);

CREATE TABLE sections (
  id SERIAL PRIMARY KEY,
  semester_id INTEGER NOT NULL,
  name VARCHAR(60) NOT NULL,
  student_count SMALLINT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE(semester_id, name),
  CONSTRAINT fk_sections_semester FOREIGN KEY (semester_id) REFERENCES semesters(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT chk_section_students CHECK (student_count > 0)
);

CREATE TABLE teachers (
  id SERIAL PRIMARY KEY,
  department_id INTEGER NOT NULL,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(160) NOT NULL UNIQUE,
  phone VARCHAR(20),
  short_code VARCHAR(12) NOT NULL UNIQUE,
  max_weekly_load SMALLINT NOT NULL DEFAULT 20,
  max_daily_load SMALLINT NOT NULL DEFAULT 5,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_teachers_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT chk_teacher_loads CHECK (max_weekly_load > 0 AND max_daily_load > 0 AND max_daily_load <= max_weekly_load)
);
CREATE INDEX idx_teachers_department ON teachers(department_id);

CREATE TABLE subjects (
  id SERIAL PRIMARY KEY,
  department_id INTEGER NOT NULL,
  semester_id INTEGER NOT NULL,
  code VARCHAR(20) NOT NULL UNIQUE,
  name VARCHAR(160) NOT NULL,
  is_practical SMALLINT NOT NULL DEFAULT 0,
  default_weekly_lectures SMALLINT NOT NULL DEFAULT 3,
  periods_per_session SMALLINT NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_subjects_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_subjects_semester FOREIGN KEY (semester_id) REFERENCES semesters(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT chk_subject_counts CHECK (default_weekly_lectures > 0 AND periods_per_session > 0 AND periods_per_session <= 4)
);
CREATE INDEX idx_subjects_semester ON subjects(semester_id);

CREATE TABLE teacher_subjects (
  id SERIAL PRIMARY KEY,
  teacher_id INTEGER NOT NULL,
  subject_id INTEGER NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE(teacher_id, subject_id),
  CONSTRAINT fk_ts_teacher FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_ts_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE ON UPDATE CASCADE
);
CREATE INDEX idx_teacher_subject_subject ON teacher_subjects(subject_id);

CREATE TABLE classrooms (
  id SERIAL PRIMARY KEY,
  department_id INTEGER,
  name VARCHAR(80) NOT NULL UNIQUE,
  capacity SMALLINT NOT NULL,
  room_type VARCHAR(20) NOT NULL DEFAULT 'CLASSROOM' CHECK (room_type IN ('CLASSROOM', 'LABORATORY')),
  is_active SMALLINT NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_classrooms_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT chk_classroom_capacity CHECK (capacity > 0)
);
CREATE INDEX idx_classrooms_type_capacity ON classrooms(room_type, capacity, is_active);

CREATE TABLE working_days (
  id SERIAL PRIMARY KEY,
  academic_year_id INTEGER NOT NULL,
  day_index SMALLINT NOT NULL,
  day_name VARCHAR(20) NOT NULL,
  UNIQUE(academic_year_id, day_index),
  CONSTRAINT fk_working_days_year FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE CASCADE ON UPDATE CASCADE
);
CREATE INDEX idx_working_days_year ON working_days(academic_year_id);

CREATE TABLE time_slots (
  id SERIAL PRIMARY KEY,
  academic_year_id INTEGER NOT NULL,
  slot_order SMALLINT NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  is_break SMALLINT NOT NULL DEFAULT 0,
  label VARCHAR(60),
  UNIQUE(academic_year_id, slot_order),
  CONSTRAINT fk_time_slots_year FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT chk_time_slot_times CHECK (start_time < end_time)
);
CREATE INDEX idx_time_slots_year ON time_slots(academic_year_id);

CREATE TABLE teacher_availability (
  id SERIAL PRIMARY KEY,
  teacher_id INTEGER NOT NULL,
  working_day_id INTEGER NOT NULL,
  time_slot_id INTEGER NOT NULL,
  is_available SMALLINT NOT NULL DEFAULT 1,
  UNIQUE(teacher_id, working_day_id, time_slot_id),
  CONSTRAINT fk_availability_teacher FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_availability_day FOREIGN KEY (working_day_id) REFERENCES working_days(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_availability_slot FOREIGN KEY (time_slot_id) REFERENCES time_slots(id) ON DELETE CASCADE ON UPDATE CASCADE
);
CREATE INDEX idx_availability_slot ON teacher_availability(working_day_id, time_slot_id, is_available);

CREATE TABLE subject_requirements (
  id SERIAL PRIMARY KEY,
  section_id INTEGER NOT NULL,
  subject_id INTEGER NOT NULL,
  weekly_lectures SMALLINT NOT NULL,
  periods_per_session SMALLINT NOT NULL DEFAULT 1,
  room_type VARCHAR(20) NOT NULL DEFAULT 'CLASSROOM' CHECK (room_type IN ('ANY', 'CLASSROOM', 'LABORATORY')),
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE(section_id, subject_id),
  CONSTRAINT fk_requirements_section FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_requirements_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT chk_requirement_counts CHECK (weekly_lectures > 0 AND periods_per_session > 0 AND periods_per_session <= 4)
);
CREATE INDEX idx_requirements_section ON subject_requirements(section_id);

CREATE TABLE timetables (
  id SERIAL PRIMARY KEY,
  academic_year_id INTEGER NOT NULL,
  department_id INTEGER NOT NULL,
  semester_id INTEGER NOT NULL,
  version_number INTEGER NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'DRAFT' CHECK (status IN ('DRAFT', 'PUBLISHED', 'ARCHIVED')),
  algorithm VARCHAR(60) NOT NULL DEFAULT 'Backtracking + MRV',
  quality_score INTEGER NOT NULL DEFAULT 0,
  notes TEXT,
  generated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  published_at TIMESTAMP,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE(academic_year_id, department_id, semester_id, version_number),
  CONSTRAINT fk_timetables_year FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_timetables_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_timetables_semester FOREIGN KEY (semester_id) REFERENCES semesters(id) ON DELETE RESTRICT ON UPDATE CASCADE
);
CREATE INDEX idx_timetables_scope ON timetables(academic_year_id, department_id, semester_id, status);

CREATE TABLE timetable_entries (
  id SERIAL PRIMARY KEY,
  timetable_id INTEGER NOT NULL,
  requirement_id INTEGER NOT NULL,
  section_id INTEGER NOT NULL,
  subject_id INTEGER NOT NULL,
  teacher_id INTEGER NOT NULL,
  classroom_id INTEGER NOT NULL,
  entry_type VARCHAR(10) NOT NULL CHECK (entry_type IN ('THEORY', 'PRACTICAL')),
  periods_count SMALLINT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_entries_timetable FOREIGN KEY (timetable_id) REFERENCES timetables(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_entries_requirement FOREIGN KEY (requirement_id) REFERENCES subject_requirements(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_entries_section FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_entries_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_entries_teacher FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_entries_classroom FOREIGN KEY (classroom_id) REFERENCES classrooms(id) ON DELETE RESTRICT ON UPDATE CASCADE
);
CREATE INDEX idx_entries_timetable ON timetable_entries(timetable_id);
CREATE INDEX idx_entries_teacher ON timetable_entries(teacher_id);
CREATE INDEX idx_entries_classroom ON timetable_entries(classroom_id);

CREATE TABLE timetable_entry_slots (
  id SERIAL PRIMARY KEY,
  timetable_entry_id INTEGER NOT NULL,
  timetable_id INTEGER NOT NULL,
  requirement_id INTEGER NOT NULL,
  section_id INTEGER NOT NULL,
  teacher_id INTEGER NOT NULL,
  classroom_id INTEGER NOT NULL,
  working_day_id INTEGER NOT NULL,
  time_slot_id INTEGER NOT NULL,
  slot_sequence SMALLINT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE(timetable_entry_id, time_slot_id),
  UNIQUE(timetable_id, teacher_id, working_day_id, time_slot_id),
  UNIQUE(timetable_id, classroom_id, working_day_id, time_slot_id),
  UNIQUE(timetable_id, section_id, working_day_id, time_slot_id),
  CONSTRAINT fk_entry_slots_entry FOREIGN KEY (timetable_entry_id) REFERENCES timetable_entries(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_entry_slots_timetable FOREIGN KEY (timetable_id) REFERENCES timetables(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_entry_slots_requirement FOREIGN KEY (requirement_id) REFERENCES subject_requirements(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_entry_slots_section FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_entry_slots_teacher FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_entry_slots_classroom FOREIGN KEY (classroom_id) REFERENCES classrooms(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_entry_slots_day FOREIGN KEY (working_day_id) REFERENCES working_days(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_entry_slots_slot FOREIGN KEY (time_slot_id) REFERENCES time_slots(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT chk_slot_sequence CHECK (slot_sequence > 0)
);
CREATE INDEX idx_entry_slots_grid ON timetable_entry_slots(timetable_id, working_day_id, time_slot_id);
CREATE INDEX idx_entry_slots_teacher ON timetable_entry_slots(timetable_id, teacher_id);
CREATE INDEX idx_entry_slots_classroom ON timetable_entry_slots(timetable_id, classroom_id);
