<?php
require_once 'db.php';

header('Content-Type: application/json');

$contact = trim($_POST['phone'] ?? ''); // 可以是手机号或邮箱

if (empty($contact)) {
    echo json_encode(['error' => '请输入手机号或邮箱']);
    exit;
}

// 生成验证码
$code = generateCode();

// 判断是手机号还是邮箱
if (strpos($contact, '@') !== false) {
    // 是邮箱，发送邮件
    $email = $contact;
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['error' => '邮箱格式不正确']);
        exit;
    }

    $result = sendEmail($email, $code);
    if ($result) {
        saveVerificationCode($email, $code);
        echo json_encode(['success' => true, 'type' => 'email']);
    } else {
        echo json_encode(['error' => '邮件发送失败']);
    }
} else {
    // 是手机号，发送短信
    if (!preg_match('/^1[3-9]\d{9}$/', $contact)) {
        echo json_encode(['error' => '手机号格式不正确']);
        exit;
    }

    $result = sendSMS($contact, $code);

    if ($result && $result['error_code'] === 0) {
        saveVerificationCode($contact, $code);
        echo json_encode(['success' => true, 'type' => 'sms']);
    } else {
        $msg = $result['reason'] ?? '发送失败';
        echo json_encode(['error' => $msg]);
    }
}
