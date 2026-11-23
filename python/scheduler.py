"""Backtracking timetable scheduler with the Minimum Remaining Values heuristic."""

import time
from collections import defaultdict

from constraints import (
    apply_placement,
    remove_placement,
    soft_constraint_cost,
    validate_hard_constraints,
)


class Scheduler:
    def __init__(self, data, node_limit=300000, time_limit=20.0):
        self.data = data
        self.node_limit = node_limit
        self.time_limit = time_limit
        self.nodes = 0
        self.started_at = time.time()
        self.failure_reason = "Timetable could not be generated."
        self.schedule = []
        self.teachers = {int(teacher["id"]): teacher for teacher in data["teachers"]}
        self.rooms = {int(room["id"]): room for room in data["rooms"]}
        self.sections = {int(section["id"]): section for section in data["sections"]}
        self.subjects = {int(subject["id"]): subject for subject in data["subjects"]}
        self.days = {int(day["id"]): day for day in data["days"]}
        self.slots = {int(slot["id"]): slot for slot in data["slots"]}
        self.break_slots = {
            (int(slot["day_id"]), int(slot["id"]))
            for slot in data["slots"]
            if slot["is_break"]
        }
        self.sessions = self.expand_requirements()
        self.session_map = {session["session_id"]: session for session in self.sessions}
        self.state = self.initial_state()

    def expand_requirements(self):
        sessions = []
        for requirement in self.data["requirements"]:
            subject = self.subjects[int(requirement["subject_id"])]
            section = self.sections[int(requirement["section_id"])]
            for session_number in range(1, int(requirement["weekly_lectures"]) + 1):
                sessions.append({
                    "session_id": f"r{requirement['id']}-s{session_number}",
                    "requirement": requirement,
                    "subject": subject,
                    "section": section,
                    "entry_type": "PRACTICAL" if int(subject["is_practical"]) else "THEORY",
                })
        return sessions

    def initial_state(self):
        return {
            "teacher_busy": defaultdict(set),
            "room_busy": defaultdict(set),
            "section_busy": defaultdict(set),
            "placed_slots": set(),
            "teacher_weekly": defaultdict(int),
            "teacher_daily": defaultdict(int),
            "subject_section_day": defaultdict(int),
            "section_practical_day": defaultdict(int),
            "teacher_practical_day": defaultdict(int),
            "break_slots": self.break_slots,
            "scheduled_count": 0,
        }

    def candidate_slots(self, session):
        candidates = []
        periods = int(session["requirement"]["periods_per_session"])
        for day in self.data["days"]:
            slots = [
                slot for slot in sorted(self.data["slots"], key=lambda value: value["slot_order"])
                if int(slot["day_id"]) == int(day["id"]) and not slot["is_break"]
            ]
            for index in range(0, len(slots) - periods + 1):
                selected = slots[index:index + periods]
                slot_ids = [int(slot["id"]) for slot in selected]
                candidates.append((day, slot_ids))
        return candidates

    def candidates_for_session(self, session):
        candidates = []
        for day, slot_ids in self.candidate_slots(session):
            for teacher in self.data["teachers"]:
                if int(session["subject"]["id"]) not in teacher["subject_ids"]:
                    continue
                for room in self.data["rooms"]:
                    if not self.room_matches(session, room):
                        continue
                    valid, reason = validate_hard_constraints(session, day, slot_ids, teacher, room, self.state)
                    if valid:
                        cost = soft_constraint_cost(self.state, session, day, slot_ids, teacher, room, self.slots)
                        candidates.append((cost, day, slot_ids, teacher, room))
                    elif not candidates:
                        self.failure_reason = reason
        candidates.sort(key=lambda candidate: (
            candidate[0],
            int(candidate[1]["day_index"]),
            int(self.slots[candidate[2][0]]["slot_order"]),
            int(candidate[3]["id"]),
            int(candidate[4]["id"]),
        ))
        return candidates

    def room_matches(self, session, room):
        required = session["requirement"]["room_type"]
        if required == "LABORATORY":
            return room["room_type"] == "LABORATORY"
        if required == "CLASSROOM":
            return room["room_type"] == "CLASSROOM"
        return int(room["capacity"]) >= int(session["section"]["student_count"])

    def choose_mrv_session(self, unassigned):
        """Minimum Remaining Values: try the most constrained lecture first."""
        best_id = None
        best_candidates = None
        for session_id in unassigned:
            session = self.session_map[session_id]
            candidates = self.candidates_for_session(session)
            if best_id is None or len(candidates) < len(best_candidates):
                best_id = session_id
                best_candidates = candidates
            if not candidates:
                break
        return self.session_map.get(best_id), best_candidates

    def solve(self):
        if not self.sessions:
            self.failure_reason = "No weekly subject requirements were supplied."
            return False
        if not self.data["days"] or not self.data["slots"]:
            self.failure_reason = "Working days and lecture slots are required."
            return False
        if not self.data["rooms"]:
            self.failure_reason = "At least one active classroom is required."
            return False

        success = self.backtrack({session["session_id"] for session in self.sessions})
        if success:
            return True
        if self.nodes >= self.node_limit:
            self.failure_reason = "Search limit reached. Reduce weekly lectures or add more teachers, rooms, or available slots."
        elif time.time() - self.started_at >= self.time_limit:
            self.failure_reason = "Scheduling timeout. Reduce weekly lectures or add more resources."
        return False

    def backtrack(self, unassigned):
        if not unassigned:
            return True
        if self.nodes >= self.node_limit:
            return False
        if time.time() - self.started_at >= self.time_limit:
            return False

        self.nodes += 1
        session, candidates = self.choose_mrv_session(unassigned)
        if not session:
            self.failure_reason = "All required lectures were scheduled."
            return True
        if not candidates:
            self.failure_reason = f"No valid allocation for {session['subject']['code']} ({session['section']['name']}). Last reason: {self.failure_reason}"
            return False

        for _cost, day, slot_ids, teacher, room in candidates:
            apply_placement(self.state, session, day, slot_ids, teacher, room)
            self.record_soft_counts(session, day, slot_ids, teacher, 1)
            entry = self.build_entry(session, day, slot_ids, teacher, room)
            self.schedule.append(entry)
            unassigned.remove(session["session_id"])

            if self.backtrack(unassigned):
                return True

            unassigned.add(session["session_id"])
            self.schedule.pop()
            self.record_soft_counts(session, day, slot_ids, teacher, -1)
            remove_placement(self.state, session, day, slot_ids, teacher, room)

        return False

    def record_soft_counts(self, session, day, slot_ids, teacher, direction):
        subject_key = (session["subject"]["id"], session["section"]["id"], day["id"])
        self.state["subject_section_day"][subject_key] += direction
        if session["entry_type"] == "PRACTICAL":
            section_key = (session["section"]["id"], day["id"])
            teacher_key = (teacher["id"], day["id"])
            self.state["section_practical_day"][section_key] += direction
            self.state["teacher_practical_day"][teacher_key] += direction

    def build_entry(self, session, day, slot_ids, teacher, room):
        return {
            "requirement_id": int(session["requirement"]["id"]),
            "section_id": int(session["section"]["id"]),
            "subject_id": int(session["subject"]["id"]),
            "teacher_id": int(teacher["id"]),
            "classroom_id": int(room["id"]),
            "entry_type": session["entry_type"],
            "periods_count": len(slot_ids),
            "day_id": int(day["id"]),
            "slots": [int(slot_id) for slot_id in slot_ids],
        }

    def quality_score(self):
        penalty = 0
        for load in self.state["teacher_weekly"].values():
            penalty += max(0, load - 8) * 3
        for value in self.state["subject_section_day"].values():
            penalty += max(0, value - 1) * 10
        for value in self.state["section_practical_day"].values():
            penalty += max(0, value - 1) * 15
        return max(0, 100 - penalty)
