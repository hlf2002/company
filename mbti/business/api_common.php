<?php
/**
 * API 签名验证
 */

// 响应 JSON 格式
function apiResponse($code, $msg, $data = null) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'code' => $code,
        'message' => $msg,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 验证请求方法
function requireMethod($method) {
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        apiResponse(405, "请求方法必须是 {$method}");
    }
}

// 验证必需参数
function requireParams($params) {
    $missing = [];
    foreach ($params as $param) {
        if (!isset($_GET[$param]) || empty($_GET[$param])) {
            $missing[] = $param;
        }
    }
    if (!empty($missing)) {
        apiResponse(400, "缺少必需参数: " . implode(', ', $missing));
    }
}

/**
 * 生成签名
 * @param array $params 请求参数
 * @param string $secret_key 私有密钥
 * @return string 签名字符串
 */
function generateSign($params, $secret_key) {
    // 1. 移除 sign 参数
    unset($params['sign']);

    // 2. 按参数名排序
    ksort($params);

    // 3. 拼接成字符串
    $string = '';
    foreach ($params as $key => $value) {
        if ($value !== '' && $value !== null) {
            $string .= $key . '=' . $value . '&';
        }
    }
    $string .= 'secret_key=' . $secret_key;

    // 4. MD5 加密
    return md5($string);
}

/**
 * 验证签名
 * @param array $params 请求参数
 * @param string $secret_key 私有密钥
 * @return bool 签名是否有效
 */
function verifySign($params, $secret_key) {
    if (!isset($params['sign'])) {
        return false;
    }
    $expectedSign = generateSign($params, $secret_key);
    return $params['sign'] === $expectedSign;
}

/**
 * 获取客户端 IP
 */
function getClientIp() {
    $ip = '';
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } elseif (isset($_SERVER['HTTP_CLIENT_IP']) && !empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (isset($_SERVER['REMOTE_ADDR']) && !empty($_SERVER['REMOTE_ADDR'])) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    // 取第一个 IP
    if (strpos($ip, ',') !== false) {
        $ip = explode(',', $ip)[0];
    }
    return trim($ip);
}

/**
 * 验证 API 密钥
 * @param string $access_key 访问密钥
 * @return array|false 返回密钥信息或 false
 */
function verifyAccessKey($access_key) {
    $pdo = getDB();

    // 兼容新旧表结构：新表用status，旧表用is_active
    $stmt = $pdo->prepare("
        SELECT ak.*, c.company_name
        FROM api_keys ak
        JOIN companies c ON ak.company_id = c.id
        WHERE ak.access_key = ?
    ");
    $stmt->execute([$access_key]);
    $keyInfo = $stmt->fetch();

    if (!$keyInfo) {
        return false;
    }

    // 兼容 is_active (旧字段) 和 status (新字段)
    $isActive = isset($keyInfo['status']) ? $keyInfo['status'] : $keyInfo['is_active'];
    if ($isActive != 1) {
        return false;
    }

    // 兼容 key_value (旧字段) 和 secret_key (新字段)
    $secretKey = isset($keyInfo['secret_key']) && !empty($keyInfo['secret_key'])
        ? $keyInfo['secret_key']
        : $keyInfo['key_value'];
    $keyInfo['secret_key'] = $secretKey;

    // 如果没有新字段，初始化默认值
    if (!isset($keyInfo['request_limit'])) {
        $keyInfo['request_limit'] = 1000;
    }
    if (!isset($keyInfo['request_today'])) {
        $keyInfo['request_today'] = 0;
    }

    // 检查日请求限制
    $today = date('Y-m-d');
    if ($keyInfo['last_request_date'] !== $today) {
        // 新的一天，重置计数
        $stmt = $pdo->prepare("UPDATE api_keys SET request_today = 0, last_request_date = ? WHERE id = ?");
        $stmt->execute([$today, $keyInfo['id']]);
        $keyInfo['request_today'] = 0;
    }

    if ($keyInfo['request_today'] >= $keyInfo['request_limit']) {
        return ['error' => '日请求次数已达上限'];
    }

    // 更新请求计数
    $stmt = $pdo->prepare("UPDATE api_keys SET request_today = request_today + 1, last_used_at = NOW() WHERE id = ?");
    $stmt->execute([$keyInfo['id']]);

    return $keyInfo;
}

/**
 * 记录测试日志
 * @param int $company_id 企业ID
 * @param string $access_key 访问密钥
 * @param string $external_user_id 外部用户ID
 * @param string $test_token 测试令牌
 * @param string $callback_url 回调URL
 * @return int 测试记录ID
 */
function createTestRecord($company_id, $access_key, $external_user_id, $test_token, $callback_url = null) {
    $pdo = getDB();

    // 检查是否已存在测试记录
    $stmt = $pdo->prepare("SELECT id FROM api_tests WHERE company_id = ? AND external_user_id = ? AND status = 'pending' ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$company_id, $external_user_id]);
    $existing = $stmt->fetch();

    if ($existing) {
        // 更新现有记录
        $stmt = $pdo->prepare("UPDATE api_tests SET test_token = ?, callback_url = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$test_token, $callback_url, $existing['id']]);
        return $existing['id'];
    }

    // 创建新记录
    $stmt = $pdo->prepare("INSERT INTO api_tests (company_id, access_key, external_user_id, test_token, callback_url, status) VALUES (?, ?, ?, ?, ?, 'pending')");
    $stmt->execute([$company_id, $access_key, $external_user_id, $test_token, $callback_url]);
    return $pdo->lastInsertId();
}

/**
 * 生成随机令牌
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length / 2));
}
