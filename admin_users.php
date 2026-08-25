<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }
require_once 'db.php';

$all_depts = [
    'Computer Science','Information Technology','Software Engineering',
    'Electrical Engineering','Mechanical Engineering','Civil Engineering',
    'Business Administration','Medicine','Pharmacy','Other'
];

$msg      = '';
$msg_type = '';
$reopen_modal = '';
$reopen_data  = [];

// ── POST Handler ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── ADD STUDENT ──────────────────────────────────────────────────────
    if ($action === 'add_student') {
        $sid   = trim($_POST['student_id']   ?? '');
        $name  = trim($_POST['s_name']       ?? '');
        $email = trim($_POST['s_email']      ?? '');
        $dept  = trim($_POST['s_department'] ?? '');
        $gen   = trim($_POST['s_gender']     ?? '');
        $pw    = $_POST['s_password']        ?? '';

        // Basic validation
        if ($sid === '' || $name === '' || $email === '' || $dept === '' || $gen === '' || $pw === '') {
            $msg = 'All fields are required.';
            $msg_type = 'danger';
            $reopen_modal = 'add_student';
            $reopen_data  = compact('sid','name','email','dept','gen');
        } else {
            // Check if ID already exists BEFORE inserting
            $chk = mysqli_prepare($conn, "SELECT studentID FROM students WHERE studentID = ?");
            mysqli_stmt_bind_param($chk, 's', $sid);
            mysqli_stmt_execute($chk);
            mysqli_stmt_store_result($chk);
            $exists = mysqli_stmt_num_rows($chk) > 0;
            mysqli_stmt_close($chk);

            if ($exists) {
                $msg = "Student ID \"$sid\" already exists in the database. Use a different ID.";
                $msg_type = 'danger';
                $reopen_modal = 'add_student';
                $reopen_data  = compact('sid','name','email','dept','gen');
            } else {
                $hash = password_hash($pw, PASSWORD_BCRYPT);
                $ins  = mysqli_prepare($conn,
                    "INSERT INTO students (studentID, name, email, password, department, gender)
                     VALUES (?, ?, ?, ?, ?, ?)");
                mysqli_stmt_bind_param($ins, 'ssssss', $sid, $name, $email, $hash, $dept, $gen);
                try {
                    mysqli_stmt_execute($ins);
                    mysqli_stmt_close($ins);
                    header("Location: admin_users.php?tab=students&msg=" .
                           urlencode("Student \"$name\" added successfully.") . "&msg_type=success");
                    exit;
                } catch (mysqli_sql_exception $e) {
                    mysqli_stmt_close($ins);
                    if ($e->getCode() == 1062) {
                        // Check which field is duplicated for a clear message
                        if (stripos($e->getMessage(), 'email') !== false) {
                            $msg = "Email \"$email\" is already registered to another student.";
                        } else {
                            $msg = "Student ID \"$sid\" already exists. Please use a different ID.";
                        }
                    } else {
                        $msg = "Database error: " . $e->getMessage();
                    }
                    $msg_type     = 'danger';
                    $reopen_modal = 'add_student';
                    $reopen_data  = compact('sid','name','email','dept','gen');
                }
            }
        }
    }

    // ── EDIT STUDENT ─────────────────────────────────────────────────────
    elseif ($action === 'edit_student') {
        $sid   = trim($_POST['student_id']   ?? '');
        $name  = trim($_POST['s_name']       ?? '');
        $email = trim($_POST['s_email']      ?? '');
        $dept  = trim($_POST['s_department'] ?? '');
        $gen   = trim($_POST['s_gender']     ?? '');
        $pw    = $_POST['s_password']        ?? '';

        if (!empty($pw)) {
            $hash = password_hash($pw, PASSWORD_BCRYPT);
            $stmt = mysqli_prepare($conn,
                "UPDATE students SET name=?, email=?, password=?, department=?, gender=? WHERE studentID=?");
            mysqli_stmt_bind_param($stmt, 'ssssss', $name, $email, $hash, $dept, $gen, $sid);
        } else {
            $stmt = mysqli_prepare($conn,
                "UPDATE students SET name=?, email=?, department=?, gender=? WHERE studentID=?");
            mysqli_stmt_bind_param($stmt, 'sssss', $name, $email, $dept, $gen, $sid);
        }

        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            header("Location: admin_users.php?tab=students&msg=" .
                   urlencode("Student updated successfully.") . "&msg_type=success");
            exit;
        } else {
            $err = mysqli_stmt_error($stmt);
            mysqli_stmt_close($stmt);
            $msg = "Update failed: $err";
            $msg_type = 'danger';
            $reopen_modal = 'edit_student';
            $reopen_data  = ['studentID'=>$sid,'name'=>$name,'email'=>$email,'department'=>$dept,'gender'=>$gen];
        }
    }

    // ── ADD ORGANIZER ────────────────────────────────────────────────────
    elseif ($action === 'add_organizer') {
        $oid   = trim($_POST['o_id']       ?? '');
        $name  = trim($_POST['o_name']     ?? '');
        $email = trim($_POST['o_email']    ?? '');
        $role  = trim($_POST['o_role']     ?? '');
        $pw    = $_POST['o_password']      ?? '';

        if ($oid === '' || $name === '' || $email === '' || $role === '' || $pw === '') {
            $msg = 'All fields are required.';
            $msg_type = 'danger';
            $reopen_modal = 'add_org';
            $reopen_data  = compact('oid','name','email','role');
        } else {
            // Pre-check ID
            $chk = mysqli_prepare($conn, "SELECT organizerID FROM organizers WHERE organizerID = ?");
            mysqli_stmt_bind_param($chk, 's', $oid);
            mysqli_stmt_execute($chk);
            mysqli_stmt_store_result($chk);
            $exists = mysqli_stmt_num_rows($chk) > 0;
            mysqli_stmt_close($chk);

            if ($exists) {
                $msg = "Organizer ID \"$oid\" already exists. Use a different ID.";
                $msg_type = 'danger';
                $reopen_modal = 'add_org';
                $reopen_data  = compact('oid','name','email','role');
            } else {
                $hash = password_hash($pw, PASSWORD_BCRYPT);
                $ins  = mysqli_prepare($conn,
                    "INSERT INTO organizers (organizerID, name, email, password, role) VALUES (?, ?, ?, ?, ?)");
                mysqli_stmt_bind_param($ins, 'sssss', $oid, $name, $email, $hash, $role);
                try {
                    mysqli_stmt_execute($ins);
                    mysqli_stmt_close($ins);
                    header("Location: admin_users.php?tab=organizers&msg=" .
                           urlencode("Organizer \"$name\" added successfully.") . "&msg_type=success");
                    exit;
                } catch (mysqli_sql_exception $e) {
                    mysqli_stmt_close($ins);
                    if ($e->getCode() == 1062) {
                        if (stripos($e->getMessage(), 'email') !== false) {
                            $msg = "Email \"$email\" is already registered to another organizer.";
                        } else {
                            $msg = "Organizer ID \"$oid\" already exists. Please use a different ID.";
                        }
                    } else {
                        $msg = "Database error: " . $e->getMessage();
                    }
                    $msg_type     = 'danger';
                    $reopen_modal = 'add_org';
                    $reopen_data  = compact('oid','name','email','role');
                }
            }
        }
    }

    // ── EDIT ORGANIZER ───────────────────────────────────────────────────
    elseif ($action === 'edit_organizer') {
        $oid   = trim($_POST['o_id_hidden'] ?? '');
        $name  = trim($_POST['o_name']      ?? '');
        $email = trim($_POST['o_email']     ?? '');
        $role  = trim($_POST['o_role']      ?? '');
        $pw    = $_POST['o_password']       ?? '';

        if (!empty($pw)) {
            $hash = password_hash($pw, PASSWORD_BCRYPT);
            $stmt = mysqli_prepare($conn,
                "UPDATE organizers SET name=?, email=?, password=?, role=? WHERE organizerID=?");
            mysqli_stmt_bind_param($stmt, 'sssss', $name, $email, $hash, $role, $oid);
        } else {
            $stmt = mysqli_prepare($conn,
                "UPDATE organizers SET name=?, email=?, role=? WHERE organizerID=?");
            mysqli_stmt_bind_param($stmt, 'ssss', $name, $email, $role, $oid);
        }

        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            header("Location: admin_users.php?tab=organizers&msg=" .
                   urlencode("Organizer updated successfully.") . "&msg_type=success");
            exit;
        } else {
            $err = mysqli_stmt_error($stmt);
            mysqli_stmt_close($stmt);
            $msg = "Update failed: $err";
            $msg_type = 'danger';
        }
    }

    // ── DELETE STUDENT ───────────────────────────────────────────────────
    elseif ($action === 'delete_student') {
        $sid = trim($_POST['student_id'] ?? '');
        $s1  = mysqli_prepare($conn, "DELETE FROM registrations WHERE studentID=?");
        mysqli_stmt_bind_param($s1, 's', $sid);
        mysqli_stmt_execute($s1);
        mysqli_stmt_close($s1);
        $s2  = mysqli_prepare($conn, "DELETE FROM students WHERE studentID=?");
        mysqli_stmt_bind_param($s2, 's', $sid);
        mysqli_stmt_execute($s2);
        mysqli_stmt_close($s2);
        header("Location: admin_users.php?tab=students&msg=" .
               urlencode("Student deleted.") . "&msg_type=success");
        exit;
    }

    // ── DELETE ORGANIZER ─────────────────────────────────────────────────
    elseif ($action === 'delete_organizer') {
        $oid = trim($_POST['organizer_id'] ?? '');
        $s1  = mysqli_prepare($conn, "DELETE FROM events WHERE organizerID=?");
        mysqli_stmt_bind_param($s1, 's', $oid);
        mysqli_stmt_execute($s1);
        mysqli_stmt_close($s1);
        $s2  = mysqli_prepare($conn, "DELETE FROM organizers WHERE organizerID=?");
        mysqli_stmt_bind_param($s2, 's', $oid);
        mysqli_stmt_execute($s2);
        mysqli_stmt_close($s2);
        header("Location: admin_users.php?tab=organizers&msg=" .
               urlencode("Organizer deleted.") . "&msg_type=success");
        exit;
    }
}

