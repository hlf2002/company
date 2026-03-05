<?php
require_once 'db.php';

startSession();

// 如果已登录，跳转到控制台
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

// 处理登录请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    if (empty($phone) && empty($email)) {
        $error = '请输入手机号或邮箱';
    } elseif (empty($password)) {
        $error = '请输入密码';
    } else {
        $pdo = getDB();

        // 查询用户
        $stmt = $pdo->prepare("SELECT id, company_name, password_hash, status FROM companies WHERE phone = ? OR email = ?");
        $stmt->execute([$phone ?: $email, $phone ?: $email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            if ($user['status'] === 2) {
                $error = '账号已被禁用，请联系管理员';
            } else {
                // 登录成功
                $_SESSION['company_id'] = $user['id'];
                $_SESSION['company_name'] = $user['company_name'];

                // 记录登录日志
                $ip = $_SERVER['REMOTE_ADDR'] ?? '';
                $location = 'IP地址: ' . $ip;
                logLogin($user['id'], $ip, $location);

                // 记住登录
                if ($remember) {
                    $token = bin2hex(random_bytes(32));
                    setcookie('remember_token', $token, time() + 7 * 24 * 3600, '/');
                }

                header('Location: index.php');
                exit;
            }
        } else {
            $error = '手机号/邮箱或密码错误';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>MBTI 开放平台 - B端登录</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&amp;family=Noto+Sans+SC:wght@400;500;700&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<script id="tailwind-config">
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        "primary": "#7A9478",
                        "primary-dark": "#637a61",
                        "background-warm": "#F9F7F2",
                        "soft-gray": "#F1F1F1",
                    },
                    fontFamily: {
                        "sans": ["Inter", "Noto Sans SC", "sans-serif"],
                    }
                }
            }
        }
    </script>
<style type="text/tailwindcss">
        @layer base {
            body {
                @apply bg-background-warm font-sans text-slate-800 antialiased;
            }
        }
        .login-card {
            @apply bg-white rounded-3xl shadow-[0_20px_50px_rgba(122,148,120,0.1)];
        }
        .input-group:focus-within .material-symbols-outlined {
            @apply text-primary;
        }
    </style>
</head>
<body class="min-h-screen flex flex-col items-center justify-center p-6">
<div class="w-full max-w-md">
<div class="flex flex-col items-center mb-8">
<div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-primary text-white shadow-lg shadow-primary/20 mb-4">
<span class="material-symbols-outlined text-3xl">psychology</span>
</div>
<h1 class="text-2xl font-bold tracking-tight text-slate-800">MBTI 开放平台</h1>
<p class="text-slate-400 mt-1">B端合作伙伴登录</p>
</div>
<div class="login-card p-10">
<div class="mb-8">
<h2 class="text-2xl font-bold text-slate-800">欢迎回来</h2>
<p class="text-sm text-slate-500 mt-2">请输入您的账号信息以管理您的测评集成</p>
</div>

<?php if ($error): ?>
<div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-xl text-red-600 text-sm">
<?php echo htmlspecialchars($error); ?>
</div>
<?php endif; ?>

<?php if ($success): ?>
<div class="mb-4 p-3 bg-green-50 border border-green-200 rounded-xl text-green-600 text-sm">
<?php echo htmlspecialchars($success); ?>
</div>
<?php endif; ?>

<form method="POST" class="space-y-6">
<div class="space-y-2">
<label class="text-sm font-semibold text-slate-700 ml-1">手机号 / 邮箱</label>
<div class="input-group relative flex items-center">
<span class="material-symbols-outlined absolute left-4 text-slate-400">person</span>
<input name="phone" class="w-full bg-slate-50 border-none rounded-xl py-3.5 pl-12 pr-4 text-sm focus:ring-2 focus:ring-primary/20 focus:bg-white transition-all outline-none text-slate-700" placeholder="请输入手机号或邮箱" type="text"/>
</div>
</div>
<div class="space-y-2">
<label class="text-sm font-semibold text-slate-700 ml-1">密码</label>
<div class="input-group relative flex items-center">
<span class="material-symbols-outlined absolute left-4 text-slate-400">lock</span>
<input name="password" class="w-full bg-slate-50 border-none rounded-xl py-3.5 pl-12 pr-12 text-sm focus:ring-2 focus:ring-primary/20 focus:bg-white transition-all outline-none text-slate-700" placeholder="请输入您的密码" type="password" id="password"/>
<button type="button" class="absolute right-4 text-slate-400 hover:text-primary transition-colors" onclick="togglePassword()">
<span class="material-symbols-outlined text-xl" id="eye-icon">visibility</span>
</button>
</div>
</div>
<div class="flex items-center justify-between">
<label class="flex items-center gap-2 cursor-pointer group">
<input name="remember" class="w-4 h-4 rounded text-primary focus:ring-primary border-slate-300" type="checkbox"/>
<span class="text-sm text-slate-600 group-hover:text-primary transition-colors">记住登录</span>
</label>
<a class="text-sm font-medium text-primary hover:text-primary-dark transition-colors" href="#">忘记密码？</a>
</div>
<button class="w-full bg-primary hover:bg-primary-dark text-white font-bold py-4 rounded-xl transition-all shadow-lg shadow-primary/20 transform active:scale-[0.98]" type="submit">
                    登录
                </button>
</form>
</div>
<p class="mt-8 text-center text-sm text-slate-500">
            还没有账号？
            <a class="font-bold text-primary hover:text-primary-dark transition-colors" href="register.php">申请成为合作伙伴</a>
</p>
<div class="mt-12 text-center">
<div class="flex justify-center gap-6 text-xs font-medium text-slate-400">
<a class="hover:text-primary transition-colors" href="#">隐私政策</a>
<a class="hover:text-primary transition-colors" href="#">服务协议</a>
<a class="hover:text-primary transition-colors" href="#">联系支持</a>
</div>
<p class="mt-4 text-[10px] text-slate-300">© 2024 MBTI Open API. 所有权归属核心测评团队。</p>
</div>
</div>

<script>
function togglePassword() {
    const passwordInput = document.getElementById('password');
    const eyeIcon = document.getElementById('eye-icon');
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        eyeIcon.textContent = 'visibility_off';
    } else {
        passwordInput.type = 'password';
        eyeIcon.textContent = 'visibility';
    }
}
</script>

</body></html>
