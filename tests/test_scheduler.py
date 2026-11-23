import json
import subprocess
import sys
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / "python"))

from scheduler import Scheduler


def make_input():
    days = [
        {"id": index, "day_index": index, "day_name": name}
        for index, name in enumerate(["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"], start=1)
    ]
    slots = []
    for day in days:
        for order in range(1, 7):
            slots.append({
                "id": (day["id"] - 1) * 10 + order,
                "day_id": day["id"],
                "slot_order": order,
                "is_break": 1 if order == 4 else 0,
            })

    subjects = []
    subject_data = [
        (1, "CS301", "Database Management Systems", 0),
        (2, "CS302", "Data Structures", 0),
        (3, "CS303", "Operating Systems", 0),
        (4, "CS304", "Computer Networks", 0),
        (5, "CS305", "Discrete Mathematics", 0),
        (6, "CS306", "DBMS Laboratory", 1),
        (7, "CS307", "Data Structures Laboratory", 1),
        (8, "CS308", "Professional Communication", 0),
    ]
    for subject_id, code, name, practical in subject_data:
        subjects.append({"id": subject_id, "code": code, "name": name, "is_practical": practical})

    teacher_data = [
        (1, "Dr. Anjali Mehta", [1, 6]),
        (2, "Prof. Rohit Sharma", [2, 7]),
        (3, "Dr. Kavita Nair", [3]),
        (4, "Prof. Amit Verma", [4]),
        (5, "Dr. Sneha Rao", [5]),
        (6, "Prof. Vikram Singh", [8]),
    ]
    teachers = []
    for teacher_id, name, subject_ids in teacher_data:
        availability = {}
        for day in days:
            for slot in slots:
                if slot["day_id"] == day["id"] and not (teacher_id == day["id"] and slot["slot_order"] == 1):
                    availability[f"{day['id']},{slot['id']}"] = True
        teachers.append({
            "id": teacher_id, "name": name, "short_code": name[-2:],
            "max_weekly_load": 12, "max_daily_load": 4,
            "subject_ids": subject_ids, "available_slots": availability,
        })

    rooms = [
        {"id": room, "name": f"Room {room}", "capacity": 70, "room_type": "CLASSROOM", "is_active": 1}
        for room in range(1, 6)
    ] + [
        {"id": 6, "name": "Lab 1", "capacity": 65, "room_type": "LABORATORY", "is_active": 1},
        {"id": 7, "name": "Lab 2", "capacity": 60, "room_type": "LABORATORY", "is_active": 1},
    ]
    sections = [{"id": 1, "name": "CSE-A", "student_count": 60}, {"id": 2, "name": "CSE-B", "student_count": 58}]

    requirements = []
    requirement_id = 0
    for section in sections:
        for subject in subjects:
            requirement_id += 1
            practical = subject["is_practical"] == 1
            requirements.append({
                "id": requirement_id,
                "section_id": section["id"],
                "subject_id": subject["id"],
                "weekly_lectures": 2 if subject["id"] == 8 else (1 if practical else 3),
                "periods_per_session": 2 if practical else 1,
                "room_type": "LABORATORY" if practical else "CLASSROOM",
            })

    return {
        "academic_year_id": 1, "department_id": 1, "semester_id": 1,
        "days": days, "slots": slots, "sections": sections, "subjects": subjects,
        "teachers": teachers, "rooms": rooms, "requirements": requirements,
    }


class SchedulerTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.data = make_input()
        cls.scheduler = Scheduler(cls.data)
        cls.success = cls.scheduler.solve()
        if not cls.success:
            raise AssertionError(cls.scheduler.failure_reason)
        cls.entries = cls.scheduler.schedule

    def test_all_required_sessions_scheduled(self):
        expected = sum(int(requirement["weekly_lectures"]) for requirement in self.data["requirements"])
        self.assertEqual(len(self.entries), expected)

    def test_no_teacher_double_booking(self):
        occupied = set()
        for entry in self.entries:
            for slot in entry["slots"]:
                key = (entry["teacher_id"], entry["day_id"], slot)
                self.assertNotIn(key, occupied)
                occupied.add(key)

    def test_no_room_double_booking(self):
        occupied = set()
        for entry in self.entries:
            for slot in entry["slots"]:
                key = (entry["classroom_id"], entry["day_id"], slot)
                self.assertNotIn(key, occupied)
                occupied.add(key)

    def test_no_section_double_booking(self):
        occupied = set()
        for entry in self.entries:
            for slot in entry["slots"]:
                key = (entry["section_id"], entry["day_id"], slot)
                self.assertNotIn(key, occupied)
                occupied.add(key)

    def test_practicals_use_labs_and_consecutive_slots(self):
        slot_map = {slot["id"]: slot for slot in self.data["slots"]}
        room_map = {room["id"]: room for room in self.data["rooms"]}
        subject_map = {subject["id"]: subject for subject in self.data["subjects"]}
        for entry in self.entries:
            subject = subject_map[entry["subject_id"]]
            if subject["is_practical"]:
                self.assertEqual(room_map[entry["classroom_id"]]["room_type"], "LABORATORY")
                ordered = sorted(slot_map[slot]["slot_order"] for slot in entry["slots"])
                self.assertEqual(ordered, list(range(ordered[0], ordered[0] + len(ordered))))

    def test_teacher_availability_respected(self):
        teacher_map = {teacher["id"]: teacher for teacher in self.data["teachers"]}
        for entry in self.entries:
            for slot in entry["slots"]:
                self.assertTrue(teacher_map[entry["teacher_id"]]["available_slots"].get(f"{entry['day_id']},{slot}"))

    def test_impossible_scenario_fails(self):
        data = make_input()
        data["rooms"] = [room for room in data["rooms"] if room["room_type"] == "CLASSROOM"]
        data["teachers"] = [teacher for teacher in data["teachers"] if teacher["subject_ids"] == [1]]
        scheduler = Scheduler(data, node_limit=5000, time_limit=3)
        success = scheduler.solve()
        self.assertFalse(success)
        self.assertNotEqual(scheduler.failure_reason, "")

    def test_generator_json_contract(self):
        completed = subprocess.run(
            [sys.executable, str(ROOT / "python" / "generator.py")],
            input=json.dumps(make_input()), text=True, capture_output=True, check=True,
        )
        result = json.loads(completed.stdout)
        self.assertTrue(result["success"])
        self.assertEqual(result["algorithm"], "Backtracking with MRV heuristic")
        self.assertGreater(len(result["entries"]), 0)


if __name__ == "__main__":
    unittest.main()
