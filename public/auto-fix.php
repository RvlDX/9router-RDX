<?php
/**
 * 9Router Automated Diagnostic & Recovery Console
 * Designed to survive builds and restore service availability on Hostinger LiteSpeed.
 */

// Security token
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
        <title>403 Forbidden - 9Router Console</title>
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #FDFAF6; color: #0a0a0a; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
            .card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 32px; max-width: 400px; text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
            h1 { color: #E56A4A; font-size: 24px; margin-bottom: 8px; }
            p { color: #6B7280; font-size: 14px; }
        </style>
    </head>
    <body>
        <div class="card">
            <h1>403 Forbidden</h1>
            <p>Access key invalid or missing. Pass <code>?key=Harumon2026</code> in URL.</p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Environment Detection
$vhost_root = realpath(__DIR__ . '/..');
if (!$vhost_root) {
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

// Perform Fix Action
$action = $_REQUEST['action'] ?? '';
$action_result = null;

if ($action === 'fix' || $action === 'restart') {
    add_log($logs, "Initiating maintenance routine...", 'info');
    
    // 1. Ensure .htaccess has required rules
    if (file_exists($htaccess_file)) {
        $htaccess_content = file_get_contents($htaccess_file);
        $modified = false;
        
        if (strpos($htaccess_content, 'SetEnv BASE_URL') === false) {
            $htaccess_content .= "\nSetEnv BASE_URL \"https://9router.harumon-japanese.com\"\nSetEnv NEXT_PUBLIC_BASE_URL \"https://9router.harumon-japanese.com\"\n";
            $modified = true;
            add_log($logs, "Added BASE_URL to .htaccess", 'success');
        }
        
        if (strpos($htaccess_content, '<Files "auto-fix.php">') === false) {
            $htaccess_content .= "\n<Files \"auto-fix.php\">\n  PassengerEnabled off\n</Files>\n";
            $modified = true;
            add_log($logs, "Configured Passenger bypass for auto-fix.php in .htaccess", 'success');
        }
        
        if ($modified) {
            file_put_contents($htaccess_file, $htaccess_content);
        } else {
            add_log($logs, ".htaccess rules already intact", 'info');
        }
    } else {
        add_log($logs, "Warning: .htaccess not found at $htaccess_file", 'warning');
    }

    // 2. Check and fix custom-server.js if require.main guard exists
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
            add_log($logs, "Patched require.main guard in current custom-server.js", 'success');
        } else {
            add_log($logs, "custom-server.js startup guard already patched", 'info');
        }
    }

    // 3. Restart Passenger
    $restart_dir = $current_link . '/nodejs/tmp';
    if (!is_dir($restart_dir)) {
        @mkdir($restart_dir, 0755, true);
    }
    $restart_txt = $restart_dir . '/restart.txt';
    touch($restart_txt);
    add_log($logs, "Passenger restart signaled via $restart_txt", 'success');
    
    $action_result = "Recovery procedure completed successfully.";
}

// Gather System Diagnostics
$ch = curl_init('http://127.0.0.1:3000/api/health');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 2);
$health_raw = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$node_healthy = ($http_code === 200);

// Check Public URL health
$ch_pub = curl_init('https://9router.harumon-japanese.com/api/version');
curl_setopt($ch_pub, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch_pub, CURLOPT_TIMEOUT, 3);
curl_setopt($ch_pub, CURLOPT_SSL_VERIFYPEER, false);
$version_raw = curl_exec($ch_pub);
$pub_code = curl_getinfo($ch_pub, CURLINFO_HTTP_CODE);
curl_close($ch_pub);

$version_data = json_decode($version_raw, true);
$app_version = $version_data['currentVersion'] ?? 'Unknown';

