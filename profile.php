<?php
session_start();

if (!isset($_SESSION['student_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'db.php';

$student_id = $_SESSION['student_id'];
$message    = '';
$msg_type   = '';


// get student data
$stmt = mysqli_prepare($conn, "SELECT * FROM students WHERE studentID = ?");
mysqli_stmt_bind_param($stmt, 'i', $student_id);
mysqli_stmt_execute($stmt);
$res     = mysqli_stmt_get_result($stmt);
$student = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

$_SESSION['student_name'] = $student['name'];

$current_page = 'profile.php';
$student_name = $student['name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - DUEMS</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body { background-color: #f0f2f5; }
        .sidebar { width: 220px; min-height: 100vh; background-color: #2c3e50; color: white; position: fixed; top: 0; left: 0; padding-top: 20px; }
        .sidebar h4 { color: #f0c040; text-align: center; font-size: 15px; padding: 0 15px 10px; border-bottom: 1px solid #3d5166; }
        .sidebar .student-info { text-align: center; padding: 10px 15px 15px; border-bottom: 1px solid #3d5166; font-size: 13px; color: #aab; }
        .sidebar a { display: block; color: #ccc; text-decoration: none; padding: 10px 20px; font-size: 14px; }
        .sidebar a:hover { background-color: #3d5166; color: white; }
        .sidebar a.active { background-color: #f0c040; color: #2c3e50; font-weight: bold; }
        .sidebar .logout-link { color: #e74c3c; border-top: 1px solid #3d5166; margin-top: 10px; }
        .sidebar .logout-link:hover { background-color: #e74c3c; color: white; }
        .main-content { margin-left: 220px; padding: 25px; }
        .topbar { background: white; padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 1px 4px rgba(0,0,0,0.08); display: flex; justify-content: space-between; align-items: center; }
        .topbar h4 { margin: 0; color: #333; }
        .card { background: white; border-radius: 8px; padding: 20px; box-shadow: 0 1px 4px rgba(0,0,0,0.08); margin-bottom: 20px; }
    </style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
    <h4>DUEMS<br><small style="font-size:11px; color:#aaa;">Jazan University</small></h4>
    <div class="student-info">Hello, <strong style="color:white;"><?= htmlspecialchars($student_name) ?></strong></div>
    <a href="student_dashboard.php"   class="<?= $current_page == 'student_dashboard.php'   ? 'active' : '' ?>">Dashboard</a>
    <a href="browse_events.php" class="<?= $current_page == 'browse_events.php'      ? 'active' : '' ?>">Browse Events</a>
    <a href="my_registrations.php"   class="<?= $current_page == 'my_registrations.php'   ? 'active' : '' ?>">My Registrations</a>
    <a href="profile.php"     class="<?= $current_page == 'profile.php'     ? 'active' : '' ?>">My Profile</a>
    <a href="support.php"     class="<?= $current_page == 'support.php'     ? 'active' : '' ?>">Support</a>
    <a href="logout.php" class="logout-link">Logout</a>
</div>

<!-- Main Content -->
<div class="main-content">
    <div class="topbar">
        <h4>My Profile</h4>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $msg_type ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <!-- Edit Profile -->
<div class="card">
    <h5>Profile Information</h5>
    <div class="row">
        <div class="col-md-6 mb-3">
            <label class="form-label">Full Name</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($student['name']) ?>" disabled>
        </div>
        <div class="col-md-6 mb-3">
            <label class="form-label">Student ID</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($student['studentID']) ?>" disabled>
        </div>
        <div class="col-md-6 mb-3">
            <label class="form-label">Email Address</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($student['email']) ?>" disabled>
        </div>
        <div class="col-md-6 mb-3">
            <label class="form-label">Department</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($student['department']) ?>" disabled>
        </div>
        <div class="col-md-6 mb-3">
            <label class="form-label">Gender</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($student['gender'] ?? '—') ?>" disabled>
        </div>
    </div>
</div>
</body>
</html>
<?php mysqli_close($conn); ?>
