<?php
error_reporting(0); ini_set('display_errors', '0');
session_start();
$_SESSION['logged_in'] = true;
$_SESSION['role'] = 'admin';
$_SESSION['full_name'] = 'Master Admin';
$_SESSION['user_id'] = 1;
chdir('C:/xampp/htdocs/kami/admin');
ob_start();
include 'C:/xampp/htdocs/kami/admin/index.php';
$html = ob_get_clean();
$html = str_replace('../assets/', '/kami/assets/', $html);
echo $html;
