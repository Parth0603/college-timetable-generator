"""Hard and soft scheduling constraints for the college timetable."""

from collections import defaultdict


def active_slots_by_day(days, slots):
    """Return non-break slots grouped by day, in time-slot order."""
    grouped = {day["id"]: [] for day in days}
    for slot in sorted(slots, key=lambda value: value["slot_order"]):
        if slot["is_break"] == 0 and slot["is_break"] is not False:
            grouped.setdefault(slot["day_id"], []).append(slot)
    return grouped


def is_room_suitable(session, room):
    required = session["requirement"]["room_type"]
    if required == "LABORATORY" and room["room_type"] != "LABORATORY":
        return False
    if required == "CLASSROOM" and room["room_type"] == "LABORATORY":
        return False
    return int(room["capacity"]) >= int(session["section"]["student_count"])


def teacher_available(teacher, day_id, slot_ids):
    for slot_id in slot_ids:
        availability_key = f"{day_id},{slot_id}"
        if not teacher["available_slots"].get(availability_key, False):
            return False
    return True


def validate_hard_constraints(session, day, slot_ids, teacher, room, state):
    """Return (is_valid, reason). Every returned False maps to a hard constraint."""
    for slot_id in slot_ids:
        key = (day["id"], slot_id)
        if key in state["break_slots"]:
            return False, "HC-09: selected period is a break period."

    if int(session["subject"]["id"]) not in teacher["subject_ids"]:
        return False, "HC-10: teacher is not assigned to this subject."

    if not teacher_available(teacher, day["id"], slot_ids):
        return False, "HC-04: teacher is unavailable in this period."

    for slot_id in slot_ids:
        key = (day["id"], slot_id)
        if key in state["teacher_busy"] and teacher["id"] in state["teacher_busy"][key]:
            return False, "HC-01: teacher already has another lecture."
        if key in state["room_busy"] and room["id"] in state["room_busy"][key]:
            return False, "HC-02: classroom is already occupied."
        if key in state["section_busy"] and session["section"]["id"] in state["section_busy"][key]:
            return False, "HC-03: section already has another lecture."

    if not is_room_suitable(session, room):
        if room["capacity"] < session["section"]["student_count"]:
            return False, "HC-06: classroom capacity is too small."
        return False, "HC-07: practical session needs a laboratory."

    periods = len(slot_ids)
    if session["requirement"]["periods_per_session"] != periods:
        return False, "HC-08: practical session must occupy the required consecutive periods."

    if state["teacher_weekly"][teacher["id"]] + periods > int(teacher["max_weekly_load"]):
        return False, "HC-11: teacher exceeds maximum weekly workload."
    if state["teacher_daily"][(teacher["id"], day["id"])] + periods > int(teacher["max_daily_load"]):
        return False, "HC-11: teacher exceeds maximum daily workload."

    return True, ""


def apply_placement(state, session, day, slot_ids, teacher, room):
    for sequence, slot_id in enumerate(slot_ids, start=1):
        key = (day["id"], slot_id)
        state["teacher_busy"].setdefault(key, set()).add(teacher["id"])
        state["room_busy"].setdefault(key, set()).add(room["id"])
        state["section_busy"].setdefault(key, set()).add(session["section"]["id"])
        state["placed_slots"].add(key)
    state["teacher_weekly"][teacher["id"]] += len(slot_ids)
    state["teacher_daily"][(teacher["id"], day["id"])] += len(slot_ids)
    state["scheduled_count"] += 1


def remove_placement(state, session, day, slot_ids, teacher, room):
    for slot_id in slot_ids:
        key = (day["id"], slot_id)
        state["teacher_busy"][key].discard(teacher["id"])
        state["room_busy"][key].discard(room["id"])
        state["section_busy"][key].discard(session["section"]["id"])
        if not state["teacher_busy"][key]:
            state["teacher_busy"].pop(key, None)
        if not state["room_busy"][key]:
            state["room_busy"].pop(key, None)
        if not state["section_busy"][key]:
            state["section_busy"].pop(key, None)
        state["placed_slots"].discard(key)
    state["teacher_weekly"][teacher["id"]] -= len(slot_ids)
    state["teacher_daily"][(teacher["id"], day["id"])] -= len(slot_ids)
    state["scheduled_count"] -= 1


def soft_constraint_cost(state, session, day, slot_ids, teacher, room, slots):
    """Lower is better. Used only to order otherwise valid candidates."""
    cost = 0
    periods = len(slot_ids)
    first_order = int(slots[slot_ids[0]]["slot_order"])
    last_order = int(slots[slot_ids[-1]]["slot_order"])

    # Prefer balanced daily teacher workloads.
    daily_load = state["teacher_daily"][(teacher["id"], day["id"])]
    cost += daily_load * daily_load * 2

    # Avoid long consecutive teacher duty stretches.
    consecutive = 1
    while (day["id"], first_order - consecutive) in state["teacher_busy"] and teacher["id"] in state["teacher_busy"][(day["id"], first_order - consecutive)]:
        consecutive += 1
    while (day["id"], last_order + consecutive) in state["teacher_busy"] and teacher["id"] in state["teacher_busy"][(day["id"], last_order + consecutive)]:
        consecutive += 1
    cost += max(0, consecutive - 1) * 5

    # Spread a subject through the week and avoid same-day repeats.
    subject_day_count = state["subject_section_day"].get((session["subject"]["id"], session["section"]["id"], day["id"]), 0)
    cost += subject_day_count * 20

    # Prefer earlier periods and reduce unnecessary student gaps.
    cost += int(first_order) * 2

    # Keep practicals away from days already containing practicals.
    if session["entry_type"] == "PRACTICAL":
        cost += state["section_practical_day"].get((session["section"]["id"], day["id"]), 0) * 25
        cost += state["teacher_practical_day"].get((teacher["id"], day["id"]), 0) * 10

    return cost
