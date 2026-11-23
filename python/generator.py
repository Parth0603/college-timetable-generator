"""JSON entry point used by PHP. PHP owns the database; this process only schedules."""

import json
import sys

from scheduler import Scheduler


def read_input():
    try:
        return json.load(sys.stdin)
    except json.JSONDecodeError as error:
        return {"input_error": f"Invalid JSON input: {error}"}


def main():
    data = read_input()
    if "input_error" in data:
        print(json.dumps({"success": False, "error": data["input_error"]}))
        return

    try:
        scheduler = Scheduler(data)
        success = scheduler.solve()
        if not success:
            print(json.dumps({
                "success": False,
                "error": scheduler.failure_reason,
                "scheduled_count": len(scheduler.schedule),
                "required_count": len(scheduler.sessions),
            }))
            return
        print(json.dumps({
            "success": True,
            "algorithm": "Backtracking with MRV heuristic",
            "quality_score": scheduler.quality_score(),
            "nodes_explored": scheduler.nodes,
            "entries": scheduler.schedule,
        }))
    except Exception as error:
        print(json.dumps({"success": False, "error": f"Scheduler error: {error}"}))


if __name__ == "__main__":
    main()
