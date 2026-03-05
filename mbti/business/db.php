<?php
// 数据库配置
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'mbti_business');
define('DB_USER', 'mbti_user');
define('DB_PASS', 'Mbti2024!@#');

// 短信配置（聚合数据）
define('JUHE_SMS_APPKEY', '105e41edd818b92da18a959888f12cb4');
define('JUHE_SMS_TEMPLATE_ID', 1); // 短信模板ID

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

// 生成6位数字验证码
function generateCode() {
    return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

// 发送短信验证码
function sendSMS($phone, $code) {
    $url = 'https://v.juhe.cn/sms/send';
    $params = [
        'mobile' => $phone,
        'tpl_id' => JUHE_SMS_TEMPLATE_ID,
        'tpl_value' => urlencode('#code#=' . $code),
        'key' => JUHE_SMS_APPKEY
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    $response = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($response, true);
    return $result;
}

// 保存验证码到数据库
function saveVerificationCode($phone, $code) {
    $pdo = getDB();

    // 先删除该手机号之前的验证码
    $stmt = $pdo->prepare("DELETE FROM sms_codes WHERE phone = ?");
    $stmt->execute([$phone]);

    // 插入新验证码
    $expires_at = date('Y-m-d H:i:s', time() + 600); // 10分钟有效
    $stmt = $pdo->prepare("INSERT INTO sms_codes (phone, code, expires_at) VALUES (?, ?, ?)");
    $stmt->execute([$phone, $code, $expires_at]);
}

// 验证验证码
function verifyCode($phone, $code) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM sms_codes WHERE phone = ? AND code = ? AND expires_at > NOW()");
    $stmt->execute([$phone, $code]);
    $result = $stmt->fetch();

    if ($result) {
        // 验证成功后删除验证码
        $stmt = $pdo->prepare("DELETE FROM sms_codes WHERE phone = ?");
        $stmt->execute([$phone]);
        return true;
    }
    return false;
}
