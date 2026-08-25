<?php
session_start();
require_once 'db.php';

$admin_id   = $_SESSION['admin_id'];
$admin_name = $_SESSION['admin_name'];

$all_depts = [
    'All','Computer Science','Information Technology','Software Engineering',
    'Electrical Engineering','Mechanical Engineering','Civil Engineering',
    'Business Administration','Medicine','Pharmacy','Other'
];

// ── Handle POST Actions ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action   = $_POST['action'];
    $event_id = isset($_POST['event_id']) ? (int)$_POST['event_id'] : 0;

    if ($action == 'add_event' || $action == 'edit_event') {
        $title   = trim($_POST['title']);
        $date    = $_POST['date'];
        $loc     = trim($_POST['location']);
        $cap     = (int)$_POST['capacity'];
        $dept    = $_POST['department'];
        $gender  = $_POST['gender'];
        $is_paid = isset($_POST['is_paid']) ? 'Yes' : 'No';
        $price   = ($is_paid === 'Yes') ? (float)$_POST['price'] : 0.00;
        $desc    = trim($_POST['description']);

        if ($action == 'add_event') {
            // Find or create organizer record for admin
            // organizerID is VARCHAR(9) in the schema (e.g. '200000001'), so we must
            // generate a matching formatted ID — we cannot rely on AUTO_INCREMENT here.
            $admin_data = mysqli_fetch_assoc(mysqli_query($conn, "SELECT email FROM admins WHERE adminID = '$admin_id'"));
            $aEmail = mysqli_real_escape_string($conn, $admin_data['email']);

            $org_check = mysqli_query($conn, "SELECT organizerID FROM organizers WHERE email = '$aEmail'");
            if ($org_row = mysqli_fetch_assoc($org_check)) {
                $org_id = $org_row['organizerID'];
            } else {
                // Generate a unique VARCHAR(9) organizer ID by finding the current max and incrementing
                $max_res = mysqli_fetch_assoc(mysqli_query($conn, "SELECT MAX(CAST(organizerID AS UNSIGNED)) AS max_id FROM organizers"));
                $org_id  = str_pad((int)($max_res['max_id'] ?? 200000000) + 1, 9, '0', STR_PAD_LEFT);
                $safe_name = mysqli_real_escape_string($conn, $admin_name);
                mysqli_query($conn, "INSERT INTO organizers (organizerID, name, email, password, role) VALUES ('$org_id', '$safe_name', '$aEmail', 'ADMIN_PROTECTED', 'Administrator')");
            }

            // organizerID is VARCHAR(9) so use 's' not 'i' in the type string
            $stmt = mysqli_prepare($conn, "INSERT INTO events (organizerID, title, description, date, location, capacity, department, gender, is_paid, price, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved')");
            mysqli_stmt_bind_param($stmt, 'sssssisssd', $org_id, $title, $desc, $date, $loc, $cap, $dept, $gender, $is_paid, $price);
            mysqli_stmt_execute($stmt);
            $new_eid = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);

            // adminID is VARCHAR(9) so use 's' not 'i' for that parameter
            $stmt2 = mysqli_prepare($conn, "INSERT INTO approvals (eventID, adminID, decision, remarks) VALUES (?, ?, 'approved', 'Created by admin')");
            mysqli_stmt_bind_param($stmt2, 'is', $new_eid, $admin_id);
            mysqli_stmt_execute($stmt2);
            mysqli_stmt_close($stmt2);

        } else {
            $stmt = mysqli_prepare($conn, "UPDATE events SET title=?, description=?, date=?, location=?, capacity=?, department=?, gender=?, is_paid=?, price=? WHERE eventID=?");
            mysqli_stmt_bind_param($stmt, 'ssssisssdi', $title, $desc, $date, $loc, $cap, $dept, $gender, $is_paid, $price, $event_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
        header('Location: admin_events.php?msg=success');
        exit;
    }

    if ($action == 'approve') {
        $stmt = mysqli_prepare($conn, "UPDATE events SET status='approved' WHERE eventID=?");
        mysqli_stmt_bind_param($stmt, 'i', $event_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        // Insert into approvals table
        $remarks = trim($_POST['remarks'] ?? '');
        // adminID is VARCHAR(9): use 'is' not 'ii'
        $stmt2 = mysqli_prepare($conn, "INSERT INTO approvals (eventID, adminID, decision, remarks) VALUES (?, ?, 'approved', ?)");
        mysqli_stmt_bind_param($stmt2, 'iss', $event_id, $admin_id, $remarks);
        mysqli_stmt_execute($stmt2);
        mysqli_stmt_close($stmt2);

    } elseif ($action == 'reject') {
        $stmt = mysqli_prepare($conn, "UPDATE events SET status='rejected' WHERE eventID=?");
        mysqli_stmt_bind_param($stmt, 'i', $event_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $remarks = trim($_POST['remarks'] ?? '');
        // adminID is VARCHAR(9): use 'is' not 'ii'
        $stmt2 = mysqli_prepare($conn, "INSERT INTO approvals (eventID, adminID, decision, remarks) VALUES (?, ?, 'rejected', ?)");
        mysqli_stmt_bind_param($stmt2, 'iss', $event_id, $admin_id, $remarks);
        mysqli_stmt_execute($stmt2);
        mysqli_stmt_close($stmt2);

    } elseif ($action == 'delete') {
        mysqli_query($conn, "DELETE FROM registrations WHERE eventID = $event_id");
        mysqli_query($conn, "DELETE FROM approvals WHERE eventID = $event_id");
        mysqli_query($conn, "DELETE FROM events WHERE eventID = $event_id");

    } elseif ($action == 'reg_status') {
        $rid    = (int)$_POST['reg_id'];
        $new_st = in_array($_POST['new_status'], ['confirmed','cancelled']) ? $_POST['new_status'] : 'confirmed';
        $stmt   = mysqli_prepare($conn, "UPDATE registrations SET status=? WHERE registrationID=?");
        mysqli_stmt_bind_param($stmt, 'si', $new_st, $rid);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        header("Location: admin_events.php?view_registrations=$event_id");
        exit;
    }

    header('Location: admin_events.php');
    exit;
}

// ── View Logic ────────────────────────────────────────────────────────────
$is_viewing_registrants = isset($_GET['view_registrations']);
$view_eid = $is_viewing_registrants ? (int)$_GET['view_registrations'] : 0;

$search  = isset($_GET['search'])  ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
$fStatus = isset($_GET['status'])  ? mysqli_real_escape_string($conn, $_GET['status']) : '';
$fDept   = isset($_GET['dept'])    ? mysqli_real_escape_string($conn, $_GET['dept'])   : '';

$where = "WHERE 1=1";
if ($search)  $where .= " AND e.title LIKE '%$search%'";
if ($fStatus) $where .= " AND e.status = '$fStatus'";
if ($fDept)   $where .= " AND e.department = '$fDept'";

$events_res = mysqli_query($conn,
    "SELECT e.*, o.name AS org_name,
            (SELECT COUNT(*) FROM registrations WHERE eventID = e.eventID AND status = 'confirmed') AS regs
     FROM events e
     LEFT JOIN organizers o ON e.organizerID = o.organizerID
     $where
     ORDER BY e.date DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Events - DUEMS Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body { background-color: #f0f2f5; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }

        .topbar { background: white; padding: 15px 25px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 1px 4px rgba(0,0,0,0.08); margin-bottom: 25px; }
        .topbar h4 { margin: 0; color: #333; font-size: 16px; }

        .container-inner { padding: 0 25px 25px; }

        .card { background: white; border-radius: 8px; padding: 20px; box-shadow: 0 1px 4px rgba(0,0,0,0.08); margin-bottom: 20px; }

        .badge-pending  { background-color: #ffc107; color: #333;  padding: 3px 9px; border-radius: 12px; font-size: 12px; }
        .badge-approved { background-color: #28a745; color: white; padding: 3px 9px; border-radius: 12px; font-size: 12px; }
        .badge-rejected { background-color: #dc3545; color: white; padding: 3px 9px; border-radius: 12px; font-size: 12px; }
        .badge-cancelled{ background-color: #6c757d; color: white; padding: 3px 9px; border-radius: 12px; font-size: 12px; }
        .badge-confirmed{ background-color: #28a745; color: white; padding: 3px 9px; border-radius: 12px; font-size: 12px; }

        /* Custom confirm modal */
        .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center; }
        .modal-overlay.active { display:flex; }
        .modal-box { background:#fff; border-radius:10px; padding:28px 28px 22px; width:340px; box-shadow:0 8px 30px rgba(0,0,0,0.25); text-align:center; }
        .modal-box p { font-size:15px; color:#2c3e50; margin:0 0 20px; font-weight:500; }
        .modal-actions { display:flex; gap:10px; justify-content:center; }
        .modal-actions button { padding:9px 26px; border:none; border-radius:6px; font-size:14px; font-weight:600; cursor:pointer; }
        .modal-btn-yes { background:#2c3e50; color:#f0c040; }
        .modal-btn-yes:hover { background:#3d5166; }
        .modal-btn-no  { background:#e5e7eb; color:#374151; }
        .modal-btn-no:hover { background:#d1d5db; }
    </style>
</head>
<body>

<!-- Confirm Modal -->
<div class="modal-overlay" id="confirmModal">
    <div class="modal-box">
        <p id="confirmMsg">Are you sure?</p>
        <div class="modal-actions">
            <button class="modal-btn-yes" id="confirmYes">Confirm</button>
            <button class="modal-btn-no"  id="confirmNo">Cancel</button>
        </div>
    </div>
</div>

<!-- Event Add/Edit Modal -->
<div class="modal-overlay" id="eventModal">
    <div class="modal-box" style="width:580px; text-align:left; padding:28px;">
        <h5 id="modalTitle" style="margin:0 0 20px; color:#2c3e50;">Add Event</h5>
        <form method="POST" id="eventForm">
            <input type="hidden" name="action"   id="formAction" value="add_event">
            <input type="hidden" name="event_id" id="edit_eid">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label">Title</label>
                    <input type="text" name="title" id="f_title" class="form-control" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Date &amp; Time</label>
                    <input type="datetime-local" name="date" id="f_date" class="form-control" required>
                </div>
                <div class="col-md-8">
                    <label class="form-label">Location</label>
                    <input type="text" name="location" id="f_loc" class="form-control" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Capacity</label>
                    <input type="number" name="capacity" id="f_cap" class="form-control" required min="1">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Department</label>
                    <select name="department" id="f_dept" class="form-select">
                        <?php foreach ($all_depts as $d) echo "<option value='$d'>$d</option>"; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Gender</label>
                    <select name="gender" id="f_gender" class="form-select">
                        <option value="Both">Both</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" name="is_paid" id="f_is_paid" onchange="togglePrice(this.checked)">
                        <label class="form-check-label" for="f_is_paid">Paid Event</label>
                    </div>
                </div>
                <div class="col-md-6" id="pWrap" style="display:none;">
                    <label class="form-label">Price (SAR)</label>
                    <input type="number" name="price" id="f_price" class="form-control" step="0.01" min="0" value="0">
                </div>
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea name="description" id="f_desc" class="form-control" rows="3"></textarea>
                </div>
            </div>
            <div class="d-flex gap-2 justify-content-end mt-4">
                <button type="button" class="btn btn-secondary" onclick="closeEventModal()">Cancel</button>
                <button type="submit" class="btn btn-dark" id="submitBtn">Create Event</button>
            </div>
        </form>
    </div>
</div>

<!-- Approval/Reject Remarks Modal -->
<div class="modal-overlay" id="decisionModal">
    <div class="modal-box" style="width:400px; text-align:left; padding:28px;">
        <h5 id="decisionTitle" style="margin:0 0 15px; color:#2c3e50;">Approve Event</h5>
        <form method="POST" id="decisionForm">
            <input type="hidden" name="action"   id="d_action">
            <input type="hidden" name="event_id" id="d_eid">
            <div class="mb-3">
                <label class="form-label">Remarks (optional)</label>
                <textarea name="remarks" class="form-control" rows="3" placeholder="Add any notes..."></textarea>
            </div>
            <div class="d-flex gap-2 justify-content-end">
                <button type="button" class="btn btn-secondary" onclick="closeDecisionModal()">Cancel</button>
                <button type="submit" id="decisionSubmitBtn" class="btn btn-success">Approve</button>
            </div>
        </form>
    </div>
</div>

<div class="topbar">
    <h4>Events Management</h4>
    <?php if (!$is_viewing_registrants): ?>
        <button class="btn btn-sm btn-dark" onclick="openAddModal()">+ Create Event</button>
    <?php else: ?>
        <a href="admin_events.php" class="btn btn-sm btn-secondary">Back to Events</a>
    <?php endif; ?>
</div>

<div class="container-inner">

    <?php if (isset($_GET['msg']) && $_GET['msg'] == 'success'): ?>
        <div class="alert alert-success">Action completed successfully.</div>
    <?php endif; ?>

    <?php if (!$is_viewing_registrants): ?>

        <!-- Filter bar -->
        <div class="card">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control" placeholder="Search by event title..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-2">
                    <select name="dept" class="form-select">
                        <option value="">All Departments</option>
                        <?php foreach ($all_depts as $d) echo "<option value='$d'" . ($fDept == $d ? ' selected' : '') . ">$d</option>"; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select">
                        <option value="">Any Status</option>
                        <option value="pending"  <?= $fStatus == 'pending'  ? 'selected' : '' ?>>Pending</option>
                        <option value="approved" <?= $fStatus == 'approved' ? 'selected' : '' ?>>Approved</option>
                        <option value="rejected" <?= $fStatus == 'rejected' ? 'selected' : '' ?>>Rejected</option>
                        <option value="cancelled"<?= $fStatus == 'cancelled'? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill">Filter</button>
                    <a href="admin_events.php" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>

        <!-- Events table -->
        <div class="card">
            <?php if (mysqli_num_rows($events_res) == 0): ?>
                <p style="color:#888;">No events found.</p>
            <?php else: ?>
                <table class="table table-hover mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Event</th>
                            <th>Organizer</th>
                            <th>Dept</th>
                            <th>Gender</th>
                            <th>Date</th>
                            <th>Capacity</th>
                            <th>Price</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($ev = mysqli_fetch_assoc($events_res)): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($ev['title']) ?></strong>
                                <?php if ($ev['description']): ?>
                                    <br><small style="color:#888;"><?= htmlspecialchars(substr($ev['description'], 0, 50)) ?>...</small>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($ev['org_name'] ?? $admin_name) ?></td>
                            <td><small><?= htmlspecialchars($ev['department']) ?></small></td>
                            <td><?= htmlspecialchars($ev['gender']) ?></td>
                            <td>
                                <?= date('M d, Y', strtotime($ev['date'])) ?>
                                <br><small style="color:#888;"><?= date('h:i A', strtotime($ev['date'])) ?></small>
                            </td>
                            <td><?= $ev['regs'] ?>/<?= $ev['capacity'] ?></td>
                            <td>
                                <?php if ($ev['is_paid'] === 'Yes'): ?>
                                    <span style= font-weight:600;"><?= number_format($ev['price'], 2) ?> SAR</span>
                                <?php else: ?>
                                    <span >Free</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge-<?= $ev['status'] ?>"><?= ucfirst($ev['status']) ?></span></td>
                            <td>
                                <div class="d-flex gap-1 flex-wrap">
                                    <?php if ($ev['status'] == 'pending'): ?>
                                        <button class="btn btn-sm btn-success" onclick="openDecision('approve', <?= $ev['eventID'] ?>)">Approve</button>
                                        <button class="btn btn-sm btn-danger"  onclick="openDecision('reject',  <?= $ev['eventID'] ?>)">Reject</button>
                                    <?php endif; ?>
                                    <button class="btn btn-sm btn-secondary" onclick='openEditModal(<?= json_encode($ev) ?>)'>Edit</button>
                                    <a href="?view_registrations=<?= $ev['eventID'] ?>" class="btn btn-sm btn-outline-dark">Registrants</a>
                                    <form method="POST" style="display:inline;" onsubmit="return showConfirm('Delete this event and all its registrations?', this)">
                                        <input type="hidden" name="action"   value="delete">
                                        <input type="hidden" name="event_id" value="<?= $ev['eventID'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

    <?php else: ?>

        <!-- Registrants view -->
        <?php
        $evInfo = mysqli_fetch_assoc(mysqli_query($conn, "SELECT title FROM events WHERE eventID=$view_eid"));
        $rs     = mysqli_query($conn, "SELECT r.*, s.name, s.email, s.gender, s.department FROM registrations r JOIN students s ON r.studentID = s.studentID WHERE r.eventID = $view_eid ORDER BY r.registrationDate DESC");
        ?>
        <h5 style="margin-bottom:20px; color:#2c3e50;">Registrations for: <?= htmlspecialchars($evInfo['title']) ?></h5>
        <div class="card">
            <?php if (mysqli_num_rows($rs) == 0): ?>
                <p style="color:#888;">No registrations for this event.</p>
            <?php else: ?>
                <table class="table table-hover mb-0">
                    <thead class="table-dark">
                        <tr><th>#</th><th>Name</th><th>Department</th><th>Gender</th><th>Email</th><th>Status</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                    <?php $i = 1; while ($r = mysqli_fetch_assoc($rs)): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><?= htmlspecialchars($r['name']) ?></td>
                            <td><?= htmlspecialchars($r['department'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($r['gender']) ?></td>
                            <td><?= htmlspecialchars($r['email']) ?></td>
                            <td><span class="badge-<?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span></td>
                            <td>
                                <form method="POST" class="d-flex gap-1">
                                    <input type="hidden" name="action"   value="reg_status">
                                    <input type="hidden" name="event_id" value="<?= $view_eid ?>">
                                    <input type="hidden" name="reg_id"   value="<?= $r['registrationID'] ?>">
                                    <?php if ($r['status'] == 'cancelled'): ?>
                                        <button name="new_status" value="confirmed" class="btn btn-sm btn-success">Restore</button>
                                    <?php else: ?>
                                        <button name="new_status" value="cancelled" class="btn btn-sm btn-outline-danger">Cancel</button>
                                    <?php endif; ?>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

    <?php endif; ?>
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

    function togglePrice(show) { document.getElementById('pWrap').style.display = show ? 'block' : 'none'; }

    function openAddModal() {
        document.getElementById('modalTitle').textContent = 'Create New Event';
        document.getElementById('formAction').value = 'add_event';
        document.getElementById('submitBtn').textContent = 'Create Event';
        document.getElementById('eventForm').reset();
        togglePrice(false);
        document.getElementById('eventModal').classList.add('active');
    }

    function openEditModal(ev) {
        document.getElementById('modalTitle').textContent = 'Edit Event';
        document.getElementById('formAction').value = 'edit_event';
        document.getElementById('submitBtn').textContent = 'Save Changes';
        document.getElementById('edit_eid').value = ev.eventID;
        document.getElementById('f_title').value = ev.title;
        document.getElementById('f_date').value  = ev.date.replace(' ', 'T').substring(0, 16);
        document.getElementById('f_loc').value   = ev.location;
        document.getElementById('f_cap').value   = ev.capacity;
        document.getElementById('f_dept').value  = ev.department;
        document.getElementById('f_gender').value= ev.gender;
        var paid = ev.is_paid === 'Yes';
        document.getElementById('f_is_paid').checked = paid;
        document.getElementById('f_price').value = ev.price;
        document.getElementById('f_desc').value  = ev.description || '';
        togglePrice(paid);
        document.getElementById('eventModal').classList.add('active');
    }

    function closeEventModal() { document.getElementById('eventModal').classList.remove('active'); }

    function openDecision(action, eid) {
        document.getElementById('d_action').value = action;
        document.getElementById('d_eid').value    = eid;
        var isApprove = action === 'approve';
        document.getElementById('decisionTitle').textContent     = isApprove ? 'Approve Event' : 'Reject Event';
        document.getElementById('decisionSubmitBtn').textContent = isApprove ? 'Approve'        : 'Reject';
        document.getElementById('decisionSubmitBtn').className   = 'btn ' + (isApprove ? 'btn-success' : 'btn-danger');
        document.getElementById('decisionModal').classList.add('active');
    }
    function closeDecisionModal() { document.getElementById('decisionModal').classList.remove('active'); }
</script>
</body>
</html>
<?php mysqli_close($conn); ?>
