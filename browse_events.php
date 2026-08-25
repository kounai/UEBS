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

// fetch student gender
$stmt = mysqli_prepare($conn, "SELECT gender FROM students WHERE studentID = ?");
mysqli_stmt_bind_param($stmt, 's', $student_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$student_row = mysqli_fetch_assoc($res);
$student_gender = $student_row['gender'] ?? ''; // 'Male' or 'Female'
mysqli_stmt_close($stmt);

// handle register button click
if (isset($_POST['register_event'])) {
    $event_id = (int)$_POST['event_id'];

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
        // gender eligibility check
        $stmt = mysqli_prepare($conn, "SELECT gender FROM events WHERE eventID = ?");
        mysqli_stmt_bind_param($stmt, 'i', $event_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $ev_gender_row = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);
        $ev_gender = $ev_gender_row['gender'] ?? 'Both';

        if ($ev_gender !== 'Both' && $ev_gender !== $student_gender) {
            $message  = 'You are not eligible to register for this event. It is open for ' . $ev_gender . ' students only.';
            $msg_type = 'danger';
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
        } // end gender eligibility else
    }
}

// filters
$search        = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_gender = isset($_GET['gender']) ? trim($_GET['gender']) : '';
$filter_paid   = isset($_GET['paid'])   ? $_GET['paid']         : '';

// build query dynamically
$where  = "(e.status = 'approved' OR EXISTS (SELECT 1 FROM approvals ap WHERE ap.eventID = e.eventID AND ap.decision = 'approved'))";
$params = [$student_id];   // must be first — matches the ? in the already_registered subquery
$types  = 's';

if ($search != '') {
    $like    = '%' . $search . '%';
    $where  .= " AND (e.title LIKE ? OR e.location LIKE ? OR e.department LIKE ?)";
    $params[] = $like; $params[] = $like; $params[] = $like;
    $types   .= 'sss';
}

if ($filter_gender !== '' && in_array($filter_gender, ['Male','Female'])) {
    $where  .= " AND (e.gender = ? OR e.gender = 'Both')";
    $params[] = $filter_gender;
    $types   .= 's';
}

if ($filter_paid === '1') {
    $where .= " AND e.is_paid = 'Yes'";
} elseif ($filter_paid === '0') {
    $where .= " AND e.is_paid = 'No'";
}

$sql = "SELECT e.*, o.name AS organizer_name,
               (SELECT COUNT(*) FROM registrations r WHERE r.eventID = e.eventID AND r.status = 'confirmed') AS reg_count,
               (SELECT COUNT(*) FROM registrations r WHERE r.eventID = e.eventID AND r.studentID = ? AND r.status != 'cancelled') AS already_registered
        FROM events e
        JOIN organizers o ON e.organizerID = o.organizerID
        WHERE $where
        ORDER BY e.date ASC";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$events = mysqli_stmt_get_result($stmt);
mysqli_stmt_close($stmt);

