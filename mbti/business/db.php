<?php
// 数据库配置
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'mbti_business');
define('DB_USER', 'mbti_user');
define('DB_PASS', 'Mbti2024!@#');

// 创建数据库连接
function getDB() {
    static $pdo = null;

    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
        } catch (PDOException $e) {
            die("数据库连接失败: " . $e->getMessage());
        }
    }

    return $pdo;
}

// 启动会话
function startSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

// 获取当前登录用户
function getCurrentUser() {
    startSession();
    if (!isset($_SESSION['company_id'])) {
        return null;
    }

    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT id, company_name, phone, email, status FROM companies WHERE id = ?");
    $stmt->execute([$_SESSION['company_id']]);
    return $stmt->fetch();
}

// 检查是否已登录
function isLoggedIn() {
    startSession();
    return isset($_SESSION['company_id']);
}

// 要求登录
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

// 生成API密钥
function generateApiKey() {
    return 'sk-' . bin2hex(random_bytes(16));
}

// 记录日志
function logLogin($company_id, $ip, $location) {
    $pdo = getDB();
    $stmt = $pdo->prepare("INSERT INTO login_logs (company_id, login_ip, login_location) VALUES (?, ?, ?)");
    $stmt->execute([$company_id, $ip, $location]);
}
