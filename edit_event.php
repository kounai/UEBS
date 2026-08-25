<?php
session_start();
if (!isset($_SESSION['organizer_id'])) { header('Location: login.php'); exit; }
require_once 'db.php';



$organizerID = (int)$_SESSION['organizer_id'];
$eventID     = (int)($_GET['id'] ?? 0);

$row = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT name, role FROM organizers WHERE organizerID = $organizerID"));
$organizerName = $row['name'] ?? 'Organizer';
$organizerRole = $row['role'] ?? '';
$_SESSION['organizer_name'] = $organizerName;

$errors = [];
$departments = [
    'All','Computer Science','Engineering','Business Administration',
    'Medicine','Pharmacy','Education','Sciences','Arts & Humanities',
    'Law','Architecture','Nursing','Dentistry'
];

$stmt = mysqli_prepare($conn, "SELECT * FROM events WHERE eventID = ? AND organizerID = ?");
mysqli_stmt_bind_param($stmt, 'ii', $eventID, $organizerID);
mysqli_stmt_execute($stmt);
$event = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
if (!$event) { header('Location: events.php?msg=notfound'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title       = trim($_POST['title']       ?? '');
    $description = trim($_POST['description'] ?? '');
    $date        = trim($_POST['date']        ?? '');
    $location    = trim($_POST['location']    ?? '');
    $capacity    = (int)($_POST['capacity']   ?? 0);
    $department  = trim($_POST['department']  ?? '');
    $gender      = trim($_POST['gender']      ?? 'Both');
    $is_paid     = isset($_POST['is_paid'])   ? 'Yes' : 'No';
    $price       = ($is_paid === 'Yes')       ? (float)($_POST['price'] ?? 0) : 0.00;

    if (!$title)       $errors[] = 'Event title is required.';
    if (!$date)        $errors[] = 'Date & time is required.';
    if (!$location)    $errors[] = 'Location is required.';
    if ($capacity < 1) $errors[] = 'Capacity must be at least 1.';
    if (!$department)  $errors[] = 'Department is required.';
    if (!in_array($gender, ['Male','Female','Both'])) $errors[] = 'Invalid gender selection.';
    if ($is_paid === 'Yes' && $price <= 0) $errors[] = 'Please enter a valid price for paid events.';

    $regCount = (int)mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COUNT(*) AS c FROM registrations WHERE eventID=$eventID AND status != 'cancelled'"))['c'];
    if ($capacity < $regCount) $errors[] = "Capacity ($capacity) cannot be less than current registrations ($regCount).";

    if (empty($errors)) {
        $upd = mysqli_prepare($conn,
            "UPDATE events SET title=?, description=?, date=?, location=?, capacity=?,
             department=?, gender=?, is_paid=?, price=?, status='pending'
             WHERE eventID=? AND organizerID=?");
        mysqli_stmt_bind_param($upd, 'ssssisssdii',
            $title, $description, $date, $location, $capacity,
            $department, $gender, $is_paid, $price, $eventID, $organizerID);
        if (mysqli_stmt_execute($upd)) { header('Location: events.php?msg=updated'); exit; }
        else $errors[] = 'Database error: ' . mysqli_error($conn);
    }
    $event = array_merge($event, $_POST);
}

$dateVal      = date('Y-m-d\TH:i', strtotime($event['date']));
$current_page = 'edit_event.php';
$page_title   = 'Edit Event';
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
    <span style="display:block;font-size:11px;color:#aaa;margin-top:2px"><?= htmlspecialchars($organizerRole) ?></span>
  </div>
  <a href="organizer_dashboard.php" class="<?= $current_page==='organizer_dashboard.php'?'active':'' ?>">Dashboard</a>
  <a href="events.php"              class="<?= $current_page==='events.php'?'active':'' ?>">My Events</a>
  <a href="add_event.php"           class="<?= $current_page==='add_event.php'?'active':'' ?>">Add Event</a>
  <a href="registrations.php"       class="<?= $current_page==='registrations.php'?'active':'' ?>">Registered Students</a>
  <a href="support.php"             class="<?= $current_page==='support.php'?'active':'' ?>">Support</a>
  <a href="profile.php"             class="<?= $current_page==='profile.php'?'active':'' ?>">My Profile</a>
  <a href="logout.php" class="logout-link">Logout</a>
</div>

<div class="main-content">
  <div class="topbar">
    <h4><?= htmlspecialchars($page_title) ?></h4>
    <span style="font-size:13px;color:#888;">Welcome, <?= htmlspecialchars($organizerName) ?> | <?= htmlspecialchars($organizerRole) ?></span>
  </div>

  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb" style="font-size:13px;">
      <li class="breadcrumb-item"><a href="events.php" style="color:#2c3e50;">My Events</a></li>
      <li class="breadcrumb-item active">Edit Event</li>
    </ol>
  </nav>

  <?php foreach ($errors as $err): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($err) ?></div>
  <?php endforeach; ?>

  <div class="card">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h5 style="margin:0;">Edit Event <span style="font-size:13px;color:#888;font-weight:400;">ID #<?= $eventID ?></span></h5>
      <div class="d-flex gap-2">
        <a href="registrations.php?event=<?= $eventID ?>" class="btn btn-sm btn-outline-secondary">View Students</a>
        <a href="events.php" class="btn btn-sm btn-outline-secondary">Back</a>
      </div>
    </div>

    <div class="alert alert-info" style="font-size:13px;margin-bottom:20px;">
      Current status: <strong><?= ucfirst($event['status']) ?></strong> — Editing will reset status to <strong>Pending</strong> for re-approval.
    </div>

    <form method="POST" novalidate>
      <div class="row mb-3">
        <div class="col-md-6 mb-3">
          <label class="form-label" style="font-size:13px;font-weight:600;">Event Title *</label>
          <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($event['title']) ?>">
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label" style="font-size:13px;font-weight:600;">Location *</label>
          <input type="text" name="location" class="form-control" value="<?= htmlspecialchars($event['location']) ?>">
        </div>
      </div>

      <div class="row mb-3">
        <div class="col-md-4 mb-3">
          <label class="form-label" style="font-size:13px;font-weight:600;">Date & Time *</label>
          <input type="datetime-local" name="date" class="form-control" value="<?= $dateVal ?>">
        </div>
        <div class="col-md-4 mb-3">
          <label class="form-label" style="font-size:13px;font-weight:600;">Number of Seats *</label>
          <input type="number" name="capacity" class="form-control" min="1" max="5000" value="<?= (int)$event['capacity'] ?>">
        </div>
        <div class="col-md-4 mb-3">
          <label class="form-label" style="font-size:13px;font-weight:600;">Department *</label>
          <select name="department" class="form-select">
            <option value="">Select department…</option>
            <?php foreach ($departments as $d): ?>
              <option value="<?= $d ?>" <?= ($event['department']===$d)?'selected':'' ?>><?= $d ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="row mb-3">
        <div class="col-md-6 mb-3">
          <label class="form-label" style="font-size:13px;font-weight:600;">Gender Allowed *</label>
          <div class="d-flex gap-3 mt-1">
            <?php foreach (['Both','Male','Female'] as $g): ?>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="gender" value="<?= $g ?>" id="g<?= $g ?>"
                       <?= ($event['gender']===$g)?'checked':'' ?>>
                <label class="form-check-label" for="g<?= $g ?>" style="font-size:14px;"><?= $g ?></label>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label" style="font-size:13px;font-weight:600;">Registration Fee</label>
          <div class="d-flex align-items-center gap-3 mt-1">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="is_paid" name="is_paid"
                     <?= ($event['is_paid']==='Yes')?'checked':'' ?> onchange="togglePrice(this)">
              <label class="form-check-label" for="is_paid" style="font-size:14px;">Paid Event</label>
            </div>
            <div id="priceField" class="d-flex align-items-center gap-2"
                 style="display:<?= ($event['is_paid']==='Yes')?'flex':'none' ?>!important;">
              <span style="font-size:13px;color:#888;">SAR</span>
              <input type="number" id="price" name="price" class="form-control form-control-sm"
                     min="0.01" step="0.01" style="width:110px;" value="<?= $event['price'] ?>">
            </div>
          </div>
        </div>
      </div>

      <div class="mb-4">
        <label class="form-label" style="font-size:13px;font-weight:600;">Description</label>
        <textarea name="description" class="form-control" rows="4"><?= htmlspecialchars($event['description']) ?></textarea>
      </div>

      <div class="d-flex gap-2">
        <button type="submit" class="btn-main">Save Changes</button>
        <a href="events.php?delete=<?= $eventID ?>" class="btn btn-outline-danger confirm-delete">Delete Event</a>
        <a href="events.php" class="btn btn-outline-secondary">Cancel</a>
      </div>
    </form>
  </div>

</div><!-- /.main-content -->

<script>
  function togglePrice(cb) {
    document.getElementById('priceField').style.display = cb.checked ? 'flex' : 'none';
    if (!cb.checked) document.getElementById('price').value = '';
  }
  var _pendingForm = null;
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
