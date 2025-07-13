<?php
require_once '../config/config.php';

session_destroy();
header('Location: ' . BASE_URL . '/auth/login.php');
exit;

?>