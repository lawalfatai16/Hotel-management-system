<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

header('Location: ' . (Auth::user() ? 'dashboard.php' : 'login.php'));
exit;
