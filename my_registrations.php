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

// handle cancel
if (isset($_POST['cancel_registration'])) {
    $reg_id = (int)$_POST['registration_id'];

    $stmt = mysqli_prepare($conn, "SELECT r.registrationID, e.date FROM registrations r JOIN events e ON r.eventID = e.eventID WHERE r.registrationID = ? AND r.studentID = ?");
    mysqli_stmt_bind_param($stmt, 'ii', $reg_id, $student_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $reg = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    if (!$reg) {
        $message  = 'Registration not found.';
        $msg_type = 'danger';
    } elseif (strtotime($reg['date']) < time()) {
        $message  = 'You cannot cancel a past event.';
        $msg_type = 'warning';
    } else {
        $stmt = mysqli_prepare($conn, "UPDATE registrations SET status = 'cancelled' WHERE registrationID = ?");
        mysqli_stmt_bind_param($stmt, 'i', $reg_id);
        if (mysqli_stmt_execute($stmt)) {
            $message  = 'Registration cancelled.';
            $msg_type = 'success';
        } else {
            $message  = 'Failed to cancel. Try again.';
            $msg_type = 'danger';
        }
        mysqli_stmt_close($stmt);
    }
}

// filter
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

$extra = '';
if ($filter == 'confirmed') $extra = "AND r.status = 'confirmed'";
if ($filter == 'cancelled') $extra = "AND r.status = 'cancelled'";
if ($filter == 'upcoming')  $extra = "AND e.date > NOW() AND r.status = 'confirmed'";
if ($filter == 'past')      $extra = "AND e.date < NOW()";

$sql  = "SELECT r.*, e.title, e.date, e.location, o.name AS organizer_name,
                (SELECT ap.decision FROM approvals ap WHERE ap.eventID = r.eventID ORDER BY ap.decisionDate DESC LIMIT 1) AS approval_decision
         FROM registrations r
         JOIN events e ON r.eventID = e.eventID
         JOIN organizers o ON e.organizerID = o.organizerID
         WHERE r.studentID = ? $extra
         ORDER BY e.date DESC";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'i', $student_id);
mysqli_stmt_execute($stmt);
$registrations = mysqli_stmt_get_result($stmt);
mysqli_stmt_close($stmt);

$current_page = 'my_registrations.php';
$student_name = $_SESSION['student_name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Registrations - DUEMS</title>
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
        .badge-confirmed { background-color: #28a745; color: white; padding: 3px 8px; border-radius: 12px; font-size: 12px; }
        .badge-cancelled { background-color: #dc3545; color: white; padding: 3px 8px; border-radius: 12px; font-size: 12px; }
        .badge-pending   { background-color: #ffc107; color: #333;  padding: 3px 8px; border-radius: 12px; font-size: 12px; }
        .badge-approved  { background-color: #17a2b8; color: white; padding: 3px 8px; border-radius: 12px; font-size: 12px; }
        .badge-rejected  { background-color: #dc3545; color: white; padding: 3px 8px; border-radius: 12px; font-size: 12px; }

        /* ── Custom confirm modal ── */
        .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center; }
        .modal-overlay.active { display:flex; }
        .modal-box { background:#fff; border-radius:10px; padding:28px 28px 22px; width:320px; box-shadow:0 8px 30px rgba(0,0,0,0.25); text-align:center; }
        .modal-box p { font-size:15px; color:#2c3e50; margin:0 0 20px; font-weight:500; }
        .modal-actions { display:flex; gap:10px; justify-content:center; }
        .modal-actions button { padding:9px 26px; border:none; border-radius:6px; font-size:14px; font-weight:600; cursor:pointer; }
        .modal-btn-yes { background:#2c3e50; color:#f0c040; }
        .modal-btn-yes:hover { background:#3d5166; }
        .modal-btn-no  { background:#e5e7eb; color:#374151; }
        .modal-btn-no:hover  { background:#d1d5db; }
    </style>
</head>
<body>

<!-- Custom Confirm Modal -->
<div class="modal-overlay" id="confirmModal">
    <div class="modal-box">
        <p id="confirmMsg">Are you sure?</p>
        <div class="modal-actions">
            <button class="modal-btn-yes" id="confirmYes">Confirm</button>
            <button class="modal-btn-no"  id="confirmNo">Cancel</button>
        </div>
    </div>
</div>

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
        <h4>My Registrations</h4>
        <a href="browse_events.php" class="btn btn-sm btn-primary">Browse Events</a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $msg_type ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <!-- Filter buttons -->
    <div class="mb-3">
        <a href="my_registrations.php?filter=all"       class="btn btn-sm <?= $filter == 'all'       ? 'btn-dark'    : 'btn-outline-secondary' ?>">All</a>
        <a href="my_registrations.php?filter=upcoming"  class="btn btn-sm <?= $filter == 'upcoming'  ? 'btn-primary' : 'btn-outline-secondary' ?>">Upcoming</a>
        <a href="my_registrations.php?filter=confirmed" class="btn btn-sm <?= $filter == 'confirmed' ? 'btn-success' : 'btn-outline-secondary' ?>">Confirmed</a>
        <a href="my_registrations.php?filter=cancelled" class="btn btn-sm <?= $filter == 'cancelled' ? 'btn-danger'  : 'btn-outline-secondary' ?>">Cancelled</a>
        <a href="my_registrations.php?filter=past"      class="btn btn-sm <?= $filter == 'past'      ? 'btn-secondary':'btn-outline-secondary' ?>">Past</a>
    </div>

    <div class="card">
        <?php if (mysqli_num_rows($registrations) == 0): ?>
            <p style="color:#888;">No registrations found. <a href="browse_events.php">Browse events</a></p>
        <?php else: ?>
            <table class="table table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>Event</th>
                        <th>Date</th>
                        <th>Location</th>
                        <th>Registered On</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php $i = 1; while ($reg = mysqli_fetch_assoc($registrations)):
                    $is_upcoming  = strtotime($reg['date']) > time();
                    $is_confirmed = $reg['status'] == 'confirmed';
                ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td><a href="event_detail.php?id=<?= $reg['eventID'] ?>" style="color:#2c3e50; font-weight:600; text-decoration:none;"><?= htmlspecialchars($reg['title']) ?></a></td>
                        <td><?= date('M d, Y', strtotime($reg['date'])) ?></td>
                        <td><?= htmlspecialchars($reg['location']) ?></td>
                        <td><?= date('M d, Y', strtotime($reg['registrationDate'])) ?></td>
                       ' <td><span class="badge-<?= strtolower($reg['status']) ?>"><?= ucfirst($reg['status']) ?></span></td>'
                      
                        <td>
                            <?php if ($is_upcoming && $is_confirmed): ?>
                                <form method="POST" style="margin:0;" onsubmit="return showConfirm('Cancel this registration?', this)">
                                    <input type="hidden" name="cancel_registration" value="1">
                                    <input type="hidden" name="registration_id" value="<?= $reg['registrationID'] ?>">
                                    <button type="submit" name="cancel_registration" class="btn btn-sm btn-danger">Cancel</button>
                                </form>
                            <?php else: ?>
                                <span style="color:#aaa;">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

</div>

<script>
    var _pendingForm = null;
    function showConfirm(msg, form) {
        document.getElementById('confirmMsg').textContent = msg;
        document.getElementById('confirmModal').classList.add('active');
        _pendingForm = form;
        return false;
    }
    document.getElementById('confirmYes').addEventListener('click', function() {
        document.getElementById('confirmModal').classList.remove('active');
        if (_pendingForm) { _pendingForm.submit(); _pendingForm = null; }
    });
    document.getElementById('confirmNo').addEventListener('click', function() {
        document.getElementById('confirmModal').classList.remove('active');
        _pendingForm = null;
    });
</script>
</body>
</html>
<?php mysqli_close($conn); ?>
