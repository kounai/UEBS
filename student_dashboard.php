<?php
session_start();

if (!isset($_SESSION['student_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'db.php';

$student_id = $_SESSION['student_id'];

// get student info
$stmt = mysqli_prepare($conn, "SELECT * FROM students WHERE studentID = ?");
mysqli_stmt_bind_param($stmt, 's', $student_id);
mysqli_stmt_execute($stmt);
$result  = mysqli_stmt_get_result($stmt);
$student = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

$_SESSION['student_name'] = $student['name'];

// count total approved events
$res = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM events WHERE status = 'approved' OR eventID IN (SELECT eventID FROM approvals WHERE decision = 'approved')");
$total_events = mysqli_fetch_assoc($res)['cnt'];

// count my registrations
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS cnt FROM registrations WHERE studentID = ?");
mysqli_stmt_bind_param($stmt, 's', $student_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$my_registrations = mysqli_fetch_assoc($res)['cnt'];
mysqli_stmt_close($stmt);

// count upcoming confirmed
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS cnt FROM registrations r JOIN events e ON r.eventID = e.eventID WHERE r.studentID = ? AND e.date > NOW() AND r.status = 'confirmed'");
mysqli_stmt_bind_param($stmt, 's', $student_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$upcoming_count = mysqli_fetch_assoc($res)['cnt'];
mysqli_stmt_close($stmt);

// get upcoming events (next 5)
$upcoming_events = mysqli_query($conn, "SELECT e.*, o.name AS organizer_name FROM events e JOIN organizers o ON e.organizerID = o.organizerID WHERE (e.status = 'approved' OR EXISTS (SELECT 1 FROM approvals ap WHERE ap.eventID = e.eventID AND ap.decision = 'approved')) AND e.date >= NOW() ORDER BY e.date ASC LIMIT 5");

// get my recent registrations
$stmt = mysqli_prepare($conn, "SELECT r.*, e.title, e.date, e.location FROM registrations r JOIN events e ON r.eventID = e.eventID WHERE r.studentID = ? ORDER BY r.registrationDate DESC LIMIT 5");
mysqli_stmt_bind_param($stmt, 's', $student_id);
mysqli_stmt_execute($stmt);
$my_recent = mysqli_stmt_get_result($stmt);
mysqli_stmt_close($stmt);

$current_page = 'student_dashboard.php';
$student_name = $student['name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - DUEMS</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body { background-color: #f0f2f5; }
        .sidebar {
            width: 220px;
            min-height: 100vh;
            background-color: #2c3e50;
            color: white;
            position: fixed;
            top: 0;
            left: 0;
            padding-top: 20px;
        }
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
        .badge-confirmed { background-color: #28a745; color: white; padding: 3px 8px; border-radius: 12px; font-size: 12px; }
        .badge-cancelled { background-color: #dc3545; color: white; padding: 3px 8px; border-radius: 12px; font-size: 12px; }
        .badge-pending   { background-color: #ffc107; color: #333;  padding: 3px 8px; border-radius: 12px; font-size: 12px; }
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
        <h4>Dashboard</h4>
        <span style="font-size:13px; color:#888;">Welcome, <?= htmlspecialchars($student['name']) ?> | ID: <?= htmlspecialchars($student['studentID']) ?></span>
    </div>

    <!-- Stats -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card text-center">
                <h2 style="color:#2c3e50;"><?= $total_events ?></h2>
                <p style="color:#888; margin:0;">Available Events</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center">
                <h2 style="color:#27ae60;"><?= $my_registrations ?></h2>
                <p style="color:#888; margin:0;">My Registrations</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center">
                <h2 style="color:#e67e22;"><?= $upcoming_count ?></h2>
                <p style="color:#888; margin:0;">Upcoming Events</p>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Upcoming Events -->
        <div class="col-md-6">
            <div class="card">
                <h5 style="margin-bottom:15px;">Upcoming Events <a href="browse_events.php" style="font-size:13px; float:right;">View all</a></h5>
                <?php if (mysqli_num_rows($upcoming_events) == 0): ?>
                    <p style="color:#888;">No upcoming events.</p>
                <?php else: ?>
                    <table class="table table-sm table-hover">
                        <thead><tr><th>Event</th><th>Date</th><th>Location</th></tr></thead>
                        <tbody>
                        <?php while ($ev = mysqli_fetch_assoc($upcoming_events)): ?>
                            <tr>
                                <td><a href="event_detail.php?id=<?= $ev['eventID'] ?>" style="color:#2c3e50;"><?= htmlspecialchars($ev['title']) ?></a></td>
                                <td><?= date('M d, Y', strtotime($ev['date'])) ?></td>
                                <td><?= htmlspecialchars($ev['location']) ?></td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- My Recent Registrations -->
        <div class="col-md-6">
            <div class="card">
                <h5 style="margin-bottom:15px;">My Recent Registrations <a href="my_registrations.php" style="font-size:13px; float:right;">View all</a></h5>
                <?php if (mysqli_num_rows($my_recent) == 0): ?>
                    <p style="color:#888;">No registrations yet. <a href="browse_events.php">Browse events</a></p>
                <?php else: ?>
                    <table class="table table-sm table-hover">
                        <thead><tr><th>Event</th><th>Date</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php while ($reg = mysqli_fetch_assoc($my_recent)): ?>
                            <tr>
                                <td><?= htmlspecialchars($reg['title']) ?></td>
                                <td><?= date('M d, Y', strtotime($reg['date'])) ?></td>
                                <td><span class="badge-<?= strtolower($reg['status']) ?>"><?= ucfirst($reg['status']) ?></span></td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>

</body>
</html>
<?php mysqli_close($conn); ?>
