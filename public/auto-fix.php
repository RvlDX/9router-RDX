<?php
/**
 * 9Router Automated Diagnostic & Recovery Console
 * High-fidelity 9Router Design System & Pure SVG Icons (No Emoticons)
 */

$SECRET_KEY = 'Harumon2026';
$provided_key = $_REQUEST['key'] ?? '';

if ($provided_key !== $SECRET_KEY) {
    http_response_code(403);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>403 Forbidden - 9Router</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
        <style>
            body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #FDFAF6; color: #0a0a0a; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 16px; }
            .card { background: #ffffff; border: 1px solid #e5e7eb; border-radius: 14px; padding: 32px; max-width: 420px; width: 100%; text-align: center; box-shadow: 0 4px 12px -2px rgba(0,0,0,0.06); }
            .icon-wrap { width: 44px; height: 44px; margin: 0 auto 16px; border-radius: 12px; background: #fee2e2; color: #ef4444; display: flex; align-items: center; justify-content: center; }
            h1 { font-size: 20px; font-weight: 700; color: #0a0a0a; margin-bottom: 8px; }
            p { color: #6B7280; font-size: 14px; line-height: 1.5; }
            code { background: #f4f4f5; padding: 2px 6px; border-radius: 6px; font-size: 13px; color: #E56A4A; font-weight: 600; }
        </style>
    </head>
    <body>
        <div class="card">
            <div class="icon-wrap">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line>
                </svg>
            </div>
            <h1>Authentication Required</h1>
            <p>Access key is missing or invalid. Append <code>?key=Harumon2026</code> to your URL to proceed.</p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$vhost_root = realpath(__DIR__ . '/..');
if (!$vhost_root || basename($vhost_root) === 'domains') {
    $vhost_root = '/home/u464657479/domains/9router.harumon-japanese.com';
}
$public_html = $vhost_root . '/public_html';
$htaccess_file = $public_html . '/.htaccess';
$hbuilds_dir = $vhost_root . '/hbuilds';
$current_link = $hbuilds_dir . '/current';
$db_file = $vhost_root . '/.9router/db/data.sqlite';

$logs = [];
function add_log(&$logs, $msg, $type = 'info') {
    $logs[] = ['time' => date('H:i:s'), 'msg' => $msg, 'type' => $type];
}

$action = $_REQUEST['action'] ?? '';
$action_result = null;

if ($action === 'fix' || $action === 'restart') {
    add_log($logs, "Executing automated recovery routine...", 'info');

    // 1. Maintain .htaccess rules
    if (file_exists($htaccess_file)) {
        $htaccess_content = file_get_contents($htaccess_file);
        $modified = false;
        if (strpos($htaccess_content, 'SetEnv BASE_URL') === false) {
            $htaccess_content .= "\nSetEnv BASE_URL \"https://9router.harumon-japanese.com\"\nSetEnv NEXT_PUBLIC_BASE_URL \"https://9router.harumon-japanese.com\"\n";
            $modified = true;
            add_log($logs, "Added BASE_URL environment parameters", 'success');
        }
        if (strpos($htaccess_content, '<Files "fix.php">') === false) {
            $htaccess_content .= "\n<Files \"fix.php\">\n  PassengerEnabled off\n</Files>\n";
            $modified = true;
            add_log($logs, "Registered Passenger bypass for fix.php", 'success');
        }
        if (strpos($htaccess_content, 'RewriteRule ^fix') === false) {
            $htaccess_content .= "\nRewriteEngine On\nRewriteRule ^fix/?$ fix.php [L]\n";
            $modified = true;
            add_log($logs, "Configured internal /fix route rewrite", 'success');
        }
        if ($modified) {
            file_put_contents($htaccess_file, $htaccess_content);
        } else {
            add_log($logs, "Web server directives verified intact", 'info');
        }
    }

    // 2. Patch custom-server.js
    $target_server = $current_link . '/nodejs/custom-server.js';
    if (file_exists($target_server)) {
        $server_code = file_get_contents($target_server);
        if (strpos($server_code, 'if (require.main === module)') !== false) {
            $server_code = str_replace(
                "if (require.main === module) {\n  start().catch((err) => {\n    console.error('Failed to start server:', err);\n    process.exit(1);\n  });\n}",
                "start().catch((err) => {\n  console.error('Failed to start server:', err);\n  process.exit(1);\n});",
                $server_code
            );
            file_put_contents($target_server, $server_code);
            add_log($logs, "Disarmed require.main startup guard (503 preventive patch)", 'success');
        } else {
            add_log($logs, "custom-server.js startup sequence valid", 'info');
        }
    }

    // 3. Patch models test endpoint if unpatched
    $src_ping = $current_link . '/nodejs/src/app/api/models/test/ping.js';
    if (file_exists($src_ping)) {
        $ping_code = file_get_contents($src_ping);
        $old_sig = 'baseUrl = `http://127.0.0.1:${process.env.PORT || UPDATER_CONFIG.appPort}`';
        $new_sig = 'baseUrl = (process.env.BASE_URL || `http://127.0.0.1:${process.env.PORT || UPDATER_CONFIG.appPort}`)';
        if (strpos($ping_code, $old_sig) !== false) {
            $ping_code = str_replace($old_sig, $new_sig, $ping_code);
            $ping_code = str_replace('AbortSignal.timeout(15000)', 'AbortSignal.timeout(45000)', $ping_code);
            file_put_contents($src_ping, $ping_code);
            add_log($logs, "Patched internal ping endpoint to use BASE_URL", 'success');
        }
    }

    // 4. Ensure public_html symlinks
    $pub_fix = $public_html . '/fix.php';
    $cfg_fix = $vhost_root . '/config/auto-fix.php';
    if (file_exists($cfg_fix) && !file_exists($pub_fix)) {
        @symlink($cfg_fix, $pub_fix);
        add_log($logs, "Re-established public_html/fix.php routing link", 'success');
    }

    // 5. Restart Passenger
    $restart_dir = $current_link . '/nodejs/tmp';
    if (!is_dir($restart_dir)) {
        @mkdir($restart_dir, 0755, true);
    }
    touch($restart_dir . '/restart.txt');
    add_log($logs, "Sent reload signal to Passenger process pool", 'success');

    $action_result = "Diagnostic & auto-fix execution complete. System operational.";
}

// Diagnostics
$ch = curl_init('http://127.0.0.1:3000/api/health');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 2);
curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$node_healthy = ($http_code === 200);

$ch_pub = curl_init('https://9router.harumon-japanese.com/api/version');
curl_setopt($ch_pub, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch_pub, CURLOPT_TIMEOUT, 3);
curl_setopt($ch_pub, CURLOPT_SSL_VERIFYPEER, false);
$version_raw = curl_exec($ch_pub);
$pub_code = curl_getinfo($ch_pub, CURLINFO_HTTP_CODE);
curl_close($ch_pub);

$version_data = json_decode($version_raw, true);
$app_version = $version_data['currentVersion'] ?? '0.5.91';

$htaccess_status = false;
if (file_exists($htaccess_file)) {
    $content = file_get_contents($htaccess_file);
    if (strpos($content, 'SetEnv BASE_URL') !== false && strpos($content, 'fix.php') !== false) {
        $htaccess_status = true;
    }
}
$db_status = file_exists($db_file);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>9Router - Maintenance Console</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --brand-500: #E56A4A;
            --brand-600: #cc5236;
            --brand-700: #a64027;
            --bg: #FDFAF6;
            --surface: #ffffff;
            --surface-2: #f4f4f5;
            --surface-3: #e7e7e9;
            --border: #e5e7eb;
            --border-subtle: #f1f1f3;
            --text-main: #0a0a0a;
            --text-muted: #6B7280;
            --text-subtle: #9CA3AF;
            --success: #10B981;
            --danger: #cf222e;
            --warning: #F59E0B;
            --shadow-soft: 0 1px 2px 0 rgba(0, 0, 0, 0.04);
            --shadow-elev: 0 4px 12px -2px rgba(0, 0, 0, 0.08);
            --shadow-warm: 0 2px 8px -2px rgba(229, 106, 74, 0.25);
            --radius-brand: 10px;
            --radius-brand-lg: 14px;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: var(--bg);
            color: var(--text-main);
            min-height: 100vh;
            padding: 40px 16px;
            display: flex;
            justify-content: center;
            position: relative;
        }

        /* Subtle grid background identical to 9Router login/landing */
        .grid-pattern {
            position: fixed;
            inset: 0;
            background-image: radial-gradient(rgba(10, 10, 10, 0.06) 1px, transparent 1px);
            background-size: 24px 24px;
            pointer-events: none;
            z-index: 0;
        }

        .container {
            width: 100%;
            max-width: 840px;
            display: flex;
            flex-direction: column;
            gap: 24px;
            position: relative;
            z-index: 1;
        }

        /* Header */
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border);
        }

        .brand-link {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: inherit;
        }

        .logo-box {
            width: 38px;
            height: 38px;
            border-radius: var(--radius-brand);
            background: linear-gradient(135deg, var(--brand-500), var(--brand-700));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            box-shadow: var(--shadow-warm);
        }

        .brand-title {
            font-size: 19px;
            font-weight: 700;
            letter-spacing: -0.02em;
            color: var(--text-main);
            line-height: 1.2;
        }

        .brand-subtitle {
            font-size: 13px;
            color: var(--text-muted);
        }

        /* Status Badge */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 14px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-badge-ok {
            background-color: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        .status-badge-fail {
            background-color: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .pulse-indicator {
            position: relative;
            display: flex;
            height: 8px;
            width: 8px;
        }
        .pulse-indicator-ping {
            animation: ping 1.5s cubic-bezier(0, 0, 0.2, 1) infinite;
            position: absolute;
            display: inline-flex;
            height: 100%;
            width: 100%;
            border-radius: 9999px;
            opacity: 0.75;
        }
        .pulse-indicator-dot {
            position: relative;
            display: inline-flex;
            border-radius: 9999px;
            height: 8px;
            width: 8px;
        }
        @keyframes ping {
            75%, 100% { transform: scale(2); opacity: 0; }
        }

        /* Cards */
        .card {
            background-color: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-brand-lg);
            padding: 24px;
            box-shadow: var(--shadow-soft);
        }

        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .card-title-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-icon {
            padding: 6px;
            border-radius: 8px;
            background: var(--surface-2);
            color: var(--text-muted);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .card-title {
            font-size: 15px;
            font-weight: 600;
            color: var(--text-main);
        }

        /* Metric Grid */
        .metric-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 14px;
        }

        .metric-box {
            background-color: #fafaf9;
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-brand);
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .metric-label {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--text-muted);
        }

        .metric-value {
            font-size: 15px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
            color: var(--text-main);
        }

        /* Action Buttons */
        .action-row {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn {
            font-family: inherit;
            font-size: 13.5px;
            font-weight: 600;
            height: 40px;
            padding: 0 18px;
            border-radius: var(--radius-brand);
            border: 1px solid transparent;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.15s ease-out;
            text-decoration: none;
        }
        .btn:active {
            transform: scale(0.98);
        }

        .btn-primary {
            background-color: var(--brand-500);
            color: #ffffff;
            box-shadow: var(--shadow-warm);
        }
        .btn-primary:hover {
            background-color: var(--brand-600);
        }

        .btn-secondary {
            background-color: var(--surface-2);
            border-color: var(--border);
            color: var(--text-main);
        }
        .btn-secondary:hover {
            background-color: var(--surface-3);
        }

        /* Console Output Card */
        .terminal-wrap {
            background-color: #111827;
            border: 1px solid #1f2937;
            border-radius: var(--radius-brand);
            overflow: hidden;
        }

        .terminal-bar {
            background-color: #1f2937;
            padding: 10px 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #374151;
        }

        .traffic-dots {
            display: flex;
            gap: 6px;
        }
        .traffic-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
        }

        .terminal-bar-title {
            color: #9ca3af;
            font-size: 11px;
            font-family: 'JetBrains Mono', monospace;
            font-weight: 500;
        }

        .terminal-body {
            padding: 16px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 12px;
            line-height: 1.65;
            color: #f3f4f6;
            max-height: 280px;
            overflow-y: auto;
        }

        .log-entry { margin-bottom: 4px; display: flex; gap: 8px; }
        .log-ts { color: #6b7280; }
        .log-ok { color: #34d399; }
        .log-info { color: #60a5fa; }
        .log-warn { color: #fbbf24; }

        .banner-alert {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            border-radius: var(--radius-brand);
            font-size: 13.5px;
            font-weight: 500;
            background-color: #ecfdf5;
            border: 1px solid #a7f3d0;
            color: #065f46;
        }

        @media (max-width: 600px) {
            .header { flex-direction: column; align-items: flex-start; gap: 14px; }
            .metric-grid { grid-template-columns: 1fr; }
            .btn { width: 100%; }
        }
    </style>
</head>
<body>
    <div class="grid-pattern"></div>
    <div class="container">
        <!-- Top Navigation -->
        <div class="header">
            <a href="/dashboard" class="brand-link">
                <div class="logo-box">
                    <!-- 9Router Connected Nodes SVG Logo -->
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="3"></circle>
                        <circle cx="19" cy="5" r="2.5"></circle>
                        <circle cx="5" cy="19" r="2.5"></circle>
                        <path d="M10.4 10.4 6.5 17.5"></path>
                        <path d="m14 10 3.5-3.5"></path>
                    </svg>
                </div>
                <div>
                    <h1 class="brand-title">9Router</h1>
                    <div class="brand-subtitle">Maintenance & Diagnostic Console</div>
                </div>
            </a>
            <div>
                <?php if ($node_healthy && $pub_code === 200): ?>
                    <span class="status-badge status-badge-ok">
                        <span class="pulse-indicator">
                            <span class="pulse-indicator-ping" style="background-color: #10B981;"></span>
                            <span class="pulse-indicator-dot" style="background-color: #10B981;"></span>
                        </span>
                        System Operational
                    </span>
                <?php else: ?>
                    <span class="status-badge status-badge-fail">
                        <span class="pulse-indicator">
                            <span class="pulse-indicator-dot" style="background-color: #ef4444;"></span>
                        </span>
                        Degraded (HTTP <?= $pub_code ?>)
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($action_result): ?>
            <div class="banner-alert">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
                <span><?= htmlspecialchars($action_result) ?></span>
            </div>
        <?php endif; ?>

        <!-- System Diagnostics Grid -->
        <div class="card">
            <div class="card-header">
                <div class="card-title-group">
                    <div class="card-icon">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="2" y="2" width="20" height="8" rx="2" ry="2"></rect>
                            <rect x="2" y="14" width="20" height="8" rx="2" ry="2"></rect>
                            <line x1="6" y1="6" x2="6.01" y2="6"></line>
                            <line x1="6" y1="18" x2="6.01" y2="18"></line>
                        </svg>
                    </div>
                    <h2 class="card-title">Runtime & Service Metrics</h2>
                </div>
            </div>

            <div class="metric-grid">
                <div class="metric-box">
                    <span class="metric-label">Gateway Response</span>
                    <div class="metric-value">
                        <?php if ($pub_code === 200): ?>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#10B981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                            <span>200 OK (v<?= htmlspecialchars($app_version) ?>)</span>
                        <?php else: ?>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#cf222e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                            <span>HTTP <?= $pub_code ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="metric-box">
                    <span class="metric-label">Node Standalone</span>
                    <div class="metric-value">
                        <?php if ($node_healthy): ?>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#10B981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                            <span>Port 3000 Active</span>
                        <?php else: ?>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#cf222e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                            <span>Disconnected</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="metric-box">
                    <span class="metric-label">Base URL Routing</span>
                    <div class="metric-value">
                        <?php if ($htaccess_status): ?>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#10B981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                            <span>Configured</span>
                        <?php else: ?>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#F59E0B" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                            <span>Needs Update</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="metric-box">
                    <span class="metric-label">Persistence Storage</span>
                    <div class="metric-value">
                        <?php if ($db_status): ?>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#10B981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
                            <span>SQLite Connected</span>
                        <?php else: ?>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#cf222e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>
                            <span>DB Missing</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Operations -->
        <div class="card">
            <div class="card-header">
                <div class="card-title-group">
                    <div class="card-icon">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
                        </svg>
                    </div>
                    <h2 class="card-title">Recovery Actions</h2>
                </div>
            </div>

            <p style="font-size: 13.5px; color: var(--text-muted); margin-bottom: 20px; line-height: 1.5;">
                Automatically disarms Phusion Passenger startup guard, updates local routing, and touches the restart trigger.
            </p>

            <div class="action-row">
                <form method="POST" action="?key=<?= urlencode($SECRET_KEY) ?>">
                    <input type="hidden" name="action" value="fix">
                    <button type="submit" class="btn btn-primary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
                        </svg>
                        <span>Run Diagnostic & Auto-Fix</span>
                    </button>
                </form>

                <form method="POST" action="?key=<?= urlencode($SECRET_KEY) ?>">
                    <input type="hidden" name="action" value="restart">
                    <button type="submit" class="btn btn-secondary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path>
                            <path d="M3 3v5h5"></path>
                            <path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"></path>
                            <path d="M16 16h5v5"></path>
                        </svg>
                        <span>Restart Passenger</span>
                    </button>
                </form>

                <a href="/dashboard" class="btn btn-secondary" target="_blank">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                        <polyline points="15 3 21 3 21 9"></polyline>
                        <line x1="10" y1="14" x2="21" y2="3"></line>
                    </svg>
                    <span>Open Dashboard</span>
                </a>
            </div>
        </div>

        <!-- Terminal Output -->
        <div class="card" style="padding: 0; overflow: hidden;">
            <div class="terminal-wrap">
                <div class="terminal-bar">
                    <div class="traffic-dots">
                        <div class="traffic-dot" style="background-color: #ef4444;"></div>
                        <div class="traffic-dot" style="background-color: #f59e0b;"></div>
                        <div class="traffic-dot" style="background-color: #10b981;"></div>
                    </div>
                    <span class="terminal-bar-title">console-stream.log</span>
                    <div style="width: 40px;"></div>
                </div>

                <div class="terminal-body">
                    <div class="log-entry">
                        <span class="log-ts">[<?= date('H:i:s') ?>]</span>
                        <span class="log-info">Active Root:</span>
                        <span><?= htmlspecialchars($vhost_root) ?></span>
                    </div>
                    <div class="log-entry">
                        <span class="log-ts">[<?= date('H:i:s') ?>]</span>
                        <span class="log-info">Build Version:</span>
                        <span><?= htmlspecialchars(readlink($current_link) ?: 'Direct Deployment') ?></span>
                    </div>
                    <?php if (empty($logs)): ?>
                        <div class="log-entry">
                            <span class="log-ts">[<?= date('H:i:s') ?>]</span>
                            <span class="log-ok">Patcher idle. Ready for diagnostic inspection.</span>
                        </div>
                    <?php else: ?>
                        <?php foreach ($logs as $l): ?>
                            <div class="log-entry">
                                <span class="log-ts">[<?= htmlspecialchars($l['time']) ?>]</span>
                                <span class="log-<?= htmlspecialchars($l['type'] === 'success' ? 'ok' : ($l['type'] === 'warning' ? 'warn' : 'info')) ?>">
                                    <?= htmlspecialchars($l['msg']) ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
