<?php
require_once 'db.php';
requireLogin();

$user = getCurrentUser();
$company_id = $_SESSION['company_id'];

$pdo = getDB();

// 获取今日消耗
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(credits_used), 0) as today_count, COALESCE(SUM(amount), 0) as today_amount
    FROM usage_logs
    WHERE company_id = ? AND DATE(created_at) = CURDATE()
");
$stmt->execute([$company_id]);
$today = $stmt->fetch();

// 获取本月消耗
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(credits_used), 0) as month_count, COALESCE(SUM(amount), 0) as month_amount
    FROM usage_logs
    WHERE company_id = ? AND YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())
");
$stmt->execute([$company_id]);
$month = $stmt->fetch();

// 获取余额
$stmt = $pdo->prepare("SELECT balance FROM balances WHERE company_id = ?");
$stmt->execute([$company_id]);
$balance_row = $stmt->fetch();
$balance = $balance_row ? $balance_row['balance'] : 0;

// 获取近期充值记录
$stmt = $pdo->prepare("
    SELECT order_no, amount, status, created_at, paid_at
    FROM recharges
    WHERE company_id = ?
    ORDER BY created_at DESC
    LIMIT 5
");
$stmt->execute([$company_id]);
$recharges = $stmt->fetchAll();

// 获取API密钥
$stmt = $pdo->prepare("SELECT id, key_value, key_name, is_active, created_at FROM api_keys WHERE company_id = ?");
$stmt->execute([$company_id]);
$api_keys = $stmt->fetchAll();

// 获取最近30天的消耗趋势
$stmt = $pdo->prepare("
    SELECT DATE(created_at) as date, SUM(credits_used) as count
    FROM usage_logs
    WHERE company_id = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date
");
$stmt->execute([$company_id]);
$trend_data = $stmt->fetchAll();

// 构建趋势数据
$dates = [];
$counts = [];
$last_30_days = [];
for ($i = 29; $i >= 0; $i--) {
    $last_30_days[] = date('Y-m-d', strtotime("-{$i} days"));
}

foreach ($last_30_days as $date) {
    $dates[] = date('m-d', strtotime($date));
    $found = false;
    foreach ($trend_data as $row) {
        if ($row['date'] === $date) {
            $counts[] = (int)$row['count'];
            $found = true;
            break;
        }
    }
    if (!$found) {
        $counts[] = 0;
    }
}

$dates_json = json_encode($dates);
$counts_json = json_encode($counts);
?>
<!DOCTYPE html>
<html lang="zh-CN"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>MBTI 用户中心 - B端管理后台</title>
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
        .chart-line {
            stroke-dasharray: 1000;
            stroke-dashoffset: 0;
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
<a class="flex items-center gap-3 px-4 py-3 rounded-xl active-nav transition-colors" href="index.php">
<span class="material-symbols-outlined">dashboard</span>
<span class="text-sm">控制台</span>
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
<a class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-white/50 transition-colors group" href="settings.php">
<span class="material-symbols-outlined">settings</span>
<span class="text-sm font-medium opacity-80 group-hover:opacity-100">账号设置</span>
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
<div class="flex justify-between items-end">
<div>
<h2 class="text-2xl font-bold text-gray-800">控制台概览</h2>
<p class="text-gray-500 text-sm mt-1">欢迎回来，这是您目前的消耗统计与账号状态</p>
</div>
<div class="flex gap-3">
<button class="flex items-center gap-2 px-6 py-2.5 bg-sage-green text-white font-bold text-sm rounded-xl shadow-lg shadow-sage-green/20 hover:opacity-90 transition-all">
<span class="material-symbols-outlined !text-white text-lg">add_card</span>
                        立即充值
                    </button>
</div>
</div>
<div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-4">
<div class="bg-white p-5 rounded-2xl custom-shadow border border-white">
<p class="text-gray-400 text-[11px] font-bold uppercase tracking-wider">今日消耗次数</p>
<div class="flex items-baseline gap-1 mt-2">
<h3 class="text-2xl font-bold text-gray-800"><?php echo $today['today_count']; ?></h3>
<span class="text-xs text-sage-green font-bold">次</span>
</div>
</div>
<div class="bg-white p-5 rounded-2xl custom-shadow border border-white">
<p class="text-gray-400 text-[11px] font-bold uppercase tracking-wider">今日消耗金额</p>
<div class="flex items-baseline gap-1 mt-2">
<h3 class="text-2xl font-bold text-gray-800">¥<?php echo number_format($today['today_amount'], 2); ?></h3>
</div>
</div>
<div class="bg-white p-5 rounded-2xl custom-shadow border border-white">
<p class="text-gray-400 text-[11px] font-bold uppercase tracking-wider">本月消耗次数</p>
<div class="flex items-baseline gap-1 mt-2">
<h3 class="text-2xl font-bold text-gray-800"><?php echo $month['month_count']; ?></h3>
<span class="text-xs text-sage-green font-bold">次</span>
</div>
</div>
<div class="bg-white p-5 rounded-2xl custom-shadow border border-white">
<p class="text-gray-400 text-[11px] font-bold uppercase tracking-wider">本月消耗金额</p>
<div class="flex items-baseline gap-1 mt-2">
<h3 class="text-2xl font-bold text-gray-800">¥<?php echo number_format($month['month_amount'], 2); ?></h3>
</div>
</div>
<div class="bg-white p-5 rounded-2xl custom-shadow border-2 border-sage-green/30 bg-sage-light/10">
<p class="text-sage-green text-[11px] font-bold uppercase tracking-wider">剩余余额</p>
<div class="flex items-baseline gap-1 mt-2">
<h3 class="text-2xl font-bold text-sage-green">¥<?php echo number_format($balance, 2); ?></h3>
</div>
</div>
</div>
<div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
<div class="lg:col-span-3 bg-white p-8 rounded-3xl custom-shadow border border-white">
<div class="flex justify-between items-center mb-8">
<div>
<h3 class="text-lg font-bold text-gray-800">每日消耗次数</h3>
<p class="text-gray-400 text-xs">最近 30 天消耗趋势</p>
</div>
<div class="flex items-center gap-4">
<div class="flex items-center gap-2">
<div class="size-2 rounded-full bg-sage-green"></div>
<span class="text-xs font-medium text-gray-500">消耗次数</span>
</div>
</div>
</div>
<div class="relative h-64 w-full mt-4">
<svg class="w-full h-full overflow-visible" preserveAspectRatio="none" viewBox="0 0 1000 200" id="chart-svg">
<path class="chart-line" d="" fill="none" stroke="var(--sage-green)" stroke-width="3"></path>
</svg>
<div class="absolute bottom-0 left-0 w-full flex justify-between pt-4 text-[10px] text-gray-400 font-bold px-2" id="chart-labels">
</div>
</div>
</div>
<div class="space-y-6">
<div class="bg-white p-6 rounded-3xl custom-shadow border border-white">
<h3 class="text-sm font-bold text-gray-800 mb-4 border-b border-warm-beige pb-3">便捷操作</h3>
<div class="space-y-3">
<a href="settings.php" class="w-full flex items-center justify-between p-4 bg-warm-beige/50 rounded-2xl hover:bg-sage-light/30 transition-all group">
<div class="flex items-center gap-3">
<span class="material-symbols-outlined">lock_reset</span>
<span class="text-sm font-bold text-gray-600">修改密码</span>
</div>
<span class="material-symbols-outlined text-gray-300 group-hover:text-sage-green text-lg">chevron_right</span>
</a>
<a href="settings.php" class="w-full flex items-center justify-between p-4 bg-warm-beige/50 rounded-2xl hover:bg-sage-light/30 transition-all group">
<div class="flex items-center gap-3">
<span class="material-symbols-outlined">api</span>
<span class="text-sm font-bold text-gray-600">密钥管理</span>
</div>
<span class="material-symbols-outlined text-gray-300 group-hover:text-sage-green text-lg">chevron_right</span>
</a>
</div>
</div>
<div class="bg-terracotta-light/30 p-6 rounded-3xl border border-terracotta/10">
<div class="flex items-center gap-3 mb-3">
<span class="material-symbols-outlined !text-terracotta">help_center</span>
<h4 class="text-sm font-bold text-terracotta">需要技术支持？</h4>
</div>
<p class="text-xs text-gray-500 leading-relaxed mb-4">如果您在接口集成或充值过程中遇到任何问题，请随时联系您的专属大客户经理。</p>
<button class="text-xs font-bold text-terracotta underline">查看开发文档</button>
</div>
</div>
</div>
</div>
<div class="bg-white rounded-3xl custom-shadow border border-white overflow-hidden">
<div class="px-8 py-5 border-b border-warm-beige flex justify-between items-center">
<h3 class="font-bold text-gray-800">近期充值记录</h3>
<button class="text-xs font-bold text-sage-green hover:underline">查看全部</button>
</div>
<?php if (empty($recharges)): ?>
<div class="p-8 text-center text-gray-400">暂无充值记录</div>
<?php else: ?>
<table class="w-full text-left">
<thead>
<tr class="bg-sage-light/30">
<th class="px-8 py-4 text-[10px] font-bold text-sage-green uppercase tracking-widest">流水号</th>
<th class="px-8 py-4 text-[10px] font-bold text-sage-green uppercase tracking-widest">时间</th>
<th class="px-8 py-4 text-[10px] font-bold text-sage-green uppercase tracking-widest">金额</th>
<th class="px-8 py-4 text-[10px] font-bold text-sage-green uppercase tracking-widest">状态</th>
</tr>
</thead>
<tbody class="divide-y divide-warm-beige">
<?php foreach ($recharges as $row): ?>
<tr>
<td class="px-8 py-5 text-sm font-mono text-gray-500"><?php echo htmlspecialchars($row['order_no']); ?></td>
<td class="px-8 py-5 text-sm text-gray-500"><?php echo $row['paid_at'] ? date('Y-m-d H:i', strtotime($row['paid_at'])) : '-'; ?></td>
<td class="px-8 py-5 text-sm font-bold text-gray-800">¥<?php echo number_format($row['amount'], 2); ?></td>
<td class="px-8 py-5">
<?php
$status_class = $row['status'] === 'success' ? 'bg-sage-light text-sage-green' : ($row['status'] === 'pending' ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700');
$status_text = $row['status'] === 'success' ? '成功' : ($row['status'] === 'pending' ? '待支付' : '失败');
?>
<span class="px-3 py-1 rounded-full <?php echo $status_class; ?> text-[10px] font-bold"><?php echo $status_text; ?></span>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>
</div>
</div>
<div class="p-10 mt-auto text-center">
<p class="text-[10px] text-gray-400 uppercase tracking-widest font-bold">© 2024 MBTI Personality Analysis Platform - B-side Partner Console</p>
</div>
</main>
</div>

<script>
const dates = <?php echo $dates_json; ?>;
const counts = <?php echo $counts_json; ?>;

// 生成图表路径
function generatePath(data) {
    if (data.length === 0) return '';
    const max = Math.max(...data, 1);
    const min = Math.min(...data, 0);
    const range = max - min || 1;
    const width = 1000;
    const height = 200;
    const padding = 20;

    let path = '';
    data.forEach((val, i) => {
        const x = (i / (data.length - 1)) * width;
        const y = height - padding - ((val - min) / range) * (height - 2 * padding);
        if (i === 0) {
            path += `M${x},${y}`;
        } else {
            path += ` T${x},${y}`;
        }
    });
    return path;
}

document.addEventListener('DOMContentLoaded', function() {
    const path = generatePath(counts);
    document.querySelector('.chart-line').setAttribute('d', path);

    // 生成标签
    const labelsContainer = document.getElementById('chart-labels');
    if (dates.length > 0) {
        const step = Math.ceil(dates.length / 4);
        for (let i = 0; i < dates.length; i += step) {
            const span = document.createElement('span');
            span.textContent = dates[i];
            labelsContainer.appendChild(span);
        }
    }
});
</script>

</body></html>
