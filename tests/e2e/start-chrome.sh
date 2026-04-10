#!/usr/bin/env bash
# Launch Chrome on Windows from WSL2 with remote debugging enabled.
# Used by Playwright as a globalSetup to auto-launch Chrome before tests.
#
# Usage: ./start-chrome.sh [port]
# Default port: 9222

set -euo pipefail

PORT="${1:-9222}"
CHROME_PATH="/mnt/c/Program Files/Google/Chrome/Application/chrome.exe"
USER_DATA_DIR="C:\\Temp\\chrome-playwright-debug"

# Check if Chrome is already listening on the debug port
if curl -s --connect-timeout 1 "http://localhost:${PORT}/json/version" > /dev/null 2>&1; then
    echo "Chrome already running on port ${PORT}"
    exit 0
fi

# Launch Chrome in background
"$CHROME_PATH" \
    --remote-debugging-port="${PORT}" \
    --remote-debugging-address=127.0.0.1 \
    --remote-allow-origins=* \
    --user-data-dir="${USER_DATA_DIR}" \
    --no-first-run \
    --no-default-browser-check \
    > /dev/null 2>&1 &

# Wait for Chrome to be ready
for i in $(seq 1 10); do
    if curl -s --connect-timeout 1 "http://localhost:${PORT}/json/version" > /dev/null 2>&1; then
        echo "Chrome ready on port ${PORT}"
        exit 0
    fi
    sleep 1
done

echo "ERROR: Chrome failed to start on port ${PORT}" >&2
exit 1
