<?php require __DIR__ . '/lib.php'; $m = me(); if ($m) log_event('logout', $m['username']); $_SESSION = []; session_destroy(); header('Location: login.php' . (isset($_GET['idle']) ? '?idle=1' : ''));
