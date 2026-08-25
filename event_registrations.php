<?php
session_start();
require_once 'db.php';

// ── Auth: organizer OR admin ──────────────────────────────────────────────
$isAdmin     = isset($_SESSION['admin_id']);
$isOrganizer = isset($_SESSION['organizer_id']);

if (!$isAdmin && !$isOrganizer) {
    header('Location: login.php');
    exit;
}

$event_id = (int)($_GET['event'] ?? 0);
if (!$event_id) {
    header($isAdmin ? 'Location: admin_events.php' : 'Location: events.php');
    exit;
}

// ── Load event info ───────────────────────────────────────────────────────
if ($isOrganizer) {
    $organizerID = (int)$_SESSION['organizer_id'];
    $row = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT name, role FROM organizers WHERE organizerID = $organizerID"));
    $userName     = $row['name'] ?? 'Organizer';
    $userSubtitle = $row['role'] ?? '';
    $_SESSION['organizer_name'] = $userName;

    // Make sure this event belongs to this organizer
    $stmt = mysqli_prepare($conn,
        "SELECT e.*, o.name AS org_name FROM events e
         JOIN organizers o ON e.organizerID = o.organizerID
         WHERE e.eventID = ? AND e.organizerID = ?");
    mysqli_stmt_bind_param($stmt, 'ii', $event_id, $organizerID);
    mysqli_stmt_execute($stmt);
    $evInfo = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$evInfo) {
        header('Location: events.php');
        exit;
    }
    $backURL   = 'events.php';
    $backLabel = 'My Events';

} else {
    $userName     = $_SESSION['admin_name'] ?? 'Admin';
    $userSubtitle = '';

    $stmt = mysqli_prepare($conn,
        "SELECT e.*, o.name AS org_name FROM events e
         LEFT JOIN organizers o ON e.organizerID = o.organizerID
         WHERE e.eventID = ?");
    mysqli_stmt_bind_param($stmt, 'i', $event_id);
    mysqli_stmt_execute($stmt);
    $evInfo = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$evInfo) {
        header('Location: admin_events.php');
        exit;
    }
    $backURL   = 'admin_events.php';
    $backLabel = 'All Events';
}

// ── Filters ───────────────────────────────────────────────────────────────
$search       = trim($_GET['search'] ?? '');
$filterStatus = $_GET['status'] ?? '';

$where = "WHERE r.eventID = $event_id";
if ($filterStatus) $where .= " AND r.status = '" . mysqli_real_escape_string($conn, $filterStatus) . "'";
if ($search) {
    $s = mysqli_real_escape_string($conn, $search);
    $where .= " AND (s.name LIKE '%$s%' OR s.email LIKE '%$s%' OR s.studentID LIKE '%$s%')";
}

