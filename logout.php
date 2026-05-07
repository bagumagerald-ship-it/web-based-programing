<?php
require_once __DIR__ . '/config.php';
startSession();
$_SESSION = [];
session_destroy();
redirect('index.php?msg=logged_out');
