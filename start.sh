#!/bin/bash
set -e

echo "=== Starting College Timetable Generator ==="

# Wait a moment for database to be ready
echo "Waiting for database..."
sleep 5

# Initialize database if needed
if [ -n "$DATABASE_URL" ]; then
    echo "Running database initialization..."
    php /app/install.php || echo "Database initialization skipped (may already exist)"
fi

# Start keep-alive background process (pings every 10 minutes)
echo "Starting keep-alive monitor..."
(
    while true; do
        sleep 600
        curl -s -o /dev/null -w "Keep-alive ping: %{http_code}\n" http://localhost:${PORT:-10000}/health.php || true
    done
) &
echo "Keep-alive process started."

# Start PHP server
echo "Starting web server on port ${PORT:-10000}..."
exec php -S 0.0.0.0:${PORT:-10000} -t /app
