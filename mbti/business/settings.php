<?php
require_once 'db.php';
requireLogin();

$user = getCurrentUser();
$company_id = $_SESSION['company_id'];
$pdo = getDB();

$message = '';
$error = '';

// 处理修改密码
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $old_password = $_POST['old_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($old_password) || empty($new_password) || empty($confirm_password)) {
        $error = '请填写所有密码字段';
    } elseif (strlen($new_password) < 6) {
        $error = '新密码长度至少6位';
    } elseif ($new_password !== $confirm_password) {
        $error = '两次输入的密码不一致';
    } else {
        // 验证旧密码
        $stmt = $pdo->prepare("SELECT password_hash FROM companies WHERE id = ?");
        $stmt->execute([$company_id]);
        $company = $stmt->fetch();

        if (!password_verify($old_password, $company['password_hash'])) {
            $error = '原密码错误';
        } else {
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE companies SET password_hash = ? WHERE id = ?");
            $stmt->execute([$new_hash, $company_id]);
            $message = '密码修改成功';
        }
    }
}

// 处理重置API密钥
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_api_key') {
    $key_id = $_POST['key_id'] ?? 0;

    // 验证密钥属于当前用户
    $stmt = $pdo->prepare("SELECT id FROM api_keys WHERE id = ? AND company_id = ?");
    $stmt->execute([$key_id, $company_id]);

    if ($stmt->fetch()) {
        $new_key = generateApiKey();
        $stmt = $pdo->prepare("UPDATE api_keys SET key_value = ? WHERE id = ?");
        $stmt->execute([$new_key, $key_id]);
        $message = 'API密钥已重置';
    }
}

// 处理复制API密钥
if (isset($_GET['action']) && $_GET['action'] === 'copy_key') {
    $key_id = $_GET['key_id'] ?? 0;
    $stmt = $pdo->prepare("SELECT key_value FROM api_keys WHERE id = ? AND company_id = ?");
    $stmt->execute([$key_id, $company_id]);
    $key_row = $stmt->fetch();
    if ($key_row) {
        echo $key_row['key_value'];
        exit;
    }
}

// 获取API密钥列表
$stmt = $pdo->prepare("SELECT id, key_value, key_name, is_active, created_at, last_used_at FROM api_keys WHERE company_id = ?");
$stmt->execute([$company_id]);
$api_keys = $stmt->fetchAll();

// 获取最近登录记录
$stmt = $pdo->prepare("SELECT login_ip, login_location, created_at FROM login_logs WHERE company_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->execute([$company_id]);
$login_logs = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="zh-CN"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>MBTI 用户中心 - 账号设置与密钥管理</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+SC:wght@400;500;700&amp;family=Quicksand:wght@500;600;700&amp;display=swap" rel="stylesheet"/>
<style type="text/tailwindcss">
        :root {
            --warm-beige: #F9F7F2;
            --soft-white: #FFFFFF;
            --sage-green: #7A9478;
            --sage-light: #E8EDE7;
            --terracotta: #D98E73;
            --terracotta-light: #F7EAE5;
            --text-main: #4A4A4A;
            --sidebar-beige: #F2EFE9;
        }
        body {
            font-family: 'Quicksand', 'Noto Sans SC', sans-serif;
            color: var(--text-main);
            background-color: var(--warm-beige);
        }
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 300, 'GRAD' 0, 'opsz' 24;
            color: var(--sage-green);
        }
        .active-nav {
            background-color: var(--sage-light);
            color: var(--sage-green);
            font-weight: 600;
        }
        .active-nav .material-symbols-outlined {
            font-variation-settings: 'FILL' 1, 'wght' 400;
        }
        .custom-shadow {
            box-shadow: 0 4px 20px -2px rgba(122, 148, 120, 0.08);
        }
    </style>
<script id="tailwind-config">
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        "warm-beige": "#F9F7F2",
                        "sage-green": "#7A9478",
                        "terracotta": "#D98E73",
                        "text-main": "#4A4A4A",
                        "sidebar-beige": "#F2EFE9",
                    },
                    borderRadius: {
                        "DEFAULT": "0.5rem",
                        "lg": "1rem",
                        "xl": "1.5rem",
                        "full": "9999px"
                    },
                },
            },
        }
    </script>
