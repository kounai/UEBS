<?php
session_start();
require_once 'db.php';

$isStudent   = isset($_SESSION['student_id']);
$isOrganizer = isset($_SESSION['organizer_id']);

if (!$isStudent && !$isOrganizer) {
    header('Location: login.php');
    exit;
}

if ($isStudent) {
    $student_id = $_SESSION['student_id'];
    $stmt = mysqli_prepare($conn, "SELECT name FROM students WHERE studentID = ?");
    mysqli_stmt_bind_param($stmt, 's', $student_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    $userName     = $row['name'] ?? 'Student';
    $userSubtitle = $student_id;
    $_SESSION['student_name'] = $userName;
} else {
    $organizerID = (int)$_SESSION['organizer_id'];
    $row = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT name, role FROM organizers WHERE organizerID = $organizerID"));
    $userName      = $row['name'] ?? 'Organizer';
    $organizerRole = $row['role'] ?? '';
    $userSubtitle  = $organizerRole;
    $_SESSION['organizer_name'] = $userName;
}

$success = '';
$errors  = [];

$categories = $isStudent
    ? ['Technical Issue','Registration Problem','Payment / Finance','Account & Access','Event Inquiry','Other']
    : ['Technical Issue','Event Approval Delay','Student Registration Problem','Payment / Finance','Account & Access','Venue / Logistics','Other'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category = trim($_POST['category'] ?? '');
    $subject  = trim($_POST['subject']  ?? '');
    $message  = trim($_POST['message']  ?? '');

    if (!$category)             $errors[] = 'Please select a category.';
    if (!$subject)              $errors[] = 'Subject is required.';
    if (strlen($subject) > 255) $errors[] = 'Subject is too long (max 255 characters).';
    if (!$message)              $errors[] = 'Message is required.';
    if (strlen($message) < 20)  $errors[] = 'Message is too short (minimum 20 characters).';

    if (empty($errors)) {
        if ($isStudent) {
            $stmt = mysqli_prepare($conn,
                "INSERT INTO requests (studentID, category, subject, message, senderType, status)
                 VALUES (?, ?, ?, ?, 'student', 'Pending')");
            mysqli_stmt_bind_param($stmt, 'ssss', $student_id, $category, $subject, $message);
        } else {
            $stmt = mysqli_prepare($conn,
                "INSERT INTO requests (organizerID, category, subject, message, senderType, status)
                 VALUES (?, ?, ?, ?, 'organizer', 'Pending')");
            mysqli_stmt_bind_param($stmt, 'isss', $organizerID, $category, $subject, $message);
        }
        if (mysqli_stmt_execute($stmt)) {
            $success = 'Your support request has been submitted. Admin will respond shortly.';
        } else {
            $errors[] = 'Database error: ' . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
    }
}

if ($isStudent) {
    $sid_esc = mysqli_real_escape_string($conn, $student_id);
    $tickets = mysqli_query($conn, "
        SELECT r.*,
               (SELECT rp.reply FROM responses rp WHERE rp.requestID = r.requestID ORDER BY rp.replied_at DESC LIMIT 1) AS last_reply,
               (SELECT a.name  FROM responses rp JOIN admins a ON rp.adminID=a.adminID WHERE rp.requestID=r.requestID ORDER BY rp.replied_at DESC LIMIT 1) AS admin_name
        FROM requests r
        WHERE r.studentID = '$sid_esc' AND r.senderType = 'student'
        ORDER BY r.created_at DESC LIMIT 20");
} else {
    $tickets = mysqli_query($conn, "
        SELECT r.*,
               (SELECT rp.reply FROM responses rp WHERE rp.requestID = r.requestID ORDER BY rp.replied_at DESC LIMIT 1) AS last_reply,
               (SELECT a.name  FROM responses rp JOIN admins a ON rp.adminID=a.adminID WHERE rp.requestID=r.requestID ORDER BY rp.replied_at DESC LIMIT 1) AS admin_name
        FROM requests r
        WHERE r.organizerID = $organizerID AND r.senderType = 'organizer'
        ORDER BY r.created_at DESC LIMIT 20");
}

$current_page = 'support.php';
$page_title   = 'Support';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= htmlspecialchars($page_title) ?> – DUEMS</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"/>
  <style>
    body { background-color: #f0f2f5; }
    .sidebar { width:220px; min-height:100vh; background-color:#2c3e50; color:white; position:fixed; top:0; left:0; padding-top:20px; }
    .sidebar h4 { color:#f0c040; text-align:center; font-size:15px; padding:0 15px 10px; border-bottom:1px solid #3d5166; }
    .sidebar .user-info { text-align:center; padding:10px 15px 15px; border-bottom:1px solid #3d5166; font-size:13px; color:#aab; }
    .sidebar a { display:block; color:#ccc; text-decoration:none; padding:10px 20px; font-size:14px; }
    .sidebar a:hover { background-color:#3d5166; color:white; }
    .sidebar a.active { background-color:#f0c040; color:#2c3e50; font-weight:bold; }
    .sidebar .logout-link { color:#e74c3c; border-top:1px solid #3d5166; margin-top:10px; }
    .sidebar .logout-link:hover { background-color:#e74c3c; color:white; }
    .main-content { margin-left:220px; padding:25px; }
    .topbar { background:white; padding:15px 20px; border-radius:8px; margin-bottom:20px; box-shadow:0 1px 4px rgba(0,0,0,0.08); display:flex; justify-content:space-between; align-items:center; }
    .topbar h4 { margin:0; color:#333; }
    .card { background:white; border-radius:8px; padding:20px; box-shadow:0 1px 4px rgba(0,0,0,0.08); margin-bottom:20px; border:none; }
    .btn-main { background-color:#2c3e50; color:#f0c040 !important; font-weight:bold; border:none; padding:8px 18px; border-radius:6px; font-size:14px; cursor:pointer; text-decoration:none; display:inline-block; }
    .btn-main:hover { background-color:#3d5166; }
    .ticket-item { padding:16px 20px; border-bottom:1px solid #dee2e6; }
    .ticket-item:last-child { border-bottom:none; }
    .badge-pending  { background-color:#ffc107; color:#333;  padding:3px 9px; border-radius:12px; font-size:12px; font-weight:600; }
    .badge-resolved { background-color:#28a745; color:white; padding:3px 9px; border-radius:12px; font-size:12px; font-weight:600; }
    .reply-block { background:#f0fdf4; border:1px solid #bbf7d0; border-radius:6px; padding:10px 12px; font-size:13px; }
  </style>
</head>
<body>

<div class="sidebar">
  <h4>DUEMS<br><small style="font-size:11px;color:#aaa;">Jazan University</small></h4>
  <div class="user-info">
    Hello, <strong style="color:white;"><?= htmlspecialchars($userName) ?></strong>
    
  </div>

  <?php if ($isStudent): ?>
    <a href="student_dashboard.php" class="<?= $current_page==='student_dashboard.php'?'active':'' ?>">Dashboard</a>
    <a href="browse_events.php" class="<?= $current_page==='events.php'?'active':'' ?>">Browse Events</a>
    <a href="my_registrations.php"  class="<?= $current_page==='my_registrations.php'?'active':'' ?>">My Registrations</a>
    <a href="profile.php"           class="<?= $current_page==='profile.php'?'active':'' ?>">My Profile</a>
    <a href="support.php"           class="active">Support</a>
  <?php else: ?>
    <a href="organizer_dashboard.php" class="<?= $current_page==='organizer_dashboard.php'?'active':'' ?>">Dashboard</a>
    <a href="browse_events.php" class="<?= $current_page==='events.php'?'active':'' ?>">My Events</a>
    <a href="add_event.php"           class="<?= $current_page==='add_event.php'?'active':'' ?>">Add Event</a>
    <a href="registrations.php"       class="<?= $current_page==='registrations.php'?'active':'' ?>">Registered Students</a>
    <a href="support.php"             class="active">Support</a>
    <a href="organizer_profile.php"             class="<?= $current_page==='organizer_profile.php'?'active':'' ?>">My Profile</a>
  <?php endif; ?>
  <a href="logout.php" class="logout-link">Logout</a>
</div>

<div class="main-content">
  <div class="topbar">
    <h4><?= htmlspecialchars($page_title) ?></h4>
    <span style="font-size:13px;color:#888;">
      Welcome, <?= htmlspecialchars($userName) ?>
      <?php if (!$isStudent && $userSubtitle): ?> | <?= htmlspecialchars($userSubtitle) ?><?php endif; ?>
    </span>
  </div>

  <?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>
  <?php foreach ($errors as $err): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($err) ?></div>
  <?php endforeach; ?>

  <div class="row">
    <div class="col-md-7">
      <div class="card">
        <h5 style="margin-bottom:4px;">Send a Support Request</h5>
        <p style="color:#888;font-size:13px;margin-bottom:20px;">Fill the form below and we will get back to you shortly.</p>
        <form method="POST" novalidate>
          <div class="mb-3">
            <label class="form-label" style="font-size:13px;font-weight:600;">Your Name</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($userName) ?>" disabled style="background:#f0f2f5;">
          </div>
          <div class="mb-3">
            <label class="form-label" style="font-size:13px;font-weight:600;">Category</label>
            <select name="category" class="form-select">
              <option value="">Select category…</option>
              <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat ?>" <?= (($_POST['category'] ?? '')===$cat)?'selected':'' ?>><?= $cat ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label" style="font-size:13px;font-weight:600;">Subject</label>
            <input type="text" name="subject" class="form-control" maxlength="255"
                   placeholder="Brief description of the issue"
                   value="<?= htmlspecialchars($_POST['subject'] ?? '') ?>">
          </div>
          <div class="mb-3">
            <label class="form-label" style="font-size:13px;font-weight:600;">Message</label>
            <textarea name="message" class="form-control" rows="5"
                      placeholder="Describe your issue in detail…"><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
            <div class="form-text">Minimum 20 characters.</div>
          </div>
          <button type="submit" class="btn-main">Submit Request</button>
        </form>
      </div>
    </div>

    <div class="col-md-5">
      <div class="card" style="padding:0;">
        <div style="padding:16px 20px;border-bottom:1px solid #dee2e6;">
          <h5 style="margin:0;">My Support History</h5>
        </div>
        <?php if (mysqli_num_rows($tickets) === 0): ?>
          <div style="text-align:center;padding:40px;color:#888;">No tickets submitted yet.</div>
        <?php else: ?>
          <div style="max-height:600px;overflow-y:auto;">
            <?php while ($t = mysqli_fetch_assoc($tickets)): ?>
              <div class="ticket-item">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
                  <div>
                    <span style="font-size:11px;color:#888;"><?= htmlspecialchars($t['category']) ?> • #<?= $t['requestID'] ?></span>
                    <div style="font-weight:600;font-size:14px;margin-top:2px;"><?= htmlspecialchars($t['subject']) ?></div>
                  </div>
                  <span class="badge-<?= strtolower($t['status']) ?>"><?= $t['status'] ?></span>
                </div>
                <div style="font-size:13px;color:#888;margin-bottom:8px;line-height:1.5;">
                  <?= nl2br(htmlspecialchars(substr($t['message'], 0, 140))) ?><?= strlen($t['message']) > 140 ? '…' : '' ?>
                </div>
                <?php if ($t['last_reply']): ?>
                  <div class="reply-block">
                    <div style="color:#166534;font-weight:600;margin-bottom:4px;">
                      Admin Response<?php if ($t['admin_name']): ?> — <span style="font-weight:400;"><?= htmlspecialchars($t['admin_name']) ?></span><?php endif; ?>
                    </div>
                    <div style="color:#166534;line-height:1.5;">
                      <?= nl2br(htmlspecialchars(substr($t['last_reply'], 0, 200))) ?><?= strlen($t['last_reply']) > 200 ? '…' : '' ?>
                    </div>
                  </div>
                <?php else: ?>
                  <div style="font-size:12px;color:#aaa;font-style:italic;">Awaiting admin response…</div>
                <?php endif; ?>
                <div style="font-size:11px;color:#aaa;margin-top:8px;"><?= date('M d, Y H:i', strtotime($t['created_at'])) ?></div>
              </div>
            <?php endwhile; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

</body>
</html>
<?php mysqli_close($conn); ?>
