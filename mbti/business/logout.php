<?php
require_once 'db.php';

startSession();

// 清除会话数据
$_SESSION = [];

// 清除记住登录的cookie
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
}

// 销毁会话
session_destroy();

// 跳转到登录页
header('Location: login.php');
exit;
