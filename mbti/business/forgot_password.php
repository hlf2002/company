<?php
require_once 'db.php';

startSession();

$error = '';
$success = '';
$step = 1; // 1: 验证账号, 2: 设置新密码

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'verify') {
        // 步骤1: 验证账号和验证码
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $code = trim($_POST['code'] ?? '');

        if (empty($phone) && empty($email)) {
            $error = '请输入手机号或邮箱';
        } elseif (empty($code)) {
            $error = '请输入验证码';
        } else {
            $pdo = getDB();

            // 查找用户
            $stmt = $pdo->prepare("SELECT id FROM companies WHERE phone = ? OR email = ?");
            $stmt->execute([$phone ?: $email, $email ?: $phone]);
            $user = $stmt->fetch();

            if (!$user) {
                $error = '该账号未注册';
            } elseif (!verifyCode($phone ?: $email, $code)) {
                $error = '验证码错误或已过期';
            } else {
                // 验证成功，保存用户ID到session，跳转到设置新密码页面
                $_SESSION['reset_user_id'] = $user['id'];
                header('Location: reset_password.php');
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>MBTI 开放平台 - 重置密码</title>
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
        .forgot-password-card {
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
</div>
<div class="forgot-password-card p-10">
<div class="mb-8">
<h2 class="text-2xl font-bold text-slate-800">重置密码</h2>
<p class="text-sm text-slate-500 mt-2 leading-relaxed">请输入您注册时使用的手机号或邮箱，我们将为您发送验证码。</p>
</div>

<?php if ($error): ?>
<div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-xl text-red-600 text-sm">
<?php echo htmlspecialchars($error); ?>
</div>
<?php endif; ?>

<form method="POST" class="space-y-6">
<input type="hidden" name="action" value="verify"/>
<div class="space-y-2">
<label class="text-sm font-semibold text-slate-700 ml-1">手机号 / 邮箱</label>
<div class="input-group relative flex items-center">
<span class="material-symbols-outlined absolute left-4 text-slate-400">mail</span>
<input name="phone" class="w-full bg-slate-50 border-none rounded-xl py-3.5 pl-12 pr-4 text-sm focus:ring-2 focus:ring-primary/20 focus:bg-white transition-all outline-none text-slate-700" placeholder="请输入手机号或邮箱" type="text"/>
</div>
</div>
<div class="space-y-2">
<label class="text-sm font-semibold text-slate-700 ml-1">验证码</label>
<div class="flex gap-3">
<div class="input-group relative flex flex-grow items-center">
<span class="material-symbols-outlined absolute left-4 text-slate-400">verified_user</span>
<input name="code" class="w-full bg-slate-50 border-none rounded-xl py-3.5 pl-12 pr-4 text-sm focus:ring-2 focus:ring-primary/20 focus:bg-white transition-all outline-none text-slate-700" placeholder="验证码" type="text"/>
</div>
<button class="whitespace-now600 px-4 py-3.5 text-sm font-medium text-primary bg-primary/5 hover:bg-primary/10 rounded-xl transition-colors border border-primary/20" type="button" onclick="sendCode()">获取验证码</button>
</div>
</div>
<button class="w-full bg-primary hover:bg-primary-dark text-white font-bold py-4 rounded-xl transition-all shadow-lg shadow-primary/20 transform active:scale-[0.98] mt-4" type="submit">下一步</button>
</form>
<div class="mt-8 pt-6 border-t border-slate-50 flex justify-center">
<a class="flex items-center gap-2 text-sm font-medium text-primary hover:text-primary-dark transition-colors group" href="login.php">
<span class="material-symbols-outlined text-lg transition-transform group-hover:-translate-x-1">arrow_back</span>
返回登录
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

<script>
let countdown = 0;
function sendCode() {
    const phoneInput = document.querySelector('input[name="phone"]');
    const phone = phoneInput.value.trim();

    if (!phone) {
        alert('请输入手机号或邮箱');
        return;
    }

    if (countdown > 0) return;

    fetch('send_code.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'phone=' + encodeURIComponent(phone)
    })
    .then(r => r.json())
    .then(data => {
        if (data.error) {
            alert(data.error);
        } else {
            alert('验证码已发送');
            countdown = 60;
            const btn = document.querySelector('button[onclick="sendCode()"]');
            btn.textContent = countdown + '秒后重发';
            const timer = setInterval(() => {
                countdown--;
                btn.textContent = countdown + '秒后重发';
                if (countdown <= 0) {
                    clearInterval(timer);
                    btn.textContent = '获取验证码';
                }
            }, 1000);
        }
    })
    .catch(err => {
        alert('发送失败，请稍后重试');
    });
}
</script>

</body>
</html>
