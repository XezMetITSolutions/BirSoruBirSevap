<?php
/**
 * Çıkış Sayfası
 */

require_once 'auth.php';

$auth = Auth::getInstance();
$auth->logout();

header('Location: login.php');
exit;
?>
