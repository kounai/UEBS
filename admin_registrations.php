<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }
require_once 'db.php';

$admin_name = $_SESSION['admin_name'] ?? 'Admin';

// Filters
$filterEvent  = (int)($_GET['event']  ?? 0);
$search       = trim($_GET['search']  ?? '');
$filterStatus = $_GET['status']       ?? '';

// All events for filter dropdown
$eventsQuery = mysqli_query($conn, "SELECT eventID, title FROM events ORDER BY date DESC");
$allEvents   = [];
while ($ev = mysqli_fetch_assoc($eventsQuery)) $allEvents[] = $ev;

// Build WHERE
$where = "WHERE 1=1";
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
    $where
    ORDER BY r.registrationDate DESC");

// Stats
$totalConfirmed = (int)mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS c FROM registrations WHERE status='confirmed'"))['c'];
$totalCancelled = (int)mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS c FROM registrations WHERE status='cancelled'"))['c'];
$totalEvents    = (int)mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS c FROM events"))['c'];

// CSV export — before any HTML output
if (isset($_GET['export'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="registrations_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Student ID','Name','Email','Department','Event','Event Date','Registered On','Fee','Status']);
    while ($r = mysqli_fetch_assoc($registrations)) {
        fputcsv($out, [
            $r['studentID'],
            $r['studentName'],
            $r['email'],
            $r['studentDept'],
            $r['eventTitle'],
            date('Y-m-d', strtotime($r['eventDate'])),
            date('Y-m-d H:i', strtotime($r['registrationDate'])),
            $r['is_paid'] === 'Yes' ? 'SAR ' . $r['price'] : 'Free',
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
    <meta charset="UTF-8">
    <title>Registrations - DUEMS Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body { background-color: #f0f2f5; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }

        /* Topbar — identical to admin_overview.php */
        .topbar { background: white; padding: 15px 20px; border-radius: 8px; margin-bottom: 20px;
                  box-shadow: 0 1px 4px rgba(0,0,0,0.08); display: flex; justify-content: space-between; align-items: center; }
        .topbar h4 { margin: 0; color: #333; }

        .wrap { padding: 25px; }

        /* Cards — identical to admin_overview.php */
        .card { background: white; border-radius: 8px; padding: 20px;
                box-shadow: 0 1px 4px rgba(0,0,0,0.08); margin-bottom: 20px; border: none; }

        /* Badges — same as every other admin page */
        .badge-confirmed { background-color: #28a745; color: white; padding: 3px 9px; border-radius: 12px; font-size: 12px; font-weight: 600; }
        .badge-cancelled { background-color: #dc3545; color: white; padding: 3px 9px; border-radius: 12px; font-size: 12px; font-weight: 600; }

        /* Export button — same outline style used in admin_events.php */
        .btn-export { background: white; border: 1px solid #d1d5db; color: #374151; padding: 5px 14px;
                      border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer;
                      text-decoration: none; display: inline-block; }
        .btn-export:hover { background: #f0f2f5; color: #374151; text-decoration: none; }
    </style>
</head>
<body>
<div class="wrap">

    <!-- Topbar -->
    <div class="topbar">
        <h4>Registrations</h4>
        <span style="font-size:13px; color:#888;">Administrator: <?= htmlspecialchars($admin_name) ?></span>
    </div>

    <!-- Stat cards — same layout as admin_overview.php -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card text-center">
                <h2 style="color:#27ae60;"><?= $totalConfirmed ?></h2>
                <p style="color:#888; margin:0; font-size:13px;">Confirmed Registrations</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center">
                <h2 style="color:#dc3545;"><?= $totalCancelled ?></h2>
                <p style="color:#888; margin:0; font-size:13px;">Cancelled</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center">
                <h2 style="color:#2c3e50;"><?= $totalEvents ?></h2>
                <p style="color:#888; margin:0; font-size:13px;">Total Events</p>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-3">
        <form method="GET" class="d-flex gap-2 flex-wrap align-items-end">
            <div style="flex:1; min-width:180px;">
                <label style="display:block; margin-bottom:4px; font-size:13px; font-weight:600;">Search Student</label>
                <input type="text" name="search" class="form-control form-control-sm"
                       placeholder="Name, email, or ID…" value="<?= htmlspecialchars($search) ?>">
            </div>
            <div style="min-width:220px;">
                <label style="display:block; margin-bottom:4px; font-size:13px; font-weight:600;">Filter by Event</label>
                <select name="event" class="form-select form-select-sm">
                    <option value="">All Events</option>
                    <?php foreach ($allEvents as $ev): ?>
                        <option value="<?= $ev['eventID'] ?>" <?= $filterEvent === $ev['eventID'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($ev['title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="min-width:140px;">
                <label style="display:block; margin-bottom:4px; font-size:13px; font-weight:600;">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="confirmed" <?= $filterStatus === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                    <option value="cancelled" <?= $filterStatus === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
            </div>
            <div class="d-flex gap-2 align-items-end">
                <button type="submit" class="btn btn-sm btn-dark">Filter</button>
                <a href="admin_registrations.php" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>

    <!-- Table -->
    <div class="card">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 style="margin:0;">
                Registered Students
                <span style="font-size:13px; font-weight:400; color:#888;">
                    (<?= mysqli_num_rows($registrations) ?> records)
                </span>
            </h5>
            <a href="admin_registrations.php?export=1&event=<?= $filterEvent ?>&status=<?= urlencode($filterStatus) ?>&search=<?= urlencode($search) ?>"
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
                        <th>Event</th>
                        <th>Event Date</th>
                        <th>Registered On</th>
                        <th>Fee</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (mysqli_num_rows($registrations) === 0): ?>
                    <tr>
                        <td colspan="10" style="text-align:center; padding:40px; color:#888;">
                            No registrations found.
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
                        <td>
                            <a href="event_registrations.php?event=<?= $r['eventID'] ?>" style="font-weight:500; color:#2c3e50; text-decoration:none;"><?= htmlspecialchars($r['eventTitle']) ?></a>
                            <div style="font-size:12px; color:#888;"><?= $r['regCount'] ?>/<?= $r['capacity'] ?> seats</div>
                        </td>
                        <td style="white-space:nowrap; font-size:13px;"><?= date('M d, Y', strtotime($r['eventDate'])) ?></td>
                        <td style="white-space:nowrap; font-size:12px; color:#888;"><?= date('M d, Y H:i', strtotime($r['registrationDate'])) ?></td>
                        <td style="font-size:13px;"><?= $r['is_paid'] === 'Yes' ? 'SAR ' . number_format($r['price'], 2) : 'Free' ?></td>
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
