<?php

/*Deep WebShell | Made by: https://github.com/BryanApolonio*/

session_start();

$MASTER_KEY = "PASS"; // Change your MASTER_KEY for your security.

if (isset($_POST['login_user']) && isset($_POST['login_pass']) && isset($_POST['master_key'])) {
    $user = trim($_POST['login_user']);
    $pass = $_POST['login_pass'];
    $m_key = $_POST['master_key'];

    if (empty($user) || empty($pass) || empty($m_key)) {
        $error = "All fields are required.";
    } elseif ($m_key !== $MASTER_KEY) {
        $error = "Invalid Master Key.";
    } elseif (strtolower($user) === 'root') {
        $error = "Direct root login is disabled.";
    } else {
        $descriptorspec = [0 => ["pipe", "r"], 1 => ["pipe", "w"], 2 => ["pipe", "w"]];
        $process = proc_open("sudo -k -S -p '' -u " . escapeshellarg($user) . " whoami", $descriptorspec, $pipes);

        if (is_resource($process)) {
            fwrite($pipes[0], $pass . "\n");
            fclose($pipes[0]);
            $stdout = trim(stream_get_contents($pipes[1]));
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exit_code = proc_close($process);

            if ($exit_code === 0 && $stdout === $user) {
                $_SESSION['logged_in'] = true;
                $_SESSION['sys_user'] = $user;
                $_SESSION['sys_pass'] = $pass;
                $_SESSION['last_activity'] = time();
                header("Location: webshell.php");
                exit;
            } else {
                $error = "Linux System Authentication failed.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Deep Shell Auth</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body class="login-body auth-page">
    <div class="login-card">
        <h2>Secure Login</h2>
        <?php if (isset($error)): ?>
            <div class="error-msg"><?php echo $error; ?></div>
        <?php endif; ?>
        <form method="POST">
            <input type="text" name="login_user" class="login-input" placeholder="System Username" required autofocus>
            <input type="password" name="login_pass" class="login-input" placeholder="System Password" required>
            
            <input type="password" name="master_key" class="login-input" placeholder="Master Key (Fixed)">
            
            <button type="submit" class="btn-save" style="width: 100%;">Authorize Access</button>
        </form>
    </div>
</body>
</html>