</head>
<body class="bg-warm-beige text-text-main">
<div class="flex min-h-screen overflow-hidden">
<aside class="w-64 bg-sidebar-beige flex flex-col shrink-0 border-r border-[#E5E1D8]">
<div class="p-8 flex items-center gap-3">
<div class="size-10 bg-sage-green rounded-xl flex items-center justify-center text-white shadow-sm">
<span class="material-symbols-outlined !text-white !fill-1">psychology</span>
</div>
<div>
<h1 class="text-sage-green text-lg font-bold leading-tight">MBTI Admin</h1>
<p class="text-[10px] text-gray-500 uppercase tracking-widest font-semibold">User Center</p>
</div>
</div>
<nav class="flex-1 px-4 py-4 space-y-2">
<a class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-white/50 transition-colors group" href="index.php">
<span class="material-symbols-outlined">dashboard</span>
<span class="text-sm font-medium opacity-80 group-hover:opacity-100">控制台</span>
</a>
<a class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-white/50 transition-colors group" href="#">
<span class="material-symbols-outlined">monitoring</span>
<span class="text-sm font-medium opacity-80 group-hover:opacity-100">数据统计</span>
</a>
<a class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-white/50 transition-colors group" href="#">
<span class="material-symbols-outlined">account_balance_wallet</span>
<span class="text-sm font-medium opacity-80 group-hover:opacity-100">充值中心</span>
</a>
<a class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-white/50 transition-colors group" href="#">
<span class="material-symbols-outlined">receipt_long</span>
<span class="text-sm font-medium opacity-80 group-hover:opacity-100">充值记录</span>
</a>
<a class="flex items-center gap-3 px-4 py-3 rounded-xl active-nav transition-colors" href="settings.php">
<span class="material-symbols-outlined">settings</span>
<span class="text-sm">账号设置</span>
</a>
</nav>
<div class="p-6">
<div class="bg-white/40 rounded-2xl p-4 border border-white/60">
<p class="text-[10px] text-gray-500 mb-2 font-bold uppercase tracking-tighter">API Status</p>
<div class="flex items-center gap-2">
<div class="size-2 rounded-full bg-sage-green animate-pulse"></div>
<span class="text-xs font-medium text-sage-green">服务连接正常</span>
</div>
</div>
</div>
</aside>
<main class="flex-1 flex flex-col min-w-0 overflow-y-auto">
<header class="h-16 bg-warm-beige flex items-center justify-between px-10 sticky top-0 z-10 border-b border-[#E5E1D8]/50">
<div class="flex items-center gap-4 w-1/3">
<div class="relative w-full max-w-sm">
<span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-lg">search</span>
<input class="w-full bg-white border-none rounded-full pl-10 text-sm focus:ring-2 focus:ring-sage-green/20 placeholder:text-gray-400 custom-shadow" placeholder="搜索功能或记录..." type="text"/>
</div>
</div>
<div class="flex items-center gap-6">
<button class="relative p-2 text-sage-green hover:bg-white rounded-full transition-colors">
<span class="material-symbols-outlined text-2xl">notifications</span>
<span class="absolute top-2.5 right-2.5 size-1.5 bg-terracotta rounded-full"></span>
</button>
<div class="flex items-center gap-4 pl-6 border-l border-gray-200">
<div class="text-right">
<p class="text-sm font-bold text-gray-700"><?php echo htmlspecialchars($user['company_name']); ?></p>
<p class="text-[10px] text-sage-green font-bold uppercase tracking-wider">Enterprise Partner</p>
</div>
<div class="size-10 rounded-full overflow-hidden border-2 border-white shadow-sm ring-1 ring-sage-light">
<img alt="User Avatar" class="w-full h-full object-cover" src="https://lh3.googleusercontent.com/aida-public/AB6AXuDFUyoA7L4CbTGWECWvgumWaeNydIWF37xV0QHiiY0MXSJRE2FJ5FmJINv3_7FfcKG-B9E-SpaRsYqSDYvJQNBPJlwyY1zrdg6GV0xxwcqi0rZpPKdAcGwQkchXeTVzffKLE_ek3Ia9srVWbP09t86ATH4czzVXcUpPRdaq0Sm-jS8GZfegWhzw9M-qhG-zFNUKRvknzbFqhF2cd60qqMm5SpLqpt5seoeXEgtldH-aaQxMbREoruj1fHQNoj3aOuBnPyCGLUhhsPM"/>
</div>
<a href="logout.php" class="ml-2 text-sm text-sage-green hover:underline">退出</a>
</div>
</div>
</header>
<div class="p-10 space-y-8">
<div>
<h2 class="text-2xl font-bold text-gray-800">账号设置</h2>
<p class="text-gray-500 text-sm mt-1">管理您的账号安全与 API 接入配置</p>
</div>

