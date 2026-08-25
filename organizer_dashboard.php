<?php
session_start();
if (!isset($_SESSION['organizer_id'])) { header('Location: login.php'); exit; }
require_once 'db.php';

$organizerID = (int)$_SESSION['organizer_id'];

$row = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT name, role FROM organizers WHERE organizerID = $organizerID"));
$organizerName = $row['name'] ?? 'Organizer';
$organizerRole = $row['role'] ?? '';
$_SESSION['organizer_name'] = $organizerName;

$totalEvents   = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM events WHERE organizerID = $organizerID"))['c'];
$activeEvents  = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM events WHERE organizerID = $organizerID AND status = 'approved'"))['c'];
$pendingEvents  = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM events WHERE organizerID = $organizerID AND status = 'pending'"))['c'];
$rejectedEvents = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM events WHERE organizerID = $organizerID AND status = 'rejected'"))['c'];

$upcoming = mysqli_query($conn, "
    SELECT e.*, o.name AS organizerName
    FROM events e JOIN organizers o ON e.organizerID = o.organizerID
    WHERE e.date >= NOW() ORDER BY e.date ASC LIMIT 5");

$recentReg = mysqli_query($conn, "
    SELECT r.registrationID, s.name AS student, e.title, r.registrationDate, r.status
    FROM registrations r
    JOIN students s ON r.studentID = s.studentID
    JOIN events   e ON r.eventID   = e.eventID
    WHERE e.organizerID = $organizerID
    ORDER BY r.registrationDate DESC LIMIT 6");

$current_page = 'organizer_dashboard.php';
$page_title   = 'Dashboard';
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
    .sidebar .org-info { text-align:center; padding:10px 15px 15px; border-bottom:1px solid #3d5166; font-size:13px; color:#aab; }
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
    .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center; }
    .modal-overlay.active { display:flex; }
    .modal-box { background:#fff; border-radius:10px; padding:28px 28px 22px; width:320px; box-shadow:0 8px 30px rgba(0,0,0,0.25); text-align:center; }
    .modal-box p { font-size:15px; color:#2c3e50; margin:0 0 20px; font-weight:500; }
    .modal-actions { display:flex; gap:10px; justify-content:center; }
    .modal-actions button { padding:9px 26px; border:none; border-radius:6px; font-size:14px; font-weight:600; cursor:pointer; }
    .modal-btn-yes { background:#2c3e50; color:#f0c040; }
    .modal-btn-yes:hover { background:#3d5166; }
    .modal-btn-no { background:#e5e7eb; color:#374151; }
    .modal-btn-no:hover { background:#d1d5db; }
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
  <h4>DUEMS<br><small style="font-size:11px;color:#aaa;">Jazan University</small></h4>
  <div class="org-info">Hello, <strong style="color:white;"><?= htmlspecialchars($organizerName) ?></strong>
    
  </div>
  <a href="organizer_dashboard.php" class="<?= $current_page==='organizer_dashboard.php'?'active':'' ?>">Dashboard</a>
  <a href="events.php"              class="<?= $current_page==='events.php'?'active':'' ?>">My Events</a>
  <a href="add_event.php"           class="<?= $current_page==='add_event.php'?'active':'' ?>">Add Event</a>
  <a href="registrations.php"       class="<?= $current_page==='registrations.php'?'active':'' ?>">Registered Students</a>
  <a href="support.php"             class="<?= $current_page==='support.php'?'active':'' ?>">Support</a>
  <a href="organizer_profile.php"             class="<?= $current_page==='organizer_profile.php'?'active':'' ?>">My Profile</a>
  <a href="logout.php" class="logout-link">Logout</a>
</div>

<div class="main-content">
  <div class="topbar">
    <h4><?= htmlspecialchars($page_title) ?></h4>
    <span style="font-size:13px;color:#888;">Welcome, <?= htmlspecialchars($organizerName) ?> | <?= htmlspecialchars($organizerRole) ?></span>
  </div>

  <!-- Rejection notice -->
  <?php
  $rejectedList = mysqli_query($conn, "
      SELECT e.title, ap.remarks, ap.decisionDate, a.name AS admin_name
      FROM events e
      JOIN approvals ap ON ap.approvalID = (SELECT MAX(approvalID) FROM approvals WHERE eventID = e.eventID)
      JOIN admins a ON ap.adminID = a.adminID
      WHERE e.organizerID = $organizerID AND e.status = 'rejected'
      ORDER BY ap.decisionDate DESC LIMIT 3");
  while ($rj = mysqli_fetch_assoc($rejectedList)): ?>
    <div class="alert" style="background:#fef2f2;border:1px solid #fca5a5;color:#991b1b;border-radius:8px;font-size:13px;padding:12px 16px;margin-bottom:12px;">
      <strong> Event Rejected:</strong> <?= htmlspecialchars($rj['title']) ?>
      <?php if ($rj['remarks']): ?> — <em><?= htmlspecialchars($rj['remarks']) ?></em><?php endif; ?>
      <span style="color:#aaa;margin-left:8px;font-size:11px;">by <?= htmlspecialchars($rj['admin_name']) ?> on <?= date('M d, Y', strtotime($rj['decisionDate'])) ?></span>
    </div>
  <?php endwhile; ?>
  <!-- Stats -->
  <div class="row mb-4">
    <div class="col-md-4">
      <div class="card text-center">
        <h2 style="color:#2c3e50;"><?= $totalEvents ?></h2>
        <p style="color:#888;margin:0;">Total Events</p>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card text-center">
        <h2 style="color:#27ae60;"><?= $activeEvents ?></h2>
        <p style="color:#888;margin:0;font-size:12px;">Approved Events</p>
      </div>
    </div>
    <div class="col-md-2">
      <div class="card text-center">
        <h2 style="color:#e67e22;"><?= $pendingEvents ?></h2>
        <p style="color:#888;margin:0;font-size:12px;">Pending Approval</p>
      </div>
    </div>
    <div class="col-md-2">
      <div class="card text-center">
        <h2 style="color:#ef4444;"><?= $rejectedEvents ?></h2>
        <p style="color:#888;margin:0;font-size:12px;">Rejected</p>
      </div>
    </div>
  </div>

  <div class="row">
    <div class="col-md-6">
      <div class="card">
        <h5 style="margin-bottom:15px;">Upcoming Events <a href="events.php" style="font-size:13px;float:right;">View all</a></h5>
        <?php if (mysqli_num_rows($upcoming) == 0): ?>
          <p style="color:#888;">No upcoming events.</p>
        <?php else: ?>
          <table class="table table-sm table-hover">
            <thead><tr><th>Event</th><th>Date</th><th>Status</th></tr></thead>
            <tbody>
            <?php while ($e = mysqli_fetch_assoc($upcoming)): ?>
              <tr>
                <td><a href="edit_event.php?id=<?= $e['eventID'] ?>" style="color:#2c3e50;"><?= htmlspecialchars($e['title']) ?></a></td>
                <td><?= date('M d, Y', strtotime($e['date'])) ?></td>
                <td><?= ucfirst($e['status']) ?></td>
              </tr>
            <?php endwhile; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>

    <div class="col-md-6">
      <div class="card">
        <h5 style="margin-bottom:15px;">Recent Registrations <a href="registrations.php" style="font-size:13px;float:right;">View all</a></h5>
        <?php if (mysqli_num_rows($recentReg) == 0): ?>
          <p style="color:#888;">No registrations yet.</p>
        <?php else: ?>
          <table class="table table-sm table-hover">
            <thead><tr><th>Student</th><th>Event</th><th>Status</th></tr></thead>
            <tbody>
            <?php while ($r = mysqli_fetch_assoc($recentReg)): ?>
              <tr>
                <td><?= htmlspecialchars($r['student']) ?></td>
                <td style="max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($r['title']) ?></td>
                <td><?= ucfirst($r['status']) ?></td>
              </tr>
            <?php endwhile; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>
  </div>

</div><!-- /.main-content -->

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
  document.querySelectorAll('.confirm-delete').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
      if (!confirm('Are you sure you want to delete this? This cannot be undone.')) e.preventDefault();
    });
  });
</script>
</body>
</html>
<?php mysqli_close($conn); ?>
