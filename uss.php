<?php
$username = "helpdesk";
$password = "halo123";

exec("sudo useradd -m -s /bin/bash $username && echo '$username:$password' | sudo chpasswd");
?>