<?php if ($message): ?>
<div class="p-3 bg-green-50 border border-green-200 rounded-xl text-green-600 text-sm">
<?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="p-3 bg-red-50 border border-red-200 rounded-xl text-red-600 text-sm">
<?php echo htmlspecialchars($error); ?>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 gap-8">
<section class="bg-white rounded-3xl custom-shadow border border-white overflow-hidden">
<div class="px-8 py-6 border-b border-warm-beige bg-sage-light/10">
<div class="flex items-center gap-3">
<span class="material-symbols-outlined">shield</span>
<h3 class="font-bold text-gray-800">安全性设置</h3>
</div>
</div>
<div class="p-8 space-y-8">
<form method="POST">
<input type="hidden" name="action" value="change_password"/>
<div class="flex items-center justify-between pb-6 border-b border-warm-beige">
<div class="flex items-start gap-4">
<div class="p-3 bg-sage-light/30 rounded-2xl">
<span class="material-symbols-outlined !text-sage-green">lock</span>
</div>
<div>
<h4 class="font-bold text-gray-700">修改密码</h4>
<p class="text-sm text-gray-400 mt-1 flex items-center gap-2">
                                        当前安全等级：<span class="text-sage-green font-bold">高</span>
<span class="flex gap-0.5">
<span class="w-3 h-1 bg-sage-green rounded-full"></span>
<span class="w-3 h-1 bg-sage-green rounded-full"></span>
<span class="w-3 h-1 bg-sage-green rounded-full"></span>
</span>
</p>
</div>
</div>
<div class="space-y-4">
<div>
<label class="block text-sm font-bold text-gray-700 mb-2">原密码</label>
<input name="old_password" class="w-full px-4 py-3 bg-warm-beige/50 border border-warm-beige rounded-xl focus:ring-2 focus:ring-sage-green/20 focus:border-sage-green outline-none transition-all" placeholder="请输入原密码" type="password"/>
</div>
<div>
<label class="block text-sm font-bold text-gray-700 mb-2">新密码</label>
<input name="new_password" class="w-full px-4 py-3 bg-warm-beige/50 border border-warm-beige rounded-xl focus:ring-2 focus:ring-sage-green/20 focus:border-sage-green outline-none transition-all" placeholder="请输入新密码" type="password"/>
</div>
<div>
<label class="block text-sm font-bold text-gray-700 mb-2">确认新密码</label>
<input name="confirm_password" class="w-full px-4 py-3 bg-warm-beige/50 border border-warm-beige rounded-xl focus:ring-2 focus:ring-sage-green/20 focus:border-sage-green outline-none transition-all" placeholder="请再次输入新密码" type="password"/>
</div>
</div>
<button type="submit" class="px-6 py-2 border-2 border-sage-green text-sage-green font-bold text-sm rounded-xl hover:bg-sage-green hover:text-white transition-all">
                                立即修改
                            </button>
