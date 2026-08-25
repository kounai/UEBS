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

// get event id from url
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: browse_events.php');
    exit;
}

$event_id = (int)$_GET['id'];

// handle register button
if (isset($_POST['register_event'])) {

    $stmt = mysqli_prepare($conn, "SELECT registrationID FROM registrations WHERE studentID = ? AND eventID = ? AND status != 'cancelled'");
    mysqli_stmt_bind_param($stmt, 'si', $student_id, $event_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    $already = mysqli_stmt_num_rows($stmt) > 0;
    mysqli_stmt_close($stmt);

    if ($already) {
        $message  = 'You are already registered for this event.';
        $msg_type = 'warning';
    } else {
        $stmt = mysqli_prepare($conn, "SELECT capacity FROM events WHERE eventID = ?");
        mysqli_stmt_bind_param($stmt, 'i', $event_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $ev  = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);

        $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS cnt FROM registrations WHERE eventID = ? AND status = 'confirmed'");
        mysqli_stmt_bind_param($stmt, 'i', $event_id);
        mysqli_stmt_execute($stmt);
        $res   = mysqli_stmt_get_result($stmt);
        $count = mysqli_fetch_assoc($res)['cnt'];
        mysqli_stmt_close($stmt);

        if ($count >= $ev['capacity']) {
            $message  = 'Sorry, this event is full.';
            $msg_type = 'danger';
        } else {
            $stmt = mysqli_prepare($conn, "SELECT registrationID FROM registrations WHERE studentID = ? AND eventID = ? AND status = 'cancelled'");
            mysqli_stmt_bind_param($stmt, 'si', $student_id, $event_id);
            mysqli_stmt_execute($stmt);
            $res       = mysqli_stmt_get_result($stmt);
            $cancelled = mysqli_fetch_assoc($res);
            mysqli_stmt_close($stmt);

            if ($cancelled) {
                $stmt = mysqli_prepare($conn, "UPDATE registrations SET status = 'confirmed', registrationDate = NOW() WHERE registrationID = ?");
                mysqli_stmt_bind_param($stmt, 'i', $cancelled['registrationID']);
            } else {
                $stmt = mysqli_prepare($conn, "INSERT INTO registrations (studentID, eventID, status) VALUES (?, ?, 'confirmed')");
                mysqli_stmt_bind_param($stmt, 'si', $student_id, $event_id);
            }

            if (mysqli_stmt_execute($stmt)) {
                $message  = 'You have successfully registered for this event!';
                $msg_type = 'success';
            } else {
                $message  = 'Registration failed. Please try again.';
                $msg_type = 'danger';
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// get event details
$stmt = mysqli_prepare($conn, "SELECT e.*, o.name AS organizer_name, o.email AS organizer_email, o.role AS organizer_role FROM events e JOIN organizers o ON e.organizerID = o.organizerID WHERE e.eventID = ? AND (e.status = 'approved' OR EXISTS (SELECT 1 FROM approvals ap WHERE ap.eventID = e.eventID AND ap.decision = 'approved'))");
mysqli_stmt_bind_param($stmt, 'i', $event_id);
mysqli_stmt_execute($stmt);
$res   = mysqli_stmt_get_result($stmt);
$event = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$event) {
    header('Location: browse_events.php');
    exit;
}

// check if already registered
$stmt = mysqli_prepare($conn, "SELECT registrationID FROM registrations WHERE studentID = ? AND eventID = ? AND status != 'cancelled'");
mysqli_stmt_bind_param($stmt, 'si', $student_id, $event_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_store_result($stmt);
$is_registered = mysqli_stmt_num_rows($stmt) > 0;
mysqli_stmt_close($stmt);

// get registration count
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS cnt FROM registrations WHERE eventID = ? AND status = 'confirmed'");
mysqli_stmt_bind_param($stmt, 'i', $event_id);
mysqli_stmt_execute($stmt);
$res       = mysqli_stmt_get_result($stmt);
$reg_count = mysqli_fetch_assoc($res)['cnt'];
mysqli_stmt_close($stmt);

$spots_left = $event['capacity'] - $reg_count;
$is_full    = $spots_left <= 0;
$is_past    = strtotime($event['date']) < time();

$current_page = 'browse_events.php';
$student_name = $_SESSION['student_name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($event['title']) ?> - DUEMS</title>
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

        /* Payment table */
        .payment-table { width: 100%; font-size: 13px; border-collapse: collapse; }
        .payment-table tr { border-bottom: 1px solid #f0f0f0; }
        .payment-table tr:last-child { border-bottom: none; }
        .payment-table td { padding: 8px 4px; vertical-align: middle; }
        .payment-table td:first-child { color: #888; width: 50%; }
        .payment-table td:last-child { color: #333; font-weight: 500; }
        .payment-total-row td { border-top: 2px solid #e9ecef !important; font-weight: 700 !important; font-size: 14px; padding-top: 10px !important; }
        .payment-total-row td:last-child { color: #6f42c1; }

        /* Custom confirm modal */
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
    <a href="student_dashboard.php" class="<?= $current_page == 'student_dashboard.php' ? 'active' : '' ?>">Dashboard</a>
    <a href="browse_events.php" class="<?= $current_page == 'browse_events.php'            ? 'active' : '' ?>">Browse Events</a>
    <a href="my_registrations.php"  class="<?= $current_page == 'my_registrations.php'  ? 'active' : '' ?>">My Registrations</a>
    <a href="profile.php"           class="<?= $current_page == 'profile.php'           ? 'active' : '' ?>">My Profile</a>
    <a href="support.php"           class="<?= $current_page == 'support.php'           ? 'active' : '' ?>">Support</a>
    <a href="logout.php" class="logout-link">Logout</a>
</div>

<!-- Main Content -->
<div class="main-content">
    <div class="topbar">
        <h4>Event Details</h4>
        <a href="browse_events.php" class="btn btn-sm btn-secondary">Back to Events</a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $msg_type ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div class="row">

        <!-- Left: Main event info -->
        <div class="col-md-8">
            <div class="card">
                <h3 style="color:#2c3e50; margin-bottom:5px;"><?= htmlspecialchars($event['title']) ?></h3>
                <p style="color:#888; font-size:13px; margin-bottom:20px;">Posted by <?= htmlspecialchars($event['organizer_name']) ?></p>
                <hr>

                <div class="row mb-4">
                    <div class="col-sm-6 mb-3">
                        <p style="font-size:12px; color:#888; margin-bottom:3px;">DATE</p>
                        <p style="font-size:15px; color:#333; margin:0;"><?= date('l, F d, Y', strtotime($event['date'])) ?></p>
                    </div>
                    <div class="col-sm-6 mb-3">
                        <p style="font-size:12px; color:#888; margin-bottom:3px;">TIME</p>
                        <p style="font-size:15px; color:#333; margin:0;"><?= date('h:i A', strtotime($event['date'])) ?></p>
                    </div>
                    <div class="col-sm-6 mb-3">
                        <p style="font-size:12px; color:#888; margin-bottom:3px;">LOCATION</p>
                        <p style="font-size:15px; color:#333; margin:0;"><?= htmlspecialchars($event['location']) ?></p>
                    </div>
                    <div class="col-sm-6 mb-3">
                        <p style="font-size:12px; color:#888; margin-bottom:3px;">CAPACITY</p>
                        <p style="font-size:15px; color:#333; margin:0;">
                            <?= $reg_count ?> / <?= $event['capacity'] ?> registered
                            <?php if (!$is_past && !$is_full): ?>
                                <br><small style="color:green;"><?= $spots_left ?> spots available</small>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="col-sm-6 mb-3">
                        <p style="font-size:12px; color:#888; margin-bottom:3px;">DEPARTMENT</p>
                        <p style="font-size:15px; color:#333; margin:0;"><?= htmlspecialchars($event['department']) ?></p>
                    </div>
                    <div class="col-sm-6 mb-3">
                        <p style="font-size:12px; color:#888; margin-bottom:3px;">GENDER</p>
                        <p style="font-size:15px; color:#333; margin:0;"><?= htmlspecialchars($event['gender']) ?></p>
                    </div>
                    <div class="col-sm-6 mb-3">
                        <p style="font-size:12px; color:#888; margin-bottom:3px;">PRICE</p>
                        <p style="font-size:15px; color:#333; margin:0;">
                            <?php if ($event['is_paid']): ?>
                                <span >
                                    <?= number_format($event['price'], 2) ?> SAR
                                </span>
                            <?php else: ?>
                                <span style=>Free</span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>

                <hr>
                <h5 style="margin-bottom:10px;">About this Event</h5>
                <?php if ($event['description']): ?>
                    <p style="color:#555; line-height:1.8;"><?= nl2br(htmlspecialchars($event['description'])) ?></p>
                <?php else: ?>
                    <p style="color:#aaa;">No description provided.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right: Register + Payment + Organizer + Status -->
        <div class="col-md-4">

            <!-- Register box -->
            <div class="card">
                <h5 style="margin-bottom:15px;">Registration</h5>

                <?php if ($is_past): ?>
                    <div class="alert alert-secondary" style="font-size:13px;">This event has already ended.</div>

                <?php elseif ($is_registered): ?>
                    <div class="alert alert-success" style="font-size:13px;">You are registered for this event.</div>
                    <a href="my_registrations.php" class="btn btn-outline-primary w-100" style="font-size:13px;">View My Registrations</a>

                <?php elseif ($is_full): ?>
                    <div class="alert alert-danger" style="font-size:13px;">This event is fully booked.</div>

                <?php else: ?>
                    <p style="font-size:13px; color:#555; margin-bottom:5px;"><?= $spots_left ?> spots remaining out of <?= $event['capacity'] ?></p>
                    <div style="background:#eee; border-radius:5px; height:8px; margin-bottom:15px;">
                        <?php $pct = round(($reg_count / max(1, $event['capacity'])) * 100); ?>
                        <div style="width:<?= $pct ?>%; height:8px; background:<?= $pct >= 80 ? '#e74c3c' : '#27ae60' ?>; border-radius:5px;"></div>
                    </div>
                    <?php if ($event['is_paid']): ?>
                        <a href="payment.php?event_id=<?= $event_id ?>" class="btn btn-success w-100">Pay &amp; Register</a>
                    <?php else: ?>
                        <form method="POST" onsubmit="return showConfirm('Register for this event?', this)">
                            <input type="hidden" name="register_event" value="1">
                            <button type="submit" name="register_event" class="btn btn-success w-100">Register Now</button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

           

            <!-- Organizer info -->
            <div class="card">
                <h5 style="margin-bottom:15px;">Organizer</h5>
                <p style="margin:0; font-size:14px; color:#333;"><strong><?= htmlspecialchars($event['organizer_name']) ?></strong></p>
                <p style="font-size:13px; color:#888; margin-top:4px;"><?= htmlspecialchars($event['organizer_role']) ?></p>
                <p style="font-size:13px; color:#555; margin-top:4px;"><?= htmlspecialchars($event['organizer_email']) ?></p>
            </div>

            <?php if ($event['is_paid']): ?>
           
            <?php endif; ?>


        </div>
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
