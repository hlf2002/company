<?php
require_once 'db.php';

header('Content-Type: application/json');

$phone = trim($_POST['phone'] ?? '');

if (empty($phone)) {
    echo json_encode(['error' => '请输入手机号']);
    exit;
}

if (!preg_match('/^1[3-9]\d{9}$/', $phone)) {
    echo json_encode(['error' => '手机号格式不正确']);
    exit;
}

// 生成验证码
$code = generateCode();

// 发送短信
$result = sendSMS($phone, $code);

if ($result && $result['error_code'] === 0) {
    // 保存验证码到数据库
    saveVerificationCode($phone, $code);
    echo json_encode(['success' => true]);
} else {
    $msg = $result['reason'] ?? '发送失败';
    echo json_encode(['error' => $msg]);
}
