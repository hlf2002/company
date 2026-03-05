<?php
require_once 'db.php';

startSession();

if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $company_name = trim($_POST['company_name'] ?? '');
    $contact_name = trim($_POST['contact_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $code = trim($_POST['code'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($company_name)) {
        $error = '请输入企业名称';
    } elseif (empty($contact_name)) {
        $error = '请输入联系人姓名';
    } elseif (empty($phone)) {
        $error = '请输入手机号';
    } elseif (empty($email)) {
        $error = '请输入工作邮箱';
    } elseif (empty($code)) {
        $error = '请输入验证码';
    } elseif (empty($password)) {
        $error = '请设置密码';
    } elseif (strlen($password) < 8) {
        $error = '密码长度至少8位';
    } elseif (!isset($_POST['terms'])) {
        $error = '请阅读并同意服务协议和隐私政策';
    } else {
        $pdo = getDB();

        $stmt = $pdo->prepare("SELECT id FROM companies WHERE phone = ?");
        $stmt->execute([$phone]);
        if ($stmt->fetch()) {
            $error = '该手机号已注册';
        } else {
            $stmt = $pdo->prepare("SELECT id FROM companies WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = '该邮箱已注册';
            } else {
                try {
                    $pdo->beginTransaction();

                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO companies (company_name, phone, email, password_hash, status) VALUES (?, ?, ?, ?, 1)");
                    $stmt->execute([$company_name, $phone, $email, $password_hash]);
                    $company_id = $pdo->lastInsertId();

                    $stmt = $pdo->prepare("INSERT INTO balances (company_id, balance, total_recharged, total_used) VALUES (?, 0, 0, 0)");
                    $stmt->execute([$company_id]);

                    $api_key = generateApiKey();
                    $stmt = $pdo->prepare("INSERT INTO api_keys (company_id, key_value, key_name, is_active) VALUES (?, ?, '默认密钥', 1)");
                    $stmt->execute([$company_id, $api_key]);

                    $pdo->commit();

                    $success = '注册成功！请登录';
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = '注册失败，请稍后重试';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>MBTI 开放平台 - B端注册</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&amp;family=Noto+Sans+SC:wght@400;500;700&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<script id="tailwind-config">
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        "primary": "#7A9478",
                        "primary-dark": "#5F745D",
                        "background-warm": "#F9F7F2",
                        "accent-warm": "#E8E4D9",
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
                @apply bg-background-warm font-sans text-slate-800;
            }
        }
        .form-input-custom {
            @apply w-full border-slate-200 rounded-lg py-3 px-4 text-sm focus:ring-primary focus:border-primary transition-all bg-white;
        }
        .illustration-bg {
            background: linear-gradient(135deg, #7A9478 0%, #5F745D 100%);
        }
    </style>
</head>
<body class="min-h-screen flex overflow-hidden">
<div class="hidden lg:flex flex-1 relative overflow-hidden illustration-bg items-center justify-center p-12" style="max-width: 600px;">
<div class="absolute inset-0 opacity-10 pointer-events-none">
<svg height="100%" width="100%" xmlns="http://www.w3.org/2000/svg">
<defs>
<pattern height="40" id="grid" patternUnits="userSpaceOnUse" width="40">
<path d="M 40 0 L 0 0 0 40" fill="none" stroke="white" stroke-width="1"></path>
</pattern>
</defs>
<rect fill="url(#grid)" height="100%" width="100%"></rect>
</svg>
</div>
<div class="z-10 max-w-xl text-white">
<div class="flex items-center gap-3 mb-8">
<div class="flex h-10 w-10 items-center justify-center rounded-xl bg-white text-primary">
<span class="material-symbols-outlined text-2xl font-bold">psychology</span>
</div>
<h1 class="text-2xl font-bold tracking-tight">MBTI 开放平台</h1>
</div>
<div class="mb-8">
<h2 class="text-4xl font-bold leading-tight mb-4">Insight into Talent</h2>
<p class="text-lg text-white/80 leading-relaxed">洞察人才潜能，赋能团队成长。</p>
</div>
<div class="relative w-full aspect-video rounded-3xl overflow-hidden shadow-2xl bg-white/5 border border-white/10 flex items-center justify-center">
<span class="material-symbols-outlined text-7xl text-white/90">group_add</span>
<p class="text-lg font-medium absolute bottom-4">加入 500+ 先进企业的选择</p>
</div>
</div>
</div>
</div>
<main class="w-full lg:w-[600px] bg-background-warm flex items-center justify-center p-6 md:p-12 overflow-y-auto">
<div class="w-full max-w-md bg-white rounded-3xl shadow-xl shadow-primary/5 p-8 md:p-10 border border-slate-100">
<div class="mb-8">
<h3 class="text-2xl font-bold text-slate-900 mb-2">成为合作伙伴</h3>
<p class="text-slate-500">解锁专业测评能力，助力企业与团队成长。</p>
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
<?php endif; ?>

<form method="POST" class="space-y-5">
<div>
<label class="block text-sm font-semibold text-slate-700 mb-1.5">企业名称</label>
<input name="company_name" class="form-input-custom" placeholder="请输入您的企业全称" required="" type="text" value="<?php echo htmlspecialchars($_POST['company_name'] ?? ''); ?>"/>
</div>
<div>
<label class="block text-sm font-semibold text-slate-700 mb-1.5">联系人姓名</label>
<input name="contact_name" class="form-input-custom" placeholder="您的称呼" required="" type="text" value="<?php echo htmlspecialchars($_POST['contact_name'] ?? ''); ?>"/>
</div>
<div>
<label class="block text-sm font-semibold text-slate-700 mb-1.5">工作邮箱</label>
<input name="email" class="form-input-custom" placeholder="email@company.com" required="" type="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"/>
</div>
<div>
<label class="block text-sm font-semibold text-slate-700 mb-1.5">手机号码</label>
<div class="flex gap-2">
<input name="phone" class="form-input-custom flex-1" placeholder="请输入手机号" required="" type="tel" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>"/>
<button class="px-4 py-2 text-sm font-semibold text-primary border border-primary/20 rounded-lg hover:bg-primary/5 transition-colors whitespace-nowrap" type="button">获取验证码</button>
</div>
</div>
<div>
<label class="block text-sm font-semibold text-slate-700 mb-1.5">验证码</label>
<input name="code" class="form-input-custom" placeholder="6位数字验证码" required="" type="text"/>
</div>
<div>
<label class="block text-sm font-semibold text-slate-700 mb-1.5">设置密码</label>
<input name="password" class="form-input-custom" placeholder="不少于8位，包含字母与数字" required="" type="password"/>
</div>
<div class="flex items-start gap-3 py-2">
<input name="terms" class="mt-1 h-4 w-4 rounded border-slate-300 text-primary focus:ring-primary" id="terms" type="checkbox"/>
<label class="text-xs text-slate-500 leading-tight" for="terms">我已阅读并同意 <a class="text-primary hover:underline" href="#">《服务协议》</a> 及 <a class="text-primary hover:underline" href="#">《隐私政策》</a></label>
</div>
<button class="w-full bg-primary hover:bg-primary-dark text-white font-bold py-3.5 rounded-xl transition-all shadow-lg shadow-primary/20 flex items-center justify-center gap-2 mt-4" type="submit">
<span>立即注册</span>
<span class="material-symbols-outlined text-lg">arrow_forward</span>
</button>
</form>
<div class="mt-8 text-center">
<p class="text-sm text-slate-500">已有账号？ <a class="text-primary font-bold hover:underline" href="login.php">立即登录</a></p>
</div>
<div class="mt-8 pt-6 border-t border-slate-50 flex items-center justify-center gap-6 text-slate-400">
<div class="flex items-center gap-1.5">
<span class="material-symbols-outlined text-base">verified</span>
<span class="text-[10px] font-medium uppercase tracking-wider">Secure Access</span>
</div>
<div class="flex items-center gap-1.5">
<span class="material-symbols-outlined text-base">support_agent</span>
<span class="text-[10px] font-medium uppercase tracking-wider">Expert Support</span>
</div>
</div>
</div>
</main>

</body></html>
