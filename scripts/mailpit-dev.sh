#!/bin/sh

set -eu

ROOT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
MAILPIT_HOST=${MAILPIT_HOST:-127.0.0.1}
MAILPIT_SMTP_PORT=${MAILPIT_SMTP_PORT:-1025}
MAILPIT_UI_PORT=${MAILPIT_UI_PORT:-8025}
PID_FILE="$ROOT_DIR/writable/cache/mailpit.pid"
LOG_FILE="$ROOT_DIR/writable/logs/mailpit.log"

ensure_dirs() {
    mkdir -p "$ROOT_DIR/writable/cache" "$ROOT_DIR/writable/logs"
}

mailpit_cmd() {
    if command -v mailpit >/dev/null 2>&1; then
        command -v mailpit
        return 0
    fi

    echo "Mailpit is not installed." >&2
    echo "Install it first, for example on macOS: brew install mailpit" >&2
    return 1
}

is_running_pid() {
    pid="$1"
    if [ -z "$pid" ]; then
        return 1
    fi

    kill -0 "$pid" >/dev/null 2>&1
}

status_mailpit() {
    if [ -f "$PID_FILE" ]; then
        pid=$(cat "$PID_FILE")
        if is_running_pid "$pid"; then
            echo "Mailpit is running"
            echo "PID: $pid"
            echo "SMTP: smtp://$MAILPIT_HOST:$MAILPIT_SMTP_PORT"
            echo "UI: http://$MAILPIT_HOST:$MAILPIT_UI_PORT"
            echo "Log: $LOG_FILE"
            return 0
        fi

        rm -f "$PID_FILE"
    fi

    echo "Mailpit is not running"
    return 1
}

start_mailpit() {
    ensure_dirs
    cmd=$(mailpit_cmd)

    if [ -f "$PID_FILE" ]; then
        pid=$(cat "$PID_FILE")
        if is_running_pid "$pid"; then
            echo "Mailpit is already running"
            echo "PID: $pid"
            echo "UI: http://$MAILPIT_HOST:$MAILPIT_UI_PORT"
            return 0
        fi

        rm -f "$PID_FILE"
    fi

    nohup "$cmd" --smtp "$MAILPIT_HOST:$MAILPIT_SMTP_PORT" --listen "$MAILPIT_HOST:$MAILPIT_UI_PORT" >>"$LOG_FILE" 2>&1 &
    pid=$!
    echo "$pid" >"$PID_FILE"

    echo "Started Mailpit"
    echo "PID: $pid"
    echo "SMTP: smtp://$MAILPIT_HOST:$MAILPIT_SMTP_PORT"
    echo "UI: http://$MAILPIT_HOST:$MAILPIT_UI_PORT"
    echo "Log: $LOG_FILE"
}

stop_mailpit() {
    if [ ! -f "$PID_FILE" ]; then
        echo "Mailpit is not running"
        return 0
    fi

    pid=$(cat "$PID_FILE")
    if is_running_pid "$pid"; then
        kill "$pid"
        echo "Stopped Mailpit (PID: $pid)"
    else
        echo "Mailpit PID file existed but process was not running"
    fi

    rm -f "$PID_FILE"
}

usage() {
    echo "Usage: sh scripts/mailpit-dev.sh {start|stop|status}"
}

action=${1:-status}

case "$action" in
    start)
        start_mailpit
        ;;
    stop)
        stop_mailpit
        ;;
    status)
        status_mailpit
        ;;
    *)
        usage
        exit 1
        ;;
esac