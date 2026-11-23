#!/bin/bash
set -e

echo "=== Starting College Timetable Generator ==="

# Wait a moment for database to be ready
echo "Waiting for database..."
sleep 5

# Initialize database if needed (creates tables + sample data on first boot)
if [ -n "$DATABASE_URL" ]; then
    echo "Running database initialization..."
    php /app/install.php || echo "Database initialization skipped (may already exist)"
fi

# Keep-alive: ping the public URL every 5 minutes so the free tier never sleeps.
# RENDER_EXTERNAL_URL is injected automatically by Render (e.g. https://xxx.onrender.com).
# Fallback to localhost if the variable is not present (local Docker testing).
(
    while true; do
        sleep 300
        TARGET="${RENDER_EXTERNAL_URL:-http://localhost:${PORT:-10000}}/health.php"
        curl -s -o /dev/null -w "Keep-alive ping: %{http_code}\n" "$TARGET" || true
    done
) &
echo "Keep-alive monitor started (every 5 minutes)."

# Start PHP server on the port Render assigns
echo "Starting web server on port ${PORT:-10000}..."
exec php -S 0.0.0.0:${PORT:-10000} -t /app
