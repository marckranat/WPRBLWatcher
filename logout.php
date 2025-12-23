<?php
require_once 'auth.php';

$auth = new Auth();
$auth->logout();

header('Location: login.php');
exit;