// ── GET message from redirect ─────────────────────────────────────────────
if ($msg === '' && isset($_GET['msg'])) {
    $msg      = htmlspecialchars($_GET['msg']);
    $msg_type = $_GET['msg_type'] ?? 'success';
}

// ── Page state ────────────────────────────────────────────────────────────
$active_tab = $_GET['tab'] ?? 'students';
$search     = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
$fDept      = isset($_GET['dept'])   ? mysqli_real_escape_string($conn, $_GET['dept'])         : '';
$fGender    = isset($_GET['gender']) ? mysqli_real_escape_string($conn, $_GET['gender'])       : '';

// Which modal to open
$show_add_student = isset($_GET['add_student'])  || $reopen_modal === 'add_student';
$show_add_org     = isset($_GET['add_org'])      || $reopen_modal === 'add_org';
$edit_student_id  = $_GET['edit_student']        ?? ($reopen_modal === 'edit_student' ? ($reopen_data['studentID'] ?? null) : null);
$edit_org_id      = $_GET['edit_org']            ?? null;
$confirm_del_sid  = $_GET['confirm_del_s']       ?? null;
$confirm_del_oid  = $_GET['confirm_del_o']       ?? null;

// Fetch edit rows
$edit_student_row = null;
if ($reopen_modal === 'edit_student') {
    $edit_student_row = $reopen_data;
} elseif ($edit_student_id) {
    $r = mysqli_prepare($conn, "SELECT * FROM students WHERE studentID=?");
    mysqli_stmt_bind_param($r, 's', $edit_student_id);
    mysqli_stmt_execute($r);
    $edit_student_row = mysqli_fetch_assoc(mysqli_stmt_get_result($r));
    mysqli_stmt_close($r);
}