</form>
</div>
<div class="flex items-center justify-between">
<div class="flex items-start gap-4">
<div class="p-3 bg-sage-light/30 rounded-2xl">
<span class="material-symbols-outlined !text-sage-green">history</span>
</div>
<div>
<h4 class="font-bold text-gray-700">登录活动</h4>
<div class="flex flex-col gap-1 mt-1">
<?php if (!empty($login_logs)): ?>
<p class="text-sm text-gray-500">最近登录时间：<span class="text-gray-700 font-medium"><?php echo date('Y-m-d H:i:s', strtotime($login_logs[0]['created_at'])); ?></span></p>
<p class="text-xs text-gray-400">登录地点：<?php echo htmlspecialchars($login_logs[0]['login_location'] ?: '未知'); ?></p>
<?php else: ?>
<p class="text-sm text-gray-500">暂无登录记录</p>
<?php endif; ?>
</div>
</div>
</div>
<button class="text-sm font-bold text-sage-green hover:underline">查看详情</button>
</div>
</div>
</section>
<section class="bg-white rounded-3xl custom-shadow border border-white overflow-hidden">
<div class="px-8 py-6 border-b border-warm-beige bg-sage-light/10">
<div class="flex items-center justify-between">
<div class="flex items-center gap-3">
<span class="material-symbols-outlined">code</span>
<h3 class="font-bold text-gray-800">开发者设置</h3>
</div>
<a class="text-xs font-bold text-sage-green flex items-center gap-1 hover:underline" href="#">
<span class="material-symbols-outlined text-sm">description</span>
                                查看开发文档
                            </a>
</div>
</div>
<div class="p-8">
<h4 class="font-bold text-gray-700 mb-4">密钥管理</h4>
<?php foreach ($api_keys as $key): ?>
<div class="bg-warm-beige/50 border border-warm-beige rounded-2xl p-5 flex items-center justify-between mb-4">
<div class="flex items-center gap-4">
<div class="size-10 bg-white rounded-xl flex items-center justify-center border border-sage-light">
<span class="material-symbols-outlined !text-sage-green">vpn_key</span>
</div>
<div>
<p class="text-[10px] text-gray-400 uppercase font-bold tracking-widest"><?php echo htmlspecialchars($key['key_name']); ?></p>
<p class="font-mono text-gray-700 tracking-wider mt-0.5" id="key-<?php echo $key['id']; ?>"><?php echo htmlspecialchars(substr($key['key_value'], 0, 10) . '••••••••••••' . substr($key['key_value'], -4)); ?></p>
<input type="hidden" id="full-key-<?php echo $key['id']; ?>" value="<?php echo htmlspecialchars($key['key_value']); ?>"/>
</div>
</div>
<div class="flex gap-3">
<button onclick="copyKey(<?php echo $key['id']; ?>)" class="flex items-center gap-2 px-4 py-2 bg-white border border-sage-light text-sage-green text-sm font-bold rounded-xl hover:bg-sage-light/30 transition-all">
<span class="material-symbols-outlined text-lg">content_copy</span>
                                        复制
                                    </button>
<form method="POST" style="display:inline;">
<input type="hidden" name="action" value="reset_api_key"/>
<input type="hidden" name="key_id" value="<?php echo $key['id']; ?>"/>
<button type="submit" onclick="return confirm('确定要重置密钥吗？重置后旧密钥将失效。')" class="flex items-center gap-2 px-4 py-2 bg-terracotta/10 border border-terracotta/20 text-terracotta text-sm font-bold rounded-xl hover:bg-terracotta/20 transition-all">
<span class="material-symbols-outlined text-lg !text-terracotta">refresh</span>
                                        重置密钥
                                    </button>
</form>
</div>
</div>
<?php endforeach; ?>
</div>
<div class="bg-terracotta-light/30 p-4 rounded-2xl border border-terracotta/10 flex items-start gap-3">
<span class="material-symbols-outlined !text-terracotta !fill-1 text-lg">info</span>
<div class="text-xs text-gray-500 leading-relaxed">
<p class="font-bold text-terracotta mb-1">安全提示</p>
                                API 密钥具有很高的权限，请务必妥善保管。切勿在公开的代码库中泄露您的密钥。如发现密钥可能泄露，请立即点击"重置密钥"以保证账号安全。
                            </div>
</div>
</div>
</section>
</div>
</div>
<div class="p-10 mt-auto text-center">
<p class="text-[10px] text-gray-400 uppercase tracking-widest font-bold">© 2024 MBTI Personality Analysis Platform - B-side Partner Console</p>
</div>
</main>
</div>

<script>
function copyKey(keyId) {
    const fullKey = document.getElementById('full-key-' + keyId).value;
    navigator.clipboard.writeText(fullKey).then(function() {
        alert('API密钥已复制到剪贴板');
    }, function(err) {
        console.error('复制失败: ', err);
    });
}
</script>

</body></html>
