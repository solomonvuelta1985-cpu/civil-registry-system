#!/bin/bash
# ================================================================
# iScan Scanner Service — Linux/Synology Startup Script
# ================================================================
# Controls the Flask-based scanner service for Epson DS-530 II
#
# Usage:
#   bash start_scanner.sh           # Start the service
#   bash start_scanner.sh stop      # Stop the service
#   bash start_scanner.sh restart   # Restart the service
#   bash start_scanner.sh status    # Check if running
#   bash start_scanner.sh logs      # Tail the log file
# ================================================================

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PID_FILE="$SCRIPT_DIR/scanner.pid"
LOG_FILE="$SCRIPT_DIR/scanner.log"
SERVICE_PORT=18622

is_running() {
    [ -f "$PID_FILE" ] && ps -p "$(cat "$PID_FILE")" > /dev/null 2>&1
}

start_service() {
    if is_running; then
        echo "Scanner service already running (PID: $(cat "$PID_FILE"))"
        return 0
    fi

    echo "Installing Python dependencies..."
    cd "$SCRIPT_DIR"
    pip3 install -r requirements.txt --quiet 2>&1 | tail -5

    echo "Starting scanner service on port $SERVICE_PORT..."
    nohup python3 scanner_service.py >> "$LOG_FILE" 2>&1 &
    echo $! > "$PID_FILE"

    # Wait briefly and verify it started
    sleep 2
    if is_running; then
        echo "Scanner service started (PID: $(cat "$PID_FILE"))"
        echo "Test: curl http://localhost:$SERVICE_PORT/scanner/status"
    else
        echo "ERROR: Scanner service failed to start. Check: $LOG_FILE"
        rm -f "$PID_FILE"
        return 1
    fi
}

stop_service() {
    if is_running; then
        kill "$(cat "$PID_FILE")"
        rm -f "$PID_FILE"
        echo "Scanner service stopped."
    else
        echo "Scanner service is not running."
    fi
}

case "$1" in
    stop)
        stop_service
        ;;
    restart)
        stop_service
        sleep 1
        start_service
        ;;
    status)
        if is_running; then
            echo "Running (PID: $(cat "$PID_FILE"), port: $SERVICE_PORT)"
            curl -s "http://localhost:$SERVICE_PORT/scanner/status" 2>/dev/null | python3 -m json.tool 2>/dev/null || true
        else
            echo "Not running."
        fi
        ;;
    logs)
        tail -50f "$LOG_FILE"
        ;;
    *)
        start_service
        ;;
esac