$edit_org_row = null;
if ($edit_org_id) {
    $r = mysqli_prepare($conn, "SELECT * FROM organizers WHERE organizerID=?");
    mysqli_stmt_bind_param($r, 's', $edit_org_id);
    mysqli_stmt_execute($r);
    $edit_org_row = mysqli_fetch_assoc(mysqli_stmt_get_result($r));
    mysqli_stmt_close($r);
}

// Table data
if ($active_tab === 'students') {
    $w = "WHERE (name LIKE '%$search%' OR email LIKE '%$search%' OR studentID LIKE '%$search%')";
    if ($fDept)   $w .= " AND department='$fDept'";
    if ($fGender) $w .= " AND gender='$fGender'";
    $data_res = mysqli_query($conn, "SELECT * FROM students $w ORDER BY name ASC");
} else {
    $w = "WHERE (name LIKE '%$search%' OR email LIKE '%$search%' OR organizerID LIKE '%$search%')";
    $data_res = mysqli_query($conn, "SELECT * FROM organizers $w ORDER BY name ASC");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Users - DUEMS Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body { background-color:#f0f2f5; font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif; }
        .topbar { background:white; padding:15px 25px; display:flex; justify-content:space-between; align-items:center; box-shadow:0 1px 4px rgba(0,0,0,0.08); margin-bottom:25px; }
        .topbar h4 { margin:0; color:#333; font-size:16px; }
        .container-inner { padding:0 25px 25px; }
        .card { background:white; border-radius:8px; padding:20px; box-shadow:0 1px 4px rgba(0,0,0,0.08); margin-bottom:20px; }
        .badge-dept { background-color:#e0f0ff; color:#0066cc; padding:3px 9px; border-radius:12px; font-size:12px; }
        .badge-role { background-color:#fff3cd; color:#856404; padding:3px 9px; border-radius:12px; font-size:12px; }
        .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center; }
        .modal-overlay.open { display:flex; }
        .modal-box { background:#fff; border-radius:10px; padding:28px; width:520px; max-height:90vh; overflow-y:auto; box-shadow:0 8px 30px rgba(0,0,0,0.25); }
        .modal-box h5 { margin:0 0 20px; color:#2c3e50; font-size:18px; font-weight:600; }
        .btn-cancel { background:#e5e7eb; color:#374151; border:none; padding:9px 20px; border-radius:6px; font-size:14px; font-weight:600; cursor:pointer; text-decoration:none; display:inline-block; }
        .btn-cancel:hover { background:#d1d5db; color:#374151; }
        .confirm-box { background:#fff; border-radius:10px; padding:28px; width:340px; box-shadow:0 8px 30px rgba(0,0,0,0.25); text-align:center; }
        .confirm-box p { font-size:15px; color:#2c3e50; margin:0 0 20px; font-weight:500; }
        .confirm-actions { display:flex; gap:10px; justify-content:center; align-items:center; }
        .btn-confirm-yes { background:#dc3545; color:#fff; border:none; padding:9px 26px; border-radius:6px; font-size:14px; font-weight:600; cursor:pointer; }
        .btn-confirm-yes:hover { background:#b02a37; }
        .field-readonly { background:#f3f4f6 !important; color:#6b7280 !important; }
    </style>
</head>
<body>

<?php /* ══════ ADD STUDENT MODAL ══════ */
if ($show_add_student): $d = $reopen_data; ?>
<div class="modal-overlay open">
    <div class="modal-box">
        <h5>Add Student</h5>
        <?php if ($msg && $reopen_modal === 'add_student'): ?>
            <div class="alert alert-danger py-2 mb-3"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>
        <form method="POST" action="admin_users.php">
            <input type="hidden" name="action" value="add_student">
            <div class="row g-3">
                <div class="col-6">
                    <label class="form-label fw-semibold">Student ID</label>
                    <input type="text" name="student_id" class="form-control" maxlength="9"
                           placeholder="e.g. 202301234"
                           value="<?= htmlspecialchars($d['sid'] ?? '') ?>" required>
                </div>
                <div class="col-6">
                    <label class="form-label fw-semibold">Full Name</label>
                    <input type="text" name="s_name" class="form-control"
                           value="<?= htmlspecialchars($d['name'] ?? '') ?>" required>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Email</label>
                    <input type="email" name="s_email" class="form-control"
                           value="<?= htmlspecialchars($d['email'] ?? '') ?>" required>
                </div>
                <div class="col-6">
                    <label class="form-label fw-semibold">Department</label>
                    <select name="s_department" class="form-select" required>
                        <?php foreach ($all_depts as $dep):
                            $sel = (($d['dept'] ?? '') === $dep) ? ' selected' : ''; ?>
                            <option value="<?= $dep ?>"<?= $sel ?>><?= $dep ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6">
                    <label class="form-label fw-semibold">Gender</label>
                    <select name="s_gender" class="form-select" required>
                        <option value="Male"   <?= (($d['gen'] ?? '') === 'Male')   ? 'selected' : '' ?>>Male</option>
                        <option value="Female" <?= (($d['gen'] ?? '') === 'Female') ? 'selected' : '' ?>>Female</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Password</label>
                    <input type="password" name="s_password" class="form-control" placeholder="Password" required>
                </div>
            </div>
            <div class="d-flex gap-2 justify-content-end mt-4">
                <a href="admin_users.php?tab=students" class="btn-cancel">Cancel</a>
                <button type="submit" class="btn btn-dark px-4">Save</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php /* ══════ EDIT STUDENT MODAL ══════ */
if ($edit_student_row): ?>
<div class="modal-overlay open">
    <div class="modal-box">
        <h5>Edit Student</h5>
        <?php if ($msg && $reopen_modal === 'edit_student'): ?>
            <div class="alert alert-danger py-2 mb-3"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>
        <form method="POST" action="admin_users.php">
            <input type="hidden" name="action"     value="edit_student">
            <input type="hidden" name="student_id" value="<?= htmlspecialchars($edit_student_row['studentID']) ?>">
            <div class="row g-3">
                <div class="col-6">
                    <label class="form-label fw-semibold">Student ID</label>
                    <input type="text" class="form-control field-readonly"
                           value="<?= htmlspecialchars($edit_student_row['studentID']) ?>" readonly>
                </div>
                <div class="col-6">
                    <label class="form-label fw-semibold">Full Name</label>
                    <input type="text" name="s_name" class="form-control"
                           value="<?= htmlspecialchars($edit_student_row['name']) ?>" required>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Email</label>
                    <input type="email" name="s_email" class="form-control"
                           value="<?= htmlspecialchars($edit_student_row['email']) ?>" required>
                </div>
                <div class="col-6">
                    <label class="form-label fw-semibold">Department</label>
                    <select name="s_department" class="form-select" required>
                        <?php foreach ($all_depts as $dep):
                            $sel = ($edit_student_row['department'] === $dep) ? ' selected' : ''; ?>
                            <option value="<?= $dep ?>"<?= $sel ?>><?= $dep ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6">
                    <label class="form-label fw-semibold">Gender</label>
                    <select name="s_gender" class="form-select" required>
                        <option value="Male"   <?= $edit_student_row['gender']==='Male'   ? 'selected':'' ?>>Male</option>
                        <option value="Female" <?= $edit_student_row['gender']==='Female' ? 'selected':'' ?>>Female</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">New Password
                        <small class="text-muted fw-normal">(leave blank to keep current)</small>
                    </label>
                    <input type="password" name="s_password" class="form-control" placeholder="New password">
                </div>
            </div>
            <div class="d-flex gap-2 justify-content-end mt-4">
                <a href="admin_users.php?tab=students" class="btn-cancel">Cancel</a>
                <button type="submit" class="btn btn-dark px-4">Save</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php /* ══════ CONFIRM DELETE STUDENT ══════ */
if ($confirm_del_sid):
    $r   = mysqli_prepare($conn, "SELECT name FROM students WHERE studentID=?");
    mysqli_stmt_bind_param($r, 's', $confirm_del_sid);
    mysqli_stmt_execute($r);
    $dr  = mysqli_fetch_assoc(mysqli_stmt_get_result($r));
    mysqli_stmt_close($r);
?>
<div class="modal-overlay open">
    <div class="confirm-box">
        <p>Delete student <strong><?= htmlspecialchars($dr['name'] ?? $confirm_del_sid) ?></strong>
           and all their registrations?</p>
        <div class="confirm-actions">
            <form method="POST" action="admin_users.php">
                <input type="hidden" name="action"     value="delete_student">
                <input type="hidden" name="student_id" value="<?= htmlspecialchars($confirm_del_sid) ?>">
                <button type="submit" class="btn-confirm-yes">Delete</button>
            </form>
            <a href="admin_users.php?tab=students" class="btn-cancel">Cancel</a>
        </div>
    </div>
</div>
<?php endif; ?>

<?php /* ══════ ADD ORGANIZER MODAL ══════ */
if ($show_add_org): $d = $reopen_data; ?>
<div class="modal-overlay open">
    <div class="modal-box">
        <h5>Add Organizer</h5>
        <?php if ($msg && $reopen_modal === 'add_org'): ?>
            <div class="alert alert-danger py-2 mb-3"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>
        <form method="POST" action="admin_users.php">
            <input type="hidden" name="action" value="add_organizer">
            <div class="row g-3">
                <div class="col-6">
                    <label class="form-label fw-semibold">Organizer ID
                        <small class="text-muted fw-normal">(max 9 chars)</small>
                    </label>
                    <input type="text" name="o_id" class="form-control" maxlength="9"
                           placeholder="e.g. 111111111"
                           value="<?= htmlspecialchars($d['oid'] ?? '') ?>" required>
                </div>
                <div class="col-6">
                    <label class="form-label fw-semibold">Full Name</label>
                    <input type="text" name="o_name" class="form-control"
                           value="<?= htmlspecialchars($d['name'] ?? '') ?>" required>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Email</label>
                    <input type="email" name="o_email" class="form-control"
                           value="<?= htmlspecialchars($d['email'] ?? '') ?>" required>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Role / Club Name</label>
                    <input type="text" name="o_role" class="form-control"
                           value="<?= htmlspecialchars($d['role'] ?? '') ?>" required>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Password</label>
                    <input type="password" name="o_password" class="form-control" placeholder="Password" required>
                </div>
            </div>
            <div class="d-flex gap-2 justify-content-end mt-4">
                <a href="admin_users.php?tab=organizers" class="btn-cancel">Cancel</a>
                <button type="submit" class="btn btn-dark px-4">Save</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php /* ══════ EDIT ORGANIZER MODAL ══════ */
if ($edit_org_row): ?>
<div class="modal-overlay open">
    <div class="modal-box">
        <h5>Edit Organizer</h5>
        <form method="POST" action="admin_users.php">
            <input type="hidden" name="action"      value="edit_organizer">
            <input type="hidden" name="o_id_hidden" value="<?= htmlspecialchars($edit_org_row['organizerID']) ?>">
            <div class="row g-3">
                <div class="col-6">
                    <label class="form-label fw-semibold">Organizer ID
                        <small class="text-muted fw-normal">(cannot change)</small>
                    </label>
                    <input type="text" class="form-control field-readonly"
                           value="<?= htmlspecialchars($edit_org_row['organizerID']) ?>" readonly>
                </div>
                <div class="col-6">
                    <label class="form-label fw-semibold">Full Name</label>
                    <input type="text" name="o_name" class="form-control"
                           value="<?= htmlspecialchars($edit_org_row['name']) ?>" required>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Email</label>
                    <input type="email" name="o_email" class="form-control"
                           value="<?= htmlspecialchars($edit_org_row['email']) ?>" required>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Role / Club Name</label>
                    <input type="text" name="o_role" class="form-control"
                           value="<?= htmlspecialchars($edit_org_row['role']) ?>" required>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">New Password
                        <small class="text-muted fw-normal">(leave blank to keep current)</small>
                    </label>
                    <input type="password" name="o_password" class="form-control" placeholder="New password">
                </div>
            </div>
            <div class="d-flex gap-2 justify-content-end mt-4">
                <a href="admin_users.php?tab=organizers" class="btn-cancel">Cancel</a>
                <button type="submit" class="btn btn-dark px-4">Save</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php /* ══════ CONFIRM DELETE ORGANIZER ══════ */
if ($confirm_del_oid):
    $r  = mysqli_prepare($conn, "SELECT name FROM organizers WHERE organizerID=?");
    mysqli_stmt_bind_param($r, 's', $confirm_del_oid);
    mysqli_stmt_execute($r);
    $dr = mysqli_fetch_assoc(mysqli_stmt_get_result($r));
    mysqli_stmt_close($r);
?>
<div class="modal-overlay open">
    <div class="confirm-box">
        <p>Delete organizer <strong><?= htmlspecialchars($dr['name'] ?? $confirm_del_oid) ?></strong>
           and all their events?</p>
        <div class="confirm-actions">
            <form method="POST" action="admin_users.php">
                <input type="hidden" name="action"       value="delete_organizer">
                <input type="hidden" name="organizer_id" value="<?= htmlspecialchars($confirm_del_oid) ?>">
                <button type="submit" class="btn-confirm-yes">Delete</button>
            </form>
            <a href="admin_users.php?tab=organizers" class="btn-cancel">Cancel</a>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ── Top Bar ── -->
<div class="topbar">
    <h4>Users Management</h4>
</div>

<div class="container-inner">

    <?php if ($msg && $reopen_modal === ''): ?>
        <div class="alert alert-<?= $msg_type ?> alert-dismissible fade show">
            <?= $msg ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Tabs -->
    <div class="mb-3">
        <a href="admin_users.php?tab=students"
           class="btn btn-sm <?= $active_tab==='students' ? 'btn-dark' : 'btn-outline-secondary' ?>">Students</a>
        <a href="admin_users.php?tab=organizers"
           class="btn btn-sm <?= $active_tab==='organizers' ? 'btn-dark' : 'btn-outline-secondary' ?>">Organizers</a>
    </div>

    <!-- Filters + Add button -->
    <div class="card">
        <form method="GET" action="admin_users.php" class="row g-2 align-items-end">
            <input type="hidden" name="tab" value="<?= $active_tab ?>">
            <div class="col-md-4">
                <input type="text" name="search" class="form-control"
                       placeholder="Search by name, email, or ID..."
                       value="<?= htmlspecialchars($search) ?>">
            </div>
            <?php if ($active_tab === 'students'): ?>
                <div class="col-md-3">
                    <select name="dept" class="form-select">
                        <option value="">All Departments</option>
                        <?php foreach ($all_depts as $dep)
                            echo "<option value='$dep'" . ($fDept===$dep?' selected':'') . ">$dep</option>"; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="gender" class="form-select">
                        <option value="">All Genders</option>
                        <option value="Male"   <?= $fGender==='Male'   ? 'selected':'' ?>>Male</option>
                        <option value="Female" <?= $fGender==='Female' ? 'selected':'' ?>>Female</option>
                    </select>
                </div>
            <?php endif; ?>
            <div class="col d-flex gap-2 flex-wrap">
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="admin_users.php?tab=<?= $active_tab ?>" class="btn btn-secondary">Reset</a>
                <?php if ($active_tab === 'students'): ?>
                    <a href="admin_users.php?tab=students&add_student=1" class="btn btn-dark">+ Add Student</a>
                <?php else: ?>
                    <a href="admin_users.php?tab=organizers&add_org=1" class="btn btn-dark">+ Add Organizer</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Table -->
    <div class="card">
        <?php if (mysqli_num_rows($data_res) == 0): ?>
            <p style="color:#888;">No records found.</p>
        <?php else: ?>
        <table class="table table-hover mb-0">
            <thead class="table-dark">
                <?php if ($active_tab === 'students'): ?>
                    <tr><th>#</th><th>Student ID</th><th>Name</th><th>Email</th><th>Department</th><th>Gender</th><th>Actions</th></tr>
                <?php else: ?>
                    <tr><th>#</th><th>Organizer ID</th><th>Name</th><th>Email</th><th>Role</th><th>Actions</th></tr>
                <?php endif; ?>
            </thead>
            <tbody>
            <?php $i = 1; while ($row = mysqli_fetch_assoc($data_res)): ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <?php if ($active_tab === 'students'): ?>
                        <td><strong><?= htmlspecialchars($row['studentID']) ?></strong></td>
                        <td><?= htmlspecialchars($row['name']) ?></td>
                        <td style="color:#666"><?= htmlspecialchars($row['email']) ?></td>
                        <td><span class="badge-dept"><?= htmlspecialchars($row['department']) ?></span></td>
                        <td><?= htmlspecialchars($row['gender']) ?></td>
                        <td>
                            <div class="d-flex gap-1">
                                <a href="admin_users.php?tab=students&edit_student=<?= urlencode($row['studentID']) ?>"
                                   class="btn btn-sm btn-outline-primary">Edit</a>
                                <a href="admin_users.php?tab=students&confirm_del_s=<?= urlencode($row['studentID']) ?>"
                                   class="btn btn-sm btn-outline-danger">Delete</a>
                            </div>
                        </td>
                    <?php else: ?>
                        <td><strong><?= htmlspecialchars($row['organizerID']) ?></strong></td>
                        <td><?= htmlspecialchars($row['name']) ?></td>
                        <td style="color:#666"><?= htmlspecialchars($row['email']) ?></td>
                        <td><span class="badge-role"><?= htmlspecialchars($row['role']) ?></span></td>
                        <td>
                            <div class="d-flex gap-1">
                                <a href="admin_users.php?tab=organizers&edit_org=<?= urlencode($row['organizerID']) ?>"
                                   class="btn btn-sm btn-outline-primary">Edit</a>
                                <a href="admin_users.php?tab=organizers&confirm_del_o=<?= urlencode($row['organizerID']) ?>"
                                   class="btn btn-sm btn-outline-danger">Delete</a>
                            </div>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php mysqli_close($conn); ?>
