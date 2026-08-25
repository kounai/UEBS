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

// Delete
if (isset($_GET['delete'])) {
    $delID = (int)$_GET['delete'];
    $chk   = mysqli_prepare($conn, "SELECT eventID FROM events WHERE eventID = ? AND organizerID = ?");
    mysqli_stmt_bind_param($chk, 'ii', $delID, $organizerID);
    mysqli_stmt_execute($chk);
    mysqli_stmt_store_result($chk);
    if (mysqli_stmt_num_rows($chk) > 0) {
        mysqli_query($conn, "DELETE FROM registrations WHERE eventID = $delID");
        mysqli_query($conn, "DELETE FROM events WHERE eventID = $delID AND organizerID = $organizerID");
        header('Location: events.php?msg=deleted'); exit;
    }
}

$search  = trim($_GET['search'] ?? '');
$fStatus = $_GET['status'] ?? '';
$fDept   = $_GET['dept']   ?? '';
$msg     = $_GET['msg']    ?? '';

$where = "WHERE e.organizerID = $organizerID";
if ($search)  $where .= " AND e.title LIKE '%" . mysqli_real_escape_string($conn, $search) . "%'";
if ($fStatus) $where .= " AND e.status = '" . mysqli_real_escape_string($conn, $fStatus) . "'";
if ($fDept)   $where .= " AND e.department = '" . mysqli_real_escape_string($conn, $fDept) . "'";

