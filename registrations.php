<?php
session_start();
if (!isset($_SESSION['organizer_id'])) { header('Location: login.php'); exit; }
require_once 'db.php';



$organizerID  = (int)$_SESSION['organizer_id'];

$row = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT name, role FROM organizers WHERE organizerID = $organizerID"));
$organizerName = $row['name'] ?? 'Organizer';
$organizerRole = $row['role'] ?? '';
$_SESSION['organizer_name'] = $organizerName;

$filterEvent  = (int)($_GET['event']  ?? 0);
$search       = trim($_GET['search']  ?? '');
$filterStatus = $_GET['status']       ?? '';

$eventsQuery = mysqli_query($conn,
    "SELECT eventID, title FROM events WHERE organizerID = $organizerID ORDER BY date DESC");
$myEvents = [];
while ($ev = mysqli_fetch_assoc($eventsQuery)) $myEvents[] = $ev;

$where = "WHERE e.organizerID = $organizerID";
if ($filterEvent)  $where .= " AND r.eventID = $filterEvent";
if ($filterStatus) $where .= " AND r.status = '" . mysqli_real_escape_string($conn, $filterStatus) . "'";
if ($search) {
    $s = mysqli_real_escape_string($conn, $search);
    $where .= " AND (s.name LIKE '%$s%' OR s.email LIKE '%$s%' OR s.studentID LIKE '%$s%')";
}