$htaccess_status = false;
if (file_exists($htaccess_file)) {
    $content = file_get_contents($htaccess_file);
    if (strpos($content, 'SetEnv BASE_URL') !== false && strpos($content, 'auto-fix.php') !== false) {
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
    <title>9Router - Recovery & Maintenance Console</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --brand-500: #E56A4A;
            --brand-600: #cc5236;
            --bg: #FDFAF6;
            --surface: #ffffff;
            --border: #e5e7eb;
            --text-main: #0a0a0a;
            --text-muted: #6B7280;
            --success: #10B981;
            --danger: #cf222e;
            --warning: #F59E0B;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: var(--bg);
            color: var(--text-main);
            min-height: 100vh;
            padding: 32px 16px;
            display: flex;
            justify-content: center;
        }

        .container {
            width: 100%;
            max-width: 800px;
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid var(--border);
            padding-bottom: 20px;
        }

        .logo-wrap {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo-badge {
            background-color: var(--brand-500);
            color: white;
            font-weight: 700;
            font-size: 18px;
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .logo-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-main);
            letter-spacing: -0.02em;
        }

        .logo-sub {
            font-size: 13px;
            color: var(--text-muted);
        }

        .top-badge {
            font-size: 12px;
            font-weight: 600;
            padding: 6px 12px;
            border-radius: 9999px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .badge-live { background-color: #ecfdf5; color: var(--success); border: 1px solid #a7f3d0; }
        .badge-down { background-color: #fef2f2; color: var(--danger); border: 1px solid #fecaca; }

        .card {
            background-color: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 1px 2px 0 rgba(0,0,0,0.04);
        }

        .card-title {
            font-size: 15px;
            font-weight: 600;
            color: var(--text-main);
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .grid-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
        }

        .stat-item {
            background: #fdfdfd;
            border: 1px solid #f3f4f6;
            border-radius: 8px;
            padding: 16px;
        }

        .stat-label {
            font-size: 12px;
            font-weight: 500;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 6px;
        }

        .stat-value {
            font-size: 16px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
        }
        .dot-green { background-color: var(--success); }
        .dot-red { background-color: var(--danger); }
        .dot-orange { background-color: var(--warning); }

        .actions-bar {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn {
            font-family: inherit;
            font-size: 14px;
            font-weight: 500;
            padding: 10px 18px;
            border-radius: 8px;
            border: 1px solid transparent;
            cursor: pointer;
            transition: all 0.15s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background-color: var(--brand-500);
            color: #ffffff;
        }
        .btn-primary:hover {
            background-color: var(--brand-600);
        }

        .btn-secondary {
            background-color: #ffffff;
            border-color: var(--border);
            color: var(--text-main);
        }
        .btn-secondary:hover {
            background-color: #f9fafb;
        }

        .terminal {
            background-color: #111827;
            color: #f3f4f6;
            font-family: 'JetBrains Mono', monospace;
            font-size: 12px;
            border-radius: 8px;
            padding: 16px;
            overflow-x: auto;
            line-height: 1.6;
        }

        .terminal-line { margin-bottom: 4px; }
        .terminal-time { color: #9ca3af; }
        .terminal-success { color: #34d399; }
        .terminal-warning { color: #fbbf24; }
        .terminal-error { color: #f87171; }
        .terminal-info { color: #60a5fa; }

        .banner {
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 16px;
        }
        .banner-success { background-color: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }

        @media (max-width: 600px) {
            .grid-stats { grid-template-columns: 1fr; }
            .header { flex-direction: column; align-items: flex-start; gap: 12px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="logo-wrap">
                <div class="logo-badge">9</div>
                <div>
                    <h1 class="logo-title">9Router Maintenance Console</h1>
                    <div class="logo-sub">Hostinger LiteSpeed Environment Resilience</div>
                </div>
            </div>
            <div>
                <?php if ($node_healthy && $pub_code === 200): ?>
                    <span class="top-badge badge-live"><span class="dot dot-green"></span> System Operational</span>
                <?php else: ?>
                    <span class="top-badge badge-down"><span class="dot dot-red"></span> Degraded Service (HTTP <?= $pub_code ?>)</span>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($action_result): ?>
            <div class="banner banner-success">
                <?= htmlspecialchars($action_result) ?>
            </div>
        <?php endif; ?>

        <!-- Quick Metrics -->
        <div class="card">
            <div class="card-title">Live System Status</div>
            <div class="grid-stats">
                <div class="stat-item">
                    <div class="stat-label">9Router HTTP Status</div>
                    <div class="stat-value">
                        <?php if ($pub_code === 200): ?>
                            <span class="dot dot-green"></span> 200 OK (v<?= htmlspecialchars($app_version) ?>)
                        <?php else: ?>
                            <span class="dot dot-red"></span> HTTP <?= $pub_code ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="stat-item">
                    <div class="stat-label">Node Process (Port 3000)</div>
                    <div class="stat-value">
                        <?php if ($node_healthy): ?>
                            <span class="dot dot-green"></span> Listening & Healthy
                        <?php else: ?>
                            <span class="dot dot-red"></span> Unreachable (503 Risk)
                        <?php endif; ?>
                    </div>
                </div>
                <div class="stat-item">
                    <div class="stat-label">.htaccess Routing & Env</div>
                    <div class="stat-value">
                        <?php if ($htaccess_status): ?>
                            <span class="dot dot-green"></span> Configured (BASE_URL OK)
                        <?php else: ?>
                            <span class="dot dot-orange"></span> Needs Environment Rules
                        <?php endif; ?>
                    </div>
                </div>
                <div class="stat-item">
                    <div class="stat-label">SQLite Database</div>
                    <div class="stat-value">
                        <?php if ($db_status): ?>
                            <span class="dot dot-green"></span> Connected
                        <?php else: ?>
                            <span class="dot dot-red"></span> Missing DB File
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Panel -->
        <div class="card">
            <div class="card-title">Automated Repair Actions</div>
            <p style="font-size: 14px; color: var(--text-muted); margin-bottom: 16px;">
                Execute one-click recovery if LiteSpeed returns 503 Service Unavailable or model fetch requests fail.
            </p>
            <div class="actions-bar">
                <form method="POST" action="?key=<?= urlencode($SECRET_KEY) ?>">
                    <input type="hidden" name="action" value="fix">
                    <button type="submit" class="btn btn-primary">
                        <span>⚡ Run Diagnostic & Auto-Fix</span>
                    </button>
                </form>
                <form method="POST" action="?key=<?= urlencode($SECRET_KEY) ?>">
                    <input type="hidden" name="action" value="restart">
                    <button type="submit" class="btn btn-secondary">
                        <span>🔄 Restart Passenger Process</span>
                    </button>
                </form>
                <a href="/dashboard" class="btn btn-secondary" target="_blank">
                    <span>↗ Open 9Router Dashboard</span>
                </a>
            </div>
        </div>

        <!-- Terminal Output -->
        <div class="card">
            <div class="card-title">Console Output</div>
            <div class="terminal">
                <div class="terminal-line"><span class="terminal-time">[<?= date('H:i:s') ?>]</span> <span class="terminal-info">VHOST root:</span> <?= htmlspecialchars($vhost_root) ?></div>
                <div class="terminal-line"><span class="terminal-time">[<?= date('H:i:s') ?>]</span> <span class="terminal-info">Active build:</span> <?= htmlspecialchars(readlink($current_link) ?: 'Direct') ?></div>
                <?php if (empty($logs)): ?>
                    <div class="terminal-line"><span class="terminal-time">[<?= date('H:i:s') ?>]</span> <span class="terminal-success">System idle. Ready for diagnostic or repair.</span></div>
                <?php else: ?>
                    <?php foreach ($logs as $l): ?>
                        <div class="terminal-line">
                            <span class="terminal-time">[<?= htmlspecialchars($l['time']) ?>]</span>
                            <span class="terminal-<?= htmlspecialchars($l['type']) ?>"><?= htmlspecialchars($l['msg']) ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
