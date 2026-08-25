<?php
session_start();

if (!isset($_SESSION['student_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'db.php';

$student_id   = $_SESSION['student_id'];
$student_name = $_SESSION['student_name'];

if (!isset($_GET['event_id']) || !is_numeric($_GET['event_id'])) {
    header('Location: events.php');
    exit;
}

$event_id = (int)$_GET['event_id'];

// Fetch event (paid + approved only)
$stmt = mysqli_prepare($conn, "SELECT e.*, o.name AS organizer_name FROM events e JOIN organizers o ON e.organizerID = o.organizerID WHERE e.eventID = ? AND e.is_paid = 1 AND (e.status = 'approved' OR EXISTS (SELECT 1 FROM approvals ap WHERE ap.eventID = e.eventID AND ap.decision = 'approved'))");
mysqli_stmt_bind_param($stmt, 'i', $event_id);
mysqli_stmt_execute($stmt);
$res   = mysqli_stmt_get_result($stmt);
$event = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$event) {
    header('Location: events.php');
    exit;
}

// Redirect if already registered
$stmt = mysqli_prepare($conn, "SELECT registrationID FROM registrations WHERE studentID = ? AND eventID = ? AND status != 'cancelled'");
mysqli_stmt_bind_param($stmt, 'si', $student_id, $event_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_store_result($stmt);
$is_registered = mysqli_stmt_num_rows($stmt) > 0;
mysqli_stmt_close($stmt);

if ($is_registered) {
    header('Location: event_detail.php?id=' . $event_id);
    exit;
}

$payment_success = false;
$error = '';

// Handle fake payment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_now'])) {
    $card_number = preg_replace('/\s+/', '', $_POST['card_number'] ?? '');
    $expiry      = trim($_POST['expiry'] ?? '');
    $cvv         = trim($_POST['cvv'] ?? '');
    $card_name   = trim($_POST['card_name'] ?? '');

    if (strlen($card_number) < 16 || empty($expiry) || strlen($cvv) < 3 || empty($card_name)) {
        $error = 'Please fill in all card details correctly.';
    } else {
        // Fake payment succeeded — insert registration
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
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $payment_success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment - DUEMS</title>
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
        .card { background: white; border-radius: 8px; padding: 24px; box-shadow: 0 1px 4px rgba(0,0,0,0.08); margin-bottom: 20px; }
    </style>
</head>
<body>

<div class="sidebar">
    <h4>DUEMS<br><small style="font-size:11px; color:#aaa;">Jazan University</small></h4>
    <div class="student-info">Hello, <strong style="color:white;"><?= htmlspecialchars($student_name) ?></strong></div>
    <a href="student_dashboard.php">Dashboard</a>
    <a href="browse_events.php" class="active">Browse Events</a>
    <a href="my_registrations.php">My Registrations</a>
    <a href="profile.php">My Profile</a>
    <a href="support.php">Support</a>
    <a href="logout.php" class="logout-link">Logout</a>
</div>

<div class="main-content">
    <div class="topbar">
        <h4>Payment</h4>
        <a href="event_detail.php?id=<?= $event_id ?>" class="btn btn-sm btn-secondary">Back to Event</a>
    </div>

    <?php if ($payment_success): ?>
    <!-- SUCCESS -->
    <div class="card" style="max-width:480px; margin:40px auto; text-align:center; padding:36px 24px;">
        <div style="font-size:56px; color:#28a745; margin-bottom:12px;">&#10003;</div>
        <h5 style="color:#2c3e50; margin-bottom:6px;">Payment Successful!</h5>
        <p style="color:#888; font-size:14px; margin-bottom:20px;">You are now registered for <strong><?= htmlspecialchars($event['title']) ?></strong>.</p>
        <hr>
        <table class="table table-sm table-borderless" style="font-size:13px; text-align:left;">
            <tr><td class="text-muted">Date</td><td><?= date('M d, Y - h:i A', strtotime($event['date'])) ?></td></tr>
            <tr><td class="text-muted">Location</td><td><?= htmlspecialchars($event['location']) ?></td></tr>
            <tr><td class="text-muted">Amount Paid</td><td><strong><?= number_format($event['price'], 2) ?> SAR</strong></td></tr>
            <tr><td class="text-muted">Reference #</td><td>PAY-<?= strtoupper(substr(md5($student_id . $event_id . time()), 0, 8)) ?></td></tr>
        </table>
        <hr>
        <a href="my_registrations.php" class="btn btn-success w-100 mb-2">View My Registrations</a>
        <a href="browse_events.php" class="btn btn-outline-secondary w-100">Back to Events</a>
    </div>

    <?php else: ?>
    <!-- PAYMENT FORM -->
    <div class="card" style="max-width:480px; margin:0 auto;">
        <h5 style="margin-bottom:4px; color:#2c3e50;">Complete Payment</h5>
        <p style="font-size:13px; color:#888; margin-bottom:20px;">
            <?= htmlspecialchars($event['title']) ?> &mdash;
            <strong style="color:#6f42c1;"><?= number_format($event['price'], 2) ?> SAR</strong>
        </p>

        <?php if ($error): ?>
            <div class="alert alert-danger" style="font-size:13px;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label" style="font-size:13px; font-weight:600;">Card Number</label>
                <input type="text" name="card_number" class="form-control"
                       placeholder="1234 5678 9012 3456" maxlength="19" autocomplete="off" required>
            </div>
            <div class="mb-3">
                <label class="form-label" style="font-size:13px; font-weight:600;">Cardholder Name</label>
                <input type="text" name="card_name" class="form-control" placeholder="Name on card" required>
            </div>
            <div class="row">
                <div class="col-6 mb-3">
                    <label class="form-label" style="font-size:13px; font-weight:600;">Expiry</label>
                    <input type="text" name="expiry" class="form-control" placeholder="MM/YY" maxlength="5" required>
                </div>
                <div class="col-6 mb-3">
                    <label class="form-label" style="font-size:13px; font-weight:600;">CVV</label>
                    <input type="text" name="cvv" class="form-control" placeholder="***" maxlength="4" autocomplete="off" required>
                </div>
            </div>
            <button type="submit" name="pay_now" class="btn btn-primary w-100">
                Pay <?= number_format($event['price'], 2) ?> SAR
            </button>
        </form>

       
    </div>
    <?php endif; ?>
</div>

</body>
</html>
<?php mysqli_close($conn); ?>
