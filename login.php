<?php
session_start();

if (isset($_SESSION['student_id']))   { header('Location: student_dashboard.php');   exit; }
if (isset($_SESSION['organizer_id'])) { header('Location: organizer_dashboard.php'); exit; }
if (isset($_SESSION['admin_id']))     { header('Location: admin_dashboard.php');      exit; }

require_once 'db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');
    $password   = trim($_POST['password']   ?? '');

    if (empty($identifier) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        $found = false;

        // ── 1. Student: always look up by studentID (VARCHAR, exact match) ──
        $stmt = mysqli_prepare($conn, "SELECT studentID, name, password FROM students WHERE studentID = ?");
        mysqli_stmt_bind_param($stmt, 's', $identifier);
        mysqli_stmt_execute($stmt);
        $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if ($user) {
            $found = true;
            if (!password_verify($password, $user['password'])) {
                $error = 'Wrong password.';
            } else {
                $_SESSION['student_id']   = $user['studentID'];
                $_SESSION['student_name'] = $user['name'];
                header('Location: student_dashboard.php'); exit;
            }
        }

       
        if (!$found) {
          

            // If not found by email and identifier is a short integer, try by organizerID
            if (!$user && ctype_digit($identifier) && strlen($identifier) <= 9) {
                $oid  = (int)$identifier;
                $stmt = mysqli_prepare($conn, "SELECT organizerID, name, password, role FROM organizers WHERE organizerID = ?");
                mysqli_stmt_bind_param($stmt, 'i', $oid);
                mysqli_stmt_execute($stmt);
                $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
                mysqli_stmt_close($stmt);
            }

            if ($user) {
                $found = true;
                if (!password_verify($password, $user['password'])) {
                    $error = 'Wrong password.';
                } else {
                    $_SESSION['organizer_id']   = $user['organizerID'];
                    $_SESSION['organizer_name'] = $user['name'];
                    $_SESSION['organizer_role'] = $user['role'];
                    header('Location: organizer_dashboard.php'); exit;
                }
            }
        }

        // ── 3. Admin: try email first, then short numeric ID ──
        if (!$found) {
           

            // If not found by email and short integer, try by adminID
            if (!$user && ctype_digit($identifier) && strlen($identifier) <= 9) {
                $aid  = (int)$identifier;
                $stmt = mysqli_prepare($conn, "SELECT adminID, name, password FROM admins WHERE adminID = ?");
                mysqli_stmt_bind_param($stmt, 'i', $aid);
                mysqli_stmt_execute($stmt);
                $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
                mysqli_stmt_close($stmt);
            }

            if ($user) {
                $found = true;
                if (!password_verify($password, $user['password'])) {
                    $error = 'Wrong password.';
                } else {
                    $_SESSION['admin_id']   = $user['adminID'];
                    $_SESSION['admin_name'] = $user['name'];
                    header('Location: admin_dashboard.php'); exit;
                }
            }
        }

        if (!$found) {
            $error = 'Account not found. Check your ID or email.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - DUEMS</title>
    <style>
        body {
            margin: 0;
            background-color: #f0f2f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .card {
            background: #fff;
            width: 400px;
            padding: 36px 32px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .card h2 { text-align:center; color:#2c3e50; font-size:22px; margin:0 0 4px; }
        .card .sub { text-align:center; font-size:13px; color:#888; margin-bottom:24px; }
        .alert-error { background:#fef2f2; color:#b91c1c; border:1px solid #fca5a5; border-radius:6px; padding:9px 12px; font-size:13px; margin-bottom:16px; }
        label { display:block; font-size:13px; font-weight:600; color:#374151; margin-bottom:5px; }
        input { width:100%; padding:10px 12px; border:1px solid #d1d5db; border-radius:6px; font-size:14px; color:#2c3e50; background:#f9fafb; box-sizing:border-box; margin-bottom:8px; outline:none; }
        input:focus { border-color:#2c3e50; background:#fff; }
        .hint { font-size:11px; color:#aaa; margin-bottom:16px; line-height:1.5; }
        button { width:100%; padding:11px; background-color:#2c3e50; color:#f0c040; font-size:15px; font-weight:700; border:none; border-radius:6px; cursor:pointer; margin-top:8px; }
        button:hover { background-color:#3d5166; }
        .register-link { text-align:center; font-size:13px; color:#888; margin-top:16px; }
        .register-link a { color:#2c3e50; font-weight:600; text-decoration:none; }
        .register-link a:hover { text-decoration:underline; }

        /* Role hint boxes */
        .role-hints { display:flex; gap:8px; margin-bottom:22px; }
        .role-hint  { flex:1; background:#f8f9fa; border:1px solid #e5e7eb; border-radius:6px; padding:8px 10px; font-size:11px; color:#555; line-height:1.5; }
        .role-hint strong { display:block; color:#2c3e50; margin-bottom:2px; font-size:12px; }
    </style>
</head>
<body>
<div class="card">
    <h2>DUEMS</h2>
    <p class="sub">Jazan University &mdash; Login Portal</p>

 
    <?php if ($error): ?>
        <div class="alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="login.php" novalidate>
        <label for="identifier">ID </label>
        <input type="text" id="identifier" name="identifier" maxlength="9"
               placeholder="Enter your ID"
               value="<?= isset($_POST['identifier']) ? htmlspecialchars($_POST['identifier']) : '' ?>"
               autocomplete="username">
        
        <label for="password">Password</label>
        <input type="password" id="password" name="password"
               placeholder="Enter your password"
               autocomplete="current-password">

        <button type="submit">Login</button>
    </form>

    <p class="register-link">
        Don't have an account? <a href="register.php">Register here</a>
    </p>
</div>
</body>
</html>