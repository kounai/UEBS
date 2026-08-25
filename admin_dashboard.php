<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }
require_once 'db.php';

// Pending events count for badge
$pending_count = (int)mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS c FROM events WHERE status='pending'"))['c'];
mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - DUEMS</title>
    <style>
        body { margin:0; background-color:#f0f2f5; font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif; color:#333; overflow:hidden; }

        .sidebar { width:220px; min-height:100vh; background-color:#2c3e50; color:white; position:fixed; top:0; left:0; padding-top:20px; z-index:1000; }
        .sidebar h4 { color:#f0c040; text-align:center; font-size:15px; padding:0 15px 10px; border-bottom:1px solid #3d5166; margin:0; }
        .sidebar .admin-info { text-align:center; padding:10px 15px 15px; border-bottom:1px solid #3d5166; font-size:13px; color:#aab; }
        .sidebar a { display:block; color:#ccc; text-decoration:none; padding:10px 20px; font-size:14px; cursor:pointer; position:relative; }
        .sidebar a:hover { background-color:#3d5166; color:white; }
        .sidebar a.active { background-color:#f0c040; color:#2c3e50; font-weight:bold; }
        .sidebar .logout-link { color:#e74c3c; border-top:1px solid #3d5166; margin-top:10px; }
        .sidebar .logout-link:hover { background-color:#e74c3c; color:white; }

        /* Pending badge */
        .pending-badge { background:#ef4444; color:white; font-size:10px; font-weight:700; padding:1px 6px; border-radius:10px; position:absolute; right:14px; top:50%; transform:translateY(-50%); }
        .sidebar a.active .pending-badge { background:#2c3e50; color:#f0c040; }

        .main-content { margin-left:220px; position:relative; height:100vh; }
        iframe { width:100%; height:100%; border:none; display:block; }

        .loader-overlay { position:absolute; inset:0; background:#f0f2f5; display:flex; align-items:center; justify-content:center; z-index:10; opacity:1; transition:opacity .3s; }
        .loader-overlay.hidden { opacity:0; pointer-events:none; }
        .spinner { width:36px; height:36px; border:4px solid #e2e8f0; border-top-color:#2c3e50; border-radius:50%; animation:spin .9s linear infinite; }
        @keyframes spin { to { transform:rotate(360deg); } }
    </style>
</head>
<body>

<div class="sidebar">
    <h4>DUEMS<br><small style="font-size:11px;color:#aaa;font-weight:normal;">Jazan University</small></h4>
    <div class="admin-info">Hello, <strong style="color:white;"><?= htmlspecialchars($_SESSION['admin_name']) ?></strong></div>

    <a onclick="navigate(this,'admin_overview.php')"  class="nav-link active">Dashboard</a>
    <a onclick="navigate(this,'admin_approvals.php')" class="nav-link">
        Event Approvals
       
    </a>
    <a onclick="navigate(this,'admin_events.php')"         class="nav-link">All Events</a>
    <a onclick="navigate(this,'admin_registrations.php')" class="nav-link">Registrations</a>
    <a onclick="navigate(this,'admin_users.php')"     class="nav-link">Users</a>
    <a onclick="navigate(this,'admin_requests.php')"  class="nav-link">Support</a>

    <a href="logout.php" class="logout-link">Logout</a>
</div>

<div class="main-content">
    <div class="loader-overlay" id="loader">
        <div class="spinner"></div>
    </div>
    <iframe id="contentFrame" name="content_frame" src="admin_overview.php" onload="hideLoader()"></iframe>
</div>

<script>
function navigate(el, url) {
    document.getElementById('loader').classList.remove('hidden');
    document.getElementById('contentFrame').src = url;
    document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
    el.classList.add('active');
}
function hideLoader() {
    setTimeout(() => document.getElementById('loader').classList.add('hidden'), 150);
}
</script>
</body>
</html>