$registrations = mysqli_query($conn, "
    SELECT r.registrationID, r.registrationDate, r.status AS regStatus,
           s.studentID, s.name AS studentName, s.email, s.department AS studentDept,
           e.eventID, e.title AS eventTitle, e.date AS eventDate, e.capacity,
           e.is_paid, e.price,
           (SELECT COUNT(*) FROM registrations rx WHERE rx.eventID = e.eventID AND rx.status != 'cancelled') AS regCount
    FROM registrations r
    JOIN students s ON r.studentID = s.studentID
    JOIN events   e ON r.eventID   = e.eventID
    $where ORDER BY r.registrationDate DESC");

$totalReg    = (int)mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS c FROM registrations r JOIN events e ON r.eventID=e.eventID
     WHERE e.organizerID=$organizerID AND r.status='confirmed'"))['c'];
$totalCancel = (int)mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS c FROM registrations r JOIN events e ON r.eventID=e.eventID
     WHERE e.organizerID=$organizerID AND r.status='cancelled'"))['c'];

// CSV export — must run before any HTML
if (isset($_GET['export'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="registrations_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Student ID','Name','Email','Department','Event','Event Date','Registered On','Fee','Status']);
    while ($r = mysqli_fetch_assoc($registrations)) {
        fputcsv($out, [
            $r['studentID'], $r['studentName'], $r['email'], $r['studentDept'],
            $r['eventTitle'], date('Y-m-d', strtotime($r['eventDate'])),
            date('Y-m-d H:i', strtotime($r['registrationDate'])),
            $r['is_paid'] === 'Yes' ? 'SAR ' . $r['price'] : 'Free',
            $r['regStatus']
        ]);
    }
    fclose($out);
    mysqli_close($conn);
    exit;
}

$current_page = 'registrations.php';
$page_title   = 'Registered Students';
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

  <!-- Stats -->
  <div class="row mb-4">
    <div class="col-md-4">
      <div class="card text-center">
        <h2 style="color:#27ae60;"><?= $totalReg ?></h2>
        <p style="color:#888;margin:0;">Confirmed Registrations</p>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card text-center">
        <h2 style="color:#dc3545;"><?= $totalCancel ?></h2>
        <p style="color:#888;margin:0;">Cancelled</p>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card text-center">
        <h2 style="color:#2c3e50;"><?= count($myEvents) ?></h2>
        <p style="color:#888;margin:0;">My Events</p>
      </div>
    </div>
  </div>

  <!-- Filters -->
  <div class="card mb-3">
    <form method="GET" class="d-flex gap-2 flex-wrap align-items-end">
      <div style="flex:1;min-width:180px;">
        <label style="display:block;margin-bottom:4px;font-size:13px;font-weight:600;">Search Student</label>
        <input type="text" name="search" class="form-control form-control-sm"
               placeholder="Name, email, or ID…" value="<?= htmlspecialchars($search) ?>">
      </div>
      <div style="min-width:220px;">
        <label style="display:block;margin-bottom:4px;font-size:13px;font-weight:600;">Filter by Event</label>
        <select name="event" class="form-select form-select-sm">
          <option value="">All My Events</option>
          <?php foreach ($myEvents as $ev): ?>
            <option value="<?= $ev['eventID'] ?>" <?= $filterEvent===$ev['eventID']?'selected':'' ?>>
              <?= htmlspecialchars($ev['title']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="min-width:140px;">
        <label style="display:block;margin-bottom:4px;font-size:13px;font-weight:600;">Status</label>
        <select name="status" class="form-select form-select-sm">
          <option value="">All</option>
          <option value="confirmed" <?= $filterStatus==='confirmed'?'selected':'' ?>>Confirmed</option>
          <option value="cancelled" <?= $filterStatus==='cancelled'?'selected':'' ?>>Cancelled</option>
        </select>
      </div>
      <div class="d-flex gap-2 align-items-end">
        <button type="submit" class="btn-main btn-sm">Filter</button>
        <a href="registrations.php" class="btn btn-sm btn-outline-secondary">Reset</a>
      </div>
    </form>
  </div>

  <!-- Table -->
  <div class="card">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h5 style="margin:0;">Registered Students
        <span style="font-size:13px;font-weight:400;color:#888;">(<?= mysqli_num_rows($registrations) ?> records)</span>
      </h5>
      <?php if (mysqli_num_rows($registrations) > 0): ?>
        <a href="registrations.php?export=1&event=<?= $filterEvent ?>&status=<?= $filterStatus ?>&search=<?= urlencode($search) ?>"
           class="btn btn-sm btn-outline-secondary">Export CSV</a>
      <?php endif; ?>
    </div>
    <div class="table-responsive">
      <table class="table table-hover">
        <thead>
          <tr><th>#</th><th>Student ID</th><th>Name</th><th>Email</th><th>Department</th><th>Event</th><th>Event Date</th><th>Registered On</th><th>Fee</th><th>Status</th></tr>
        </thead>
        <tbody>
        <?php if (mysqli_num_rows($registrations) === 0): ?>
          <tr><td colspan="10" style="text-align:center;padding:30px;color:#888;">No registrations found</td></tr>
        <?php else: $i=1; while ($r = mysqli_fetch_assoc($registrations)): ?>
          <tr>
            <td style="color:#888;"><?= $i++ ?></td>
            <td><strong><?= htmlspecialchars($r['studentID']) ?></strong></td>
            <td><?= htmlspecialchars($r['studentName']) ?></td>
            <td style="font-size:12px;color:#888;"><?= htmlspecialchars($r['email']) ?></td>
            <td style="font-size:13px;"><?= htmlspecialchars($r['studentDept']) ?></td>
            <td>
              <a href="edit_event.php?id=<?= $r['eventID'] ?>" style="color:#2c3e50;font-weight:500;"><?= htmlspecialchars($r['eventTitle']) ?></a>
              <div style="font-size:12px;color:#888;"><?= $r['regCount'] ?>/<?= $r['capacity'] ?> seats</div>
            </td>
            <td style="white-space:nowrap;font-size:13px;"><?= date('M d, Y', strtotime($r['eventDate'])) ?></td>
            <td style="white-space:nowrap;font-size:12px;color:#888;"><?= date('M d, Y H:i', strtotime($r['registrationDate'])) ?></td>
            <td style="font-size:13px;"><?= $r['is_paid']==='Yes' ? 'SAR '.number_format($r['price'],2) : 'Free' ?></td>
            <td style="font-size:13px;"><?= ucfirst($r['regStatus']) ?></td>
          </tr>
        <?php endwhile; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div><!-- /.main-content -->

<script>
  var _pendingForm = null;
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
