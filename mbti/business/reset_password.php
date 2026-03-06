<?php
require_once 'db.php';

startSession();

$error = '';
$success = '';

// 检查session中是否有重置用户ID
if (!isset($_SESSION['reset_user_id'])) {
    header('Location: forgot_password.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($new_password)) {
        $error = '请输入新密码';
    } elseif (strlen($new_password) < 8) {
        $error = '密码长度至少8位';
    } elseif (!preg_match('/[A-Za-z]/', $new_password) || !preg_match('/[0-9]/', $new_password)) {
        $error = '密码必须包含字母和数字';
    } elseif ($new_password !== $confirm_password) {
        $error = '两次输入的密码不一致';
    } else {
        $pdo = getDB();
        $company_id = $_SESSION['reset_user_id'];

        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE companies SET password_hash = ? WHERE id = ?");
        $stmt->execute([$new_hash, $company_id]);

        // 清除session
        unset($_SESSION['reset_user_id']);

        $success = '密码重置成功';
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>MBTI 开放平台 - 设置新密码</title>
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
        .password-card {
            @apply bg-white rounded-3xl shadow-[0_20px_50px_rgba(122,148,120,0.1)];
        }
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            display: inline-block;
            vertical-align: middle;
            line-height: 1;
        }
        .input-group:focus-within .icon-lead {
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
</div>
<div class="password-card p-10">
<div class="mb-8 text-center sm:text-left">
<h2 class="text-2xl font-bold text-slate-800">设置新密码</h2>
<p class="text-sm text-slate-500 mt-2 leading-relaxed">为了您的账号安全，请设置一个强密码。</p>
</div>

<?php if ($error): ?>
<div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-xl text-red-600 text-sm">
<?php echo htmlspecialchars($error); ?>
</div>
<?php endif; ?>

<?php if ($success): ?>
<div class="mb-4 p-3 bg-green-50 border border-green-200 rounded-xl text-green-600 text-sm">
<?php echo htmlspecialchars($success); ?>
<a href="login.php" class="underline font-bold">立即登录</a>
</div>
<?php else: ?>

<form method="POST" class="space-y-6">
<div class="space-y-2">
<label class="text-sm font-semibold text-slate-700 ml-1">新密码</label>
<div class="input-group relative flex items-center">
<span class="material-symbols-outlined absolute left-4 text-slate-400 icon-lead">lock</span>
<input name="new_password" class="w-full bg-slate-50 border-none rounded-xl py-3.5 pl-12 pr-12 text-sm focus:ring-2 focus:ring-primary/20 focus:bg-white transition-all outline-none text-slate-700" placeholder="请输入新密码" type="password"/>
</div>
</div>
<div class="space-y-2">
<label class="text-sm font-semibold text-slate-700 ml-1">确认新密码</label>
<div class="input-group relative flex items-center">
<span class="material-symbols-outlined absolute left-4 text-slate-400 icon-lead">lock</span>
<input name="confirm_password" class="w-full bg-slate-50 border-none rounded-xl py-3.5 pl-12 pr-12 text-sm focus:ring-2 focus:ring-primary/20 focus:bg-white transition-all outline-none text-slate-700" placeholder="请再次输入新密码" type="password"/>
</div>
</div>
<p class="text-[13px] text-slate-400 leading-relaxed ml-1">密码长度需在 8-16 位之间，包含字母和数字。</p>
<button class="w-full bg-primary hover:bg-primary-dark text-white font-bold py-4 rounded-xl transition-all shadow-lg shadow-primary/20 transform active:scale-[0.98] mt-4" type="submit">提交并登录</button>
</form>

<?php endif; ?>

<div class="mt-8 pt-6 border-t border-slate-50 flex justify-center">
<a class="flex items-center gap-2 text-sm font-medium text-primary hover:text-primary-dark transition-colors group" href="forgot_password.php">
<span class="material-symbols-outlined text-lg transition-transform group-hover:-translate-x-1">arrow_back</span>
返回上一步
</a>
</div>
</div>
<div class="mt-12 text-center">
<div class="flex justify-center gap-6 text-xs font-medium text-slate-400">
<a class="hover:text-primary transition-colors" href="#">隐私政策</a>
<a class="hover:text-primary transition-colors" href="#">服务协议</a>
<a class="hover:text-primary transition-colors" href="#">联系支持</a>
</div>
<p class="mt-4 text-[10px] text-slate-300 uppercase tracking-wider">© 2024 MBTI Open API. 所有权归属核心测评团队。</p>
</div>
</div>

</body>
</html>