$current_page = 'events.php';
$student_name = $_SESSION['student_name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Events - DUEMS</title>
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
        .badge-confirmed { background-color: #28a745; color: white; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; }
        .badge-cancelled { background-color: #dc3545; color: white; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; }
        .badge-paid { display: inline-block; font-size: 13px; font-weight: 700; color: #000000; line-height: 1.4; }
        .badge-free { display: inline-block; font-size: 13px; font-weight: 700; color: #000000; }
        .table td { vertical-align: middle; font-size: 13.5px; }
        .table th { font-size: 13px; white-space: nowrap; }
        .event-title { font-weight: 700; color: #2c3e50; text-decoration: none; font-size: 14px; }
        .event-title:hover { color: #f0c040; }
        .event-desc { color: #999; font-size: 12px; display: block; max-width: 260px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 2px; }
        .seats-badge { font-size: 13px; font-weight: 600; color: #2c3e50; }
        .seats-warning { color: #e67e22; font-size: 11px; display: block; }
        .btn-register { font-size: 12px; white-space: nowrap; padding: 5px 14px; font-weight: 600; border-radius: 6px; }
        
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

<div class="modal-overlay" id="confirmModal">
    <div class="modal-box">
        <p id="confirmMsg">Are you sure?</p>
        <div class="modal-actions">
            <button class="modal-btn-yes" id="confirmYes">Confirm</button>
            <button class="modal-btn-no"  id="confirmNo">Cancel</button>
        </div>
    </div>
</div>

<div class="sidebar">
    <h4>DUEMS<br><small style="font-size:11px; color:#aaa;">Jazan University</small></h4>
    <div class="student-info">Hello, <strong style="color:white;"><?= htmlspecialchars($student_name) ?></strong></div>
    <a href="student_dashboard.php" class="<?= $current_page == 'student_dashboard.php' ? 'active' : '' ?>">Dashboard</a>
    <a href="events.php"            class="<?= $current_page == 'events.php'            ? 'active' : '' ?>">Browse Events</a>
    <a href="my_registrations.php"  class="<?= $current_page == 'my_registrations.php'  ? 'active' : '' ?>">My Registrations</a>
    <a href="profile.php"           class="<?= $current_page == 'profile.php'           ? 'active' : '' ?>">My Profile</a>
    <a href="support.php"           class="<?= $current_page == 'support.php'           ? 'active' : '' ?>">Support</a>
    <a href="logout.php" class="logout-link">Logout</a>
</div>

<div class="main-content">
    <div class="topbar">
        <h4>Browse Events</h4>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $msg_type ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <!-- Search & Filters -->
    <div class="card">
        <form method="GET" action="events.php">
            <div class="row g-2 align-items-end">
                <div class="col-md-5">
                    <input type="text" name="search" class="form-control"
                           placeholder="Search by name, location, or department..."
                           value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-2">
                    <select name="gender" class="form-select">
                        <option value="">All</option>
                        <option value="Male"   <?= $filter_gender == 'Male'   ? 'selected' : '' ?>>Male</option>
                        <option value="Female" <?= $filter_gender == 'Female' ? 'selected' : '' ?>>Female</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="paid" class="form-select">
                        <option value="">Free &amp; Paid</option>
                        <option value="0" <?= $filter_paid === '0' ? 'selected' : '' ?>>Free Only</option>
                        <option value="1" <?= $filter_paid === '1' ? 'selected' : '' ?>>Paid Only</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill">Search</button>
                    <?php if ($search || $filter_gender || $filter_paid !== ''): ?>
                        <a href="events.php" class="btn btn-secondary">Clear</a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>

    <!-- Events table -->
    <div class="card">
        <?php if (mysqli_num_rows($events) == 0): ?>
            <p style="color:#888;">No events found.</p>
        <?php else: ?>
            <table class="table table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>Event Name</th>
                        <th>Organizer</th>
                        <th>Date</th>
                        <th>Location</th>
                        <th>Department</th>
                        <th>Gender</th>
                        <th>Seats</th>
                        <th>Price</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php $i = 1; while ($ev = mysqli_fetch_assoc($events)):
                    $is_full       = $ev['reg_count'] >= $ev['capacity'];
                    $is_registered = $ev['already_registered'] > 0;
                    $is_past       = strtotime($ev['date']) < time();
                    $spots_left    = $ev['capacity'] - $ev['reg_count'];
                    $ev_gender     = $ev['gender'];
                    $gender_ok     = ($ev_gender === 'Both' || $ev_gender === $student_gender);
                ?>
                    <tr>
                        <td style="color:#aaa; font-size:13px;"><?= $i++ ?></td>
                        <td style="max-width:280px;">
                            <a href="event_detail.php?id=<?= $ev['eventID'] ?>" class="event-title">
                                <?= htmlspecialchars($ev['title']) ?>
                            </a>
                            <?php if ($ev['description']): ?>
                                <span class="event-desc"><?= htmlspecialchars(substr($ev['description'], 0, 70)) ?>...</span>
                            <?php endif; ?>
                        </td>
                        <td style="white-space:nowrap;"><?= htmlspecialchars($ev['organizer_name']) ?></td>
                        <td style="white-space:nowrap;">
                            <span style="font-weight:600; color:#2c3e50;"><?= date('M d, Y', strtotime($ev['date'])) ?></span>
                            <br><small style="color:#999;"><?= date('h:i A', strtotime($ev['date'])) ?></small>
                        </td>
                        <td style="max-width:120px;"><?= htmlspecialchars($ev['location'])  ?></td>
                        <td style="white-space:nowrap;">
                            <?php $dept = $ev['department']; ?>
                            <?= ($dept && $dept !== 'All') ? htmlspecialchars($dept) : '<span style="color:#000000;">All</span>' ?>
                        </td>
                        <td style="white-space:nowrap;">
                            <?php
                                $g = $ev['gender'];
                                $gColor = $g === 'Male' ? '#000000' : ($g === 'Female' ? '#000000' : '#000000');
                            ?>
                            <span style="color:<?= $gColor ?>; font-weight:600;"><?= htmlspecialchars($g) ?></span>
                        </td>
                        <td style="white-space:nowrap;" class="seats-badge">
                            <?= $ev['reg_count'] ?>/<?= $ev['capacity'] ?>
                            <?php if ($spots_left <= 5 && !$is_full && !$is_past): ?>
                                <span class="seats-warning">⚠ <?= $spots_left ?> left</span>
                            <?php endif; ?>
                        </td>
                        <td style="white-space:nowrap;">
                            <?php if ($ev['is_paid'] === 'Yes'): ?>
                                <span class="badge-paid"><?= number_format($ev['price'], 2) ?><br>SAR</span>
                            <?php else: ?>
                                <span class="badge-free">Free</span>
                            <?php endif; ?>
                        </td>
                        <td style="white-space:nowrap;">
                            <?php if ($is_registered): ?>
                                <span class="badge-confirmed"> Registered</span>
                            <?php elseif ($is_past): ?>
                                <span style="color:#aaa; font-size:12px;">Ended</span>
                            <?php elseif (!$gender_ok): ?>
                                <span style="color:#dc3545; font-size:12px; font-weight:600;" title="This event is for <?= htmlspecialchars($ev_gender) ?> students only"> <?= htmlspecialchars($ev_gender) ?> Only</span>
                            <?php elseif ($is_full): ?>
                                <span class="badge-cancelled">Full</span>
                            <?php elseif ($ev['is_paid'] === 'Yes'): ?>
                                <a href="payment.php?event_id=<?= $ev['eventID'] ?>"
                                   class="btn btn-sm btn-success btn-register"
                                   onclick="return showConfirmUrl('This is a paid event (<?= number_format($ev['price'], 2) ?> SAR). Proceed to payment?', this.href)">Register</a>
                            <?php else: ?>
                                <form method="POST" style="margin:0;" onsubmit="return showConfirm('Register for this event?', this)">
                                    <input type="hidden" name="register_event" value="1">
                                    <input type="hidden" name="event_id" value="<?= $ev['eventID'] ?>">
                                    <button type="submit" class="btn btn-sm btn-success btn-register">Register</button>
                                </form>
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
    var _pendingUrl  = null;

    function showConfirm(msg, form) {
        document.getElementById('confirmMsg').textContent = msg;
        document.getElementById('confirmModal').classList.add('active');
        _pendingForm = form;
        _pendingUrl  = null;
        return false;
    }

    function showConfirmUrl(msg, url) {
        document.getElementById('confirmMsg').textContent = msg;
        document.getElementById('confirmModal').classList.add('active');
        _pendingUrl  = url;
        _pendingForm = null;
        return false;
    }

    document.getElementById('confirmYes').addEventListener('click', function() {
        document.getElementById('confirmModal').classList.remove('active');
        if (_pendingForm) { _pendingForm.submit(); _pendingForm = null; }
        if (_pendingUrl)  { window.location.href = _pendingUrl; _pendingUrl = null; }
    });
    document.getElementById('confirmNo').addEventListener('click', function() {
        document.getElementById('confirmModal').classList.remove('active');
        _pendingForm = null;
        _pendingUrl  = null;
    });
</script>
</body>
</html>
<?php mysqli_close($conn); ?>
