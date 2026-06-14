<?php
// index.php
if (!file_exists("config/database.php")) {
    header("Location: install.php");
    exit();
}
header("Location: login.php");
exit();
?>
