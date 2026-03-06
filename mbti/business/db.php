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

// IP查询配置（聚合数据）
define('JUHE_IP_APPKEY', '86d3395168838897304ed50ec848428b');

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

// 获取IP地址位置
function getIpLocation($ip) {
    // 跳过内网IP
    if (preg_match('/^(127\.|10\.|172\.(1[6-9]|2[0-9]|3[01])\.|192\.168\.)/', $ip)) {
        return '本地网络';
    }

    $url = 'https://apis.juhe.cn/ip/ipNew';
    $params = [
        'ip' => $ip,
        'key' => JUHE_IP_APPKEY
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url . '?' . http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($response, true);
    if ($result && $result['error_code'] === 0 && !empty($result['result']['Country'])) {
        return $result['result']['Country'] . $result['result']['Province'] . $result['result']['City'];
    }
    return '未知';
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

// 邮件配置
define('SMTP_HOST', 'smtp.feishu.cn');
define('SMTP_PORT', 465);
define('SMTP_USER', 'mbtisystem@7373.com.cn');
define('SMTP_PASS', '9STsLynzXuOz3LE1');
define('SMTP_FROM', 'mbtisystem@7373.com.cn');

// 发送邮件验证码
function sendEmail($email, $code) {
    $subject = '=?UTF-8?B?' . base64_encode('MBTI 开放平台 - 验证码') . '?=';
    $message = '
    <html>
    <body style="font-family: Arial, sans-serif; padding: 20px;">
        <div style="max-width: 600px; margin: 0 auto; background: #F9F7F2; padding: 30px; border-radius: 12px;">
            <h2 style="color: #7A9478; text-align: center;">MBTI 开放平台</h2>
            <p style="color: #4A4A4A; font-size: 16px;">您好，</p>
            <p style="color: #4A4A4A; font-size: 16px;">您的验证码是：</p>
            <div style="background: #7A9478; color: white; font-size: 32px; font-weight: bold; padding: 15px 30px; border-radius: 8px; text-align: center; letter-spacing: 5px; margin: 20px 0;">
                ' . $code . '
            </div>
            <p style="color: #888; font-size: 14px;">验证码有效期为10分钟，请尽快完成验证。</p>
            <p style="color: #888; font-size: 12px; margin-top: 30px;">如果这不是您的操作，请忽略此邮件。</p>
        </div>
    </body>
    </html>
    ';

    $boundary = md5(uniqid(time()));
    $headers = "From: " . SMTP_FROM . "\r\n";
    $headers .= "Reply-To: " . SMTP_FROM . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";

    $body = "--{$boundary}\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $body .= chunk_split(base64_encode($message)) . "\r\n";
    $body .= "--{$boundary}--";

    // 使用 SMTP 连接发送邮件
    $socket = @fsockopen('ssl://' . SMTP_HOST, SMTP_PORT, $errno, $errstr, 30);
    if (!$socket) {
        return false;
    }

    $response = fgets($socket, 515);
    if (substr($response, 0, 3) != '220') {
        fclose($socket);
        return false;
    }

    // EHLO
    fputs($socket, "EHLO " . SMTP_HOST . "\r\n");
    while ($line = fgets($socket, 515)) {
        if (substr($line, 3, 1) == ' ') break;
    }

    // AUTH LOGIN
    fputs($socket, "AUTH LOGIN\r\n");
    fgets($socket, 515);
    fputs($socket, base64_encode(SMTP_USER) . "\r\n");
    fgets($socket, 515);
    fputs($socket, base64_encode(SMTP_PASS) . "\r\n");
    $response = fgets($socket, 515);
    if (substr($response, 0, 3) != '235') {
        fclose($socket);
        return false;
    }

    // MAIL FROM
    fputs($socket, "MAIL FROM:<" . SMTP_FROM . ">\r\n");
    fgets($socket, 515);

    // RCPT TO
    fputs($socket, "RCPT TO:<{$email}>\r\n");
    fgets($socket, 515);

    // DATA
    fputs($socket, "DATA\r\n");
    fgets($socket, 515);

    $emailHeaders = "From: MBTI 开放平台 <" . SMTP_FROM . ">\r\n";
    $emailHeaders .= "To: {$email}\r\n";
    $emailHeaders .= "Subject: {$subject}\r\n";
    $emailHeaders .= $headers . "\r\n";
    $emailHeaders .= "\r\n";
    $emailHeaders .= $body . "\r\n";

    fputs($socket, $emailHeaders);
    fputs($socket, ".\r\n");
    $response = fgets($socket, 515);

    fputs($socket, "QUIT\r\n");
    fclose($socket);

    return substr($response, 0, 3) == '250';
}

// 保存验证码到数据库
function saveVerificationCode($phone, $code) {
    $pdo = getDB();

    // 先删除该手机号之前的验证码
    $stmt = $pdo->prepare("DELETE FROM sms_codes WHERE phone = ?");
    $stmt->execute([$phone]);

    // 插入新验证码，使用MySQL的DATE_ADD确保时区一致
    $stmt = $pdo->prepare("INSERT INTO sms_codes (phone, code, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))");
    $stmt->execute([$phone, $code]);
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