$registrations = mysqli_query($conn, "
    SELECT r.registrationID, r.registrationDate, r.status AS regStatus,
           s.studentID, s.name AS studentName, s.email,
           s.department AS studentDept, s.gender
    FROM registrations r
    JOIN students s ON r.studentID = s.studentID
    $where
    ORDER BY r.registrationDate DESC");

// ── Counts ────────────────────────────────────────────────────────────────
$cntConfirmed = (int)mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS c FROM registrations WHERE eventID=$event_id AND status='confirmed'"))['c'];
$cntCancelled = (int)mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS c FROM registrations WHERE eventID=$event_id AND status='cancelled'"))['c'];
$seatsLeft    = $evInfo['capacity'] - $cntConfirmed;

// ── CSV Export ────────────────────────────────────────────────────────────
if (isset($_GET['export'])) {
    header('Content-Type: text/csv');
    $safeName = preg_replace('/[^a-z0-9]/i', '_', $evInfo['title']);
    header('Content-Disposition: attachment; filename="registrations_' . $safeName . '_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Student ID','Name','Email','Department','Gender','Registered On','Status']);
    while ($r = mysqli_fetch_assoc($registrations)) {
        fputcsv($out, [
            $r['studentID'],
            $r['studentName'],
            $r['email'],
            $r['studentDept'],
            $r['gender'],
            date('Y-m-d H:i', strtotime($r['registrationDate'])),
            ucfirst($r['regStatus'])
        ]);
    }
    fclose($out);
    mysqli_close($conn);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Registrations – <?= htmlspecialchars($evInfo['title']) ?> – DUEMS</title>
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
    .main-content { margin-left:<?= $isOrganizer ? '220px' : '0' ?>; padding:25px; }
    .topbar { background:white; padding:15px 20px; border-radius:8px; margin-bottom:20px;
              box-shadow:0 1px 4px rgba(0,0,0,0.08); display:flex; justify-content:space-between; align-items:center; }
    .topbar h4 { margin:0; color:#333; }
    .card { background:white; border-radius:8px; padding:20px;
            box-shadow:0 1px 4px rgba(0,0,0,0.08); margin-bottom:20px; border:none; }
    .badge-confirmed { background-color:#28a745; color:white; padding:3px 9px; border-radius:12px; font-size:12px; font-weight:600; }
    .badge-cancelled { background-color:#dc3545; color:white; padding:3px 9px; border-radius:12px; font-size:12px; font-weight:600; }
    .ev-banner { background:#2c3e50; color:white; border-radius:8px; padding:16px 20px; margin-bottom:20px; }
    .ev-banner h5 { color:#f0c040; margin:0 0 8px; font-size:16px; }
    .ev-banner .meta { display:flex; flex-wrap:wrap; gap:16px; font-size:13px; color:#aab; }
    .ev-banner .meta strong { color:white; }
    .btn-export { background:white; border:1px solid #d1d5db; color:#374151; padding:5px 14px;
                  border-radius:6px; font-size:13px; font-weight:600; cursor:pointer;
                  text-decoration:none; display:inline-block; }
    .btn-export:hover { background:#f0f2f5; color:#374151; text-decoration:none; }
    .btn-back { background:rgba(255,255,255,0.15); border:1px solid rgba(255,255,255,0.3);
                color:white; padding:5px 14px; border-radius:6px; font-size:13px;
                text-decoration:none; white-space:nowrap; }
    .btn-back:hover { background:rgba(255,255,255,0.25); color:white; text-decoration:none; }
  </style>
</head>
<body>

<?php if ($isOrganizer): ?>
<div class="sidebar">
  <h4>DUEMS<br><small style="font-size:11px;color:#aaa;">Jazan University</small></h4>
  <div class="user-info">
    Hello, <strong style="color:white;"><?= htmlspecialchars($userName) ?></strong>
    <span style="display:block;font-size:11px;color:#aaa;margin-top:2px;"><?= htmlspecialchars($userSubtitle) ?></span>
  </div>
  <a href="organizer_dashboard.php">Dashboard</a>
  <a href="events.php">My Events</a>
  <a href="add_event.php">Add Event</a>
  <a href="registrations.php" class="active">Registered Students</a>
  <a href="support.php">Support</a>
  <a href="profile.php">My Profile</a>
  <a href="logout.php" class="logout-link">Logout</a>
</div>
<?php endif; ?>

<div class="main-content">

  <div class="topbar">
    <h4>Registered Students</h4>
    <span style="font-size:13px; color:#888;">
      <?php if ($isAdmin): ?>
        Administrator: <?= htmlspecialchars($userName) ?>
      <?php else: ?>
        Welcome, <?= htmlspecialchars($userName) ?> | <?= htmlspecialchars($userSubtitle) ?>
      <?php endif; ?>
    </span>
  </div>

  <!-- Breadcrumb -->
  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb" style="font-size:13px;">
      <li class="breadcrumb-item">
        <a href="<?= $backURL ?>" style="color:#2c3e50;"><?= $backLabel ?></a>
      </li>
      <li class="breadcrumb-item active"><?= htmlspecialchars($evInfo['title']) ?></li>
    </ol>
  </nav>

  <!-- Event banner -->
  <div class="ev-banner">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
      <div>
        <h5><?= htmlspecialchars($evInfo['title']) ?></h5>
        <div class="meta">
          <span>Date: <strong><?= date('M d, Y  h:i A', strtotime($evInfo['date'])) ?></strong></span>
          <span>Location: <strong><?= htmlspecialchars($evInfo['location']) ?></strong></span>
          <span>Dept: <strong><?= htmlspecialchars($evInfo['department']) ?></strong></span>
          <span>Organizer: <strong><?= htmlspecialchars($evInfo['org_name'] ?? '—') ?></strong></span>
          <span>Gender: <strong><?= htmlspecialchars($evInfo['gender']) ?></strong></span>
          <span>Fee: <strong><?= $evInfo['is_paid']==='Yes' ? 'SAR '.number_format($evInfo['price'],2) : 'Free' ?></strong></span>
        </div>
      </div>
      <a href="<?= $backURL ?>" class="btn-back">← Back</a>
    </div>
  </div>

  <!-- Stat cards -->
  <div class="row mb-4">
    <div class="col-md-4">
      <div class="card text-center">
        <h2 style="color:#27ae60;"><?= $cntConfirmed ?></h2>
        <p style="color:#888; margin:0; font-size:13px;">Confirmed</p>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card text-center">
        <h2 style="color:#dc3545;"><?= $cntCancelled ?></h2>
        <p style="color:#888; margin:0; font-size:13px;">Cancelled</p>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card text-center">
        <h2 style="color:#2c3e50;"><?= $seatsLeft ?> / <?= $evInfo['capacity'] ?></h2>
        <p style="color:#888; margin:0; font-size:13px;">Seats Remaining</p>
      </div>
    </div>
  </div>

  <!-- Filters -->
  <div class="card mb-3">
    <form method="GET" class="d-flex gap-2 flex-wrap align-items-end">
      <input type="hidden" name="event" value="<?= $event_id ?>">
      <div style="flex:1; min-width:200px;">
        <label style="display:block; margin-bottom:4px; font-size:13px; font-weight:600;">Search Student</label>
        <input type="text" name="search" class="form-control form-control-sm"
               placeholder="Name, email, or ID…" value="<?= htmlspecialchars($search) ?>">
      </div>
      <div style="min-width:140px;">
        <label style="display:block; margin-bottom:4px; font-size:13px; font-weight:600;">Status</label>
        <select name="status" class="form-select form-select-sm">
          <option value="">All</option>
          <option value="confirmed" <?= $filterStatus==='confirmed'?'selected':'' ?>>Confirmed</option>
          <option value="cancelled" <?= $filterStatus==='cancelled'?'selected':'' ?>>Cancelled</option>
        </select>
      </div>
      <div class="d-flex gap-2 align-items-end">
        <button type="submit" class="btn btn-sm btn-dark">Filter</button>
        <a href="event_registrations.php?event=<?= $event_id ?>" class="btn btn-sm btn-outline-secondary">Reset</a>
      </div>
    </form>
  </div>

  <!-- Table -->
  <div class="card">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h5 style="margin:0;">
        Registered Students
        <span style="font-size:13px; font-weight:400; color:#888;">(<?= mysqli_num_rows($registrations) ?> records)</span>
      </h5>
      <a href="event_registrations.php?event=<?= $event_id ?>&export=1&status=<?= urlencode($filterStatus) ?>&search=<?= urlencode($search) ?>"
         class="btn-export">Export CSV</a>
    </div>

    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th>#</th>
            <th>Student ID</th>
            <th>Name</th>
            <th>Email</th>
            <th>Department</th>
            <th>Gender</th>
            <th>Registered On</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
        <?php if (mysqli_num_rows($registrations) === 0): ?>
          <tr>
            <td colspan="8" style="text-align:center; padding:40px; color:#888;">
              No students registered for this event<?= ($search || $filterStatus) ? ' with the selected filters' : '' ?>.
            </td>
          </tr>
        <?php else:
          $i = 1;
          while ($r = mysqli_fetch_assoc($registrations)): ?>
          <tr>
            <td style="color:#888;"><?= $i++ ?></td>
            <td><strong><?= htmlspecialchars($r['studentID']) ?></strong></td>
            <td><?= htmlspecialchars($r['studentName']) ?></td>
            <td style="font-size:12px; color:#888;"><?= htmlspecialchars($r['email']) ?></td>
            <td style="font-size:13px;"><?= htmlspecialchars($r['studentDept']) ?></td>
            <td style="font-size:13px;"><?= htmlspecialchars($r['gender']) ?></td>
            <td style="font-size:12px; color:#888; white-space:nowrap;"><?= date('M d, Y H:i', strtotime($r['registrationDate'])) ?></td>
            <td><span class="badge-<?= $r['regStatus'] ?>"><?= ucfirst($r['regStatus']) ?></span></td>
          </tr>
          <?php endwhile;
        endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>
</body>
</html>
<?php mysqli_close($conn); ?>