$events = mysqli_query($conn, "
    SELECT e.*,
           (SELECT COUNT(*) FROM registrations r WHERE r.eventID = e.eventID AND r.status != 'cancelled') AS regCount,
           ap.decision   AS ap_decision,
           ap.remarks    AS ap_remarks,
           ap.decisionDate AS ap_date,
           a.name        AS ap_admin_name
    FROM events e
    LEFT JOIN approvals ap ON ap.approvalID = (
        SELECT MAX(approvalID) FROM approvals WHERE eventID = e.eventID
    )
    LEFT JOIN admins a ON ap.adminID = a.adminID
    $where ORDER BY e.date DESC");

$current_page = 'events.php';
$page_title   = 'My Events';
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

  <?php if ($msg === 'deleted'): ?><div class="alert alert-danger">Event deleted successfully.</div>
  <?php elseif ($msg === 'added'): ?><div class="alert alert-success">Event added successfully.</div>
  <?php elseif ($msg === 'updated'): ?><div class="alert alert-success">Event updated successfully.</div>
  <?php endif; ?>

  <!-- Filter Bar -->
  <div class="card mb-3">
    <form method="GET" class="d-flex gap-2 flex-wrap align-items-end">
      <div style="flex:1;min-width:160px;">
        <label style="display:block;margin-bottom:4px;font-size:13px;font-weight:600;">Search</label>
        <input type="text" name="search" class="form-control form-control-sm" placeholder="Event title…" value="<?= htmlspecialchars($search) ?>">
      </div>
      <div style="min-width:140px;">
        <label style="display:block;margin-bottom:4px;font-size:13px;font-weight:600;">Status</label>
        <select name="status" class="form-select form-select-sm">
          <option value="">All Statuses</option>
          <?php foreach (['pending','approved','rejected','cancelled'] as $st): ?>
            <option value="<?= $st ?>" <?= $fStatus===$st?'selected':'' ?>><?= ucfirst($st) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="min-width:160px;">
        <label style="display:block;margin-bottom:4px;font-size:13px;font-weight:600;">Department</label>
        <select name="dept" class="form-select form-select-sm">
         
          <?php $depts = mysqli_query($conn, "SELECT DISTINCT department FROM events WHERE organizerID=$organizerID ORDER BY department");
          while ($d = mysqli_fetch_assoc($depts)): ?>
            <option value="<?= htmlspecialchars($d['department']) ?>" <?= $fDept===$d['department']?'selected':'' ?>><?= htmlspecialchars($d['department']) ?></option>
          <?php endwhile; ?>
        </select>
      </div>
      <div class="d-flex gap-2 align-items-end">
        <button type="submit" class="btn-main btn-sm">Filter</button>
        <a href="events.php" class="btn btn-sm btn-outline-secondary">Reset</a>
      </div>
      <div class="ms-auto align-self-end">
        <a href="add_event.php" class="btn-main">Add Event</a>
      </div>
    </form>
  </div>

  <!-- Events Table -->
  <div class="card">
    <h5 style="margin-bottom:15px;">My Events <span style="font-size:13px;font-weight:400;color:#888;">(<?= mysqli_num_rows($events) ?> records)</span></h5>
    <div class="table-responsive">
      <table class="table table-hover">
        <thead>
          <tr><th>#</th><th>Title</th><th>Date</th><th>Location</th><th>Department</th><th>Seats</th><th>Registered</th><th>Gender</th><th>Fee</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
        <?php if (mysqli_num_rows($events) === 0): ?>
          <tr><td colspan="11" style="text-align:center;padding:30px;color:#888;">No events found. <a href="add_event.php">Add your first event</a></td></tr>
        <?php else: $i=1; while ($e = mysqli_fetch_assoc($events)):
            $remaining = $e['capacity'] - $e['regCount']; ?>
          <tr>
            <td style="color:#888;"><?= $i++ ?></td>
            <td>
              <div style="font-weight:600;"><?= htmlspecialchars($e['title']) ?></div>
              <?php if ($e['description']): ?>
                <div style="font-size:12px;color:#888;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($e['description']) ?></div>
              <?php endif; ?>
            </td>
            <td style="white-space:nowrap;">
              <?= date('M d, Y', strtotime($e['date'])) ?><br>
              <span style="font-size:12px;color:#888;"><?= date('h:i A', strtotime($e['date'])) ?></span>
            </td>
            <td style="font-size:13px;"><?= htmlspecialchars($e['location']) ?></td>
            <td style="font-size:13px;"><?= htmlspecialchars($e['department']) ?></td>
            <td><?= $e['capacity'] ?><br><span style="font-size:12px;color:#888;"><?= $remaining ?> left</span></td>
            <td><a href="event_registrations.php?event=<?= $e['eventID'] ?>" style="color:#2c3e50;font-weight:600;"><?= $e['regCount'] ?></a></td>
            <td style="font-size:13px;"><?= $e['gender'] ?></td>
            <td style="font-size:13px;"><?= $e['is_paid']==='Yes' ? 'SAR '.number_format($e['price'],2) : 'Free' ?></td>
            <td>
              <?php
                $badgeColor = ['pending'=>'#f59e0b','approved'=>'#22c55e','rejected'=>'#ef4444','cancelled'=>'#94a3b8'];
                $bc = $badgeColor[$e['status']] ?? '#94a3b8';
              ?>
              <span style="background:<?= $bc ?>;color:white;padding:3px 9px;border-radius:12px;font-size:12px;font-weight:600;">
                <?= ucfirst($e['status']) ?>
              </span>
              <?php if ($e['status']==='pending'): ?>
                
              <?php elseif ($e['status']==='rejected' && $e['ap_remarks']): ?>
                <div style="font-size:11px;color:#ef4444;margin-top:3px;" title="<?= htmlspecialchars($e['ap_remarks']) ?>">
                  💬 <?= htmlspecialchars(substr($e['ap_remarks'],0,40)) ?><?= strlen($e['ap_remarks'])>40?'…':'' ?>
                </div>
              <?php elseif ($e['status']==='approved' && $e['ap_admin_name']): ?>
                <div style="font-size:11px;color:#166534;margin-top:3px;">by <?= htmlspecialchars($e['ap_admin_name']) ?></div>
              <?php endif; ?>
            </td>
            <td>
              <div class="d-flex gap-1">
                <a href="edit_event.php?id=<?= $e['eventID'] ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                <a href="event_registrations.php?event=<?= $e['eventID'] ?>" class="btn btn-sm btn-outline-secondary">Students</a>
                <form method="GET" action="events.php" style="display:inline;"
                      onsubmit="return showConfirm('Are you sure you want to delete this event? This cannot be undone.', this)">
                  <input type="hidden" name="delete" value="<?= $e['eventID'] ?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endwhile; endif; ?>
        </tbody>
      </table>
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

</script>
</body>
</html>
<?php mysqli_close($conn); ?>
