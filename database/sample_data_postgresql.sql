-- College Timetable Generator - PostgreSQL Sample Data

INSERT INTO admins (name, username, email, password_hash) VALUES
('College Administrator', 'admin', 'admin@college.edu', '$2y$10$jjWucIlEHZtWoJted2JyaOwKJxQhUZDlQPU.Z2M/H3fJpL06AKlHa');

INSERT INTO academic_years (name, start_date, end_date, is_active) VALUES
('2026-2027', '2026-07-01', '2027-06-30', 1);

INSERT INTO departments (code, name) VALUES
('CSE', 'Computer Science and Engineering');

INSERT INTO semesters (academic_year_id, department_id, semester_number, name) VALUES
(1, 1, 3, 'CSE Semester 3');

INSERT INTO sections (semester_id, name, student_count) VALUES
(1, 'CSE-A', 60),
(1, 'CSE-B', 58);

INSERT INTO teachers (department_id, name, email, phone, short_code, max_weekly_load, max_daily_load) VALUES
(1, 'Dr. Anjali Mehta', 'anjali.mehta@college.edu', '9810011111', 'AM', 12, 4),
(1, 'Prof. Rohit Sharma', 'rohit.sharma@college.edu', '9810022222', 'RS', 12, 4),
(1, 'Dr. Kavita Nair', 'kavita.nair@college.edu', '9810033333', 'KN', 12, 4),
(1, 'Prof. Amit Verma', 'amit.verma@college.edu', '9810044444', 'AV', 12, 4),
(1, 'Dr. Sneha Rao', 'sneha.rao@college.edu', '9810055555', 'SR', 12, 4),
(1, 'Prof. Vikram Singh', 'vikram.singh@college.edu', '9810066666', 'VS', 12, 4);

INSERT INTO subjects (department_id, semester_id, code, name, is_practical, default_weekly_lectures, periods_per_session) VALUES
(1, 1, 'CS301', 'Database Management Systems', 0, 3, 1),
(1, 1, 'CS302', 'Data Structures', 0, 3, 1),
(1, 1, 'CS303', 'Operating Systems', 0, 3, 1),
(1, 1, 'CS304', 'Computer Networks', 0, 3, 1),
(1, 1, 'CS305', 'Discrete Mathematics', 0, 3, 1),
(1, 1, 'CS306', 'DBMS Laboratory', 1, 1, 2),
(1, 1, 'CS307', 'Data Structures Laboratory', 1, 1, 2),
(1, 1, 'CS308', 'Professional Communication', 0, 2, 1);

INSERT INTO teacher_subjects (teacher_id, subject_id) VALUES
(1,1),(1,6),(2,2),(2,7),(3,3),(4,4),(5,5),(6,8);

INSERT INTO classrooms (department_id, name, capacity, room_type, is_active) VALUES
(1, 'Room A-101', 70, 'CLASSROOM', 1),
(1, 'Room A-102', 70, 'CLASSROOM', 1),
(1, 'Room A-103', 65, 'CLASSROOM', 1),
(1, 'Room B-201', 80, 'CLASSROOM', 1),
(1, 'Room B-202', 75, 'CLASSROOM', 1),
(1, 'Programming Lab 1', 65, 'LABORATORY', 1),
(1, 'Programming Lab 2', 60, 'LABORATORY', 1);

INSERT INTO working_days (academic_year_id, day_index, day_name) VALUES
(1,1,'Monday'),(1,2,'Tuesday'),(1,3,'Wednesday'),(1,4,'Thursday'),(1,5,'Friday'),(1,6,'Saturday');

INSERT INTO time_slots (academic_year_id, slot_order, start_time, end_time, is_break, label) VALUES
(1,1,'09:00:00','10:00:00',0,'Period 1'),
(1,2,'10:00:00','11:00:00',0,'Period 2'),
(1,3,'11:00:00','12:00:00',0,'Period 3'),
(1,4,'12:00:00','13:00:00',1,'Lunch Break'),
(1,5,'13:00:00','14:00:00',0,'Period 4'),
(1,6,'14:00:00','15:00:00',0,'Period 5');

INSERT INTO subject_requirements (section_id, subject_id, weekly_lectures, periods_per_session, room_type) VALUES
(1,1,3,1,'CLASSROOM'),(1,2,3,1,'CLASSROOM'),(1,3,3,1,'CLASSROOM'),(1,4,3,1,'CLASSROOM'),(1,5,3,1,'CLASSROOM'),(1,6,1,2,'LABORATORY'),(1,7,1,2,'LABORATORY'),(1,8,2,1,'CLASSROOM'),
(2,1,3,1,'CLASSROOM'),(2,2,3,1,'CLASSROOM'),(2,3,3,1,'CLASSROOM'),(2,4,3,1,'CLASSROOM'),(2,5,3,1,'CLASSROOM'),(2,6,1,2,'LABORATORY'),(2,7,1,2,'LABORATORY'),(2,8,2,1,'CLASSROOM');

INSERT INTO teacher_availability (teacher_id, working_day_id, time_slot_id, is_available)
SELECT t.id, wd.id, ts.id, 1
FROM teachers t
CROSS JOIN working_days wd
CROSS JOIN time_slots ts
WHERE wd.academic_year_id = 1 AND ts.academic_year_id = 1;

UPDATE teacher_availability SET is_available = 0
WHERE (teacher_id = 1 AND working_day_id = 1 AND time_slot_id = 1)
   OR (teacher_id = 2 AND working_day_id = 2 AND time_slot_id = 2)
   OR (teacher_id = 3 AND working_day_id = 3 AND time_slot_id = 3)
   OR (teacher_id = 4 AND working_day_id = 4 AND time_slot_id = 5)
   OR (teacher_id = 5 AND working_day_id = 5 AND time_slot_id = 6)
   OR (teacher_id = 6 AND working_day_id = 6 AND time_slot_id = 1);
