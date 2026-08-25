<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }
require_once 'db.php';

$admin_id   = (int)$_SESSION['admin_id'];
$admin_name = $_SESSION['admin_name'] ?? 'Admin';
$msg        = '';
$msg_type   = '';

// Handle Approve / Reject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action   = $_POST['action'];
    $event_id = (int)($_POST['event_id'] ?? 0);
    $remarks  = trim($_POST['remarks']   ?? '');

    if ($event_id && in_array($action, ['approve', 'reject'])) {
        $new_status = ($action === 'approve') ? 'approved' : 'rejected';
        $decision   = $new_status;

        $stmt = mysqli_prepare($conn, "UPDATE events SET status = ? WHERE eventID = ?");
        mysqli_stmt_bind_param($stmt, 'si', $new_status, $event_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $stmt2 = mysqli_prepare($conn,
            "INSERT INTO approvals (eventID, adminID, decision, decisionDate, remarks)
             VALUES (?, ?, ?, NOW(), ?)");
        mysqli_stmt_bind_param($stmt2, 'iiss', $event_id, $admin_id, $decision, $remarks);
        mysqli_stmt_execute($stmt2);
        mysqli_stmt_close($stmt2);

        $msg      = $action === 'approve' ? 'Event approved successfully.' : 'Event rejected.';
        $msg_type = $action === 'approve' ? 'success' : 'danger';
    }
}

// Stats
$cnt_pending  = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM events WHERE status='pending'"))['c'];
$cnt_approved = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM events WHERE status='approved'"))['c'];
$cnt_rejected = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM events WHERE status='rejected'"))['c'];

// Filter
$fStatus     = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : 'pending';
$whereStatus = $fStatus !== '' ? "AND e.status = '$fStatus'" : '';

// Fetch events
$events = mysqli_query($conn, "
    SELECT e.*,
           o.name AS org_name,
           o.role AS org_role,
           (SELECT COUNT(*) FROM registrations r WHERE r.eventID = e.eventID AND r.status = 'confirmed') AS reg_count,
           ap.decision     AS ap_decision,
           ap.decisionDate AS ap_date,
           ap.remarks      AS ap_remarks,
           a.name          AS ap_admin_name
    FROM events e
    LEFT JOIN organizers o ON e.organizerID = o.organizerID
    LEFT JOIN approvals  ap ON ap.approvalID = (
        SELECT MAX(approvalID) FROM approvals WHERE eventID = e.eventID
    )
    LEFT JOIN admins a ON ap.adminID = a.adminID
    WHERE 1=1 $whereStatus
    ORDER BY e.created_at DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Event Approvals - DUEMS Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body { background-color: #f0f2f5; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }

        /* Topbar — identical to admin_overview.php */
        .topbar { background: white; padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 1px 4px rgba(0,0,0,0.08); display: flex; justify-content: space-between; align-items: center; }
        .topbar h4 { margin: 0; color: #333; }

        .wrap { padding: 25px; }

        /* Stat cards — identical to admin_overview.php */
        .card { background: white; border-radius: 8px; padding: 20px; box-shadow: 0 1px 4px rgba(0,0,0,0.08); margin-bottom: 20px; border: none; }

        /* Filter tabs — plain, no rounded pills */
        .filter-tabs { display: flex; gap: 8px; margin-bottom: 20px; flex-wrap: wrap; }
        .tab-btn { padding: 6px 16px; border-radius: 6px; border: 1px solid #d1d5db; background: white; color: #555; font-size: 13px; font-weight: 600; cursor: pointer; text-decoration: none; transition: background .15s; }
        .tab-btn:hover { background: #f0f2f5; color: #333; text-decoration: none; }
        .tab-btn.tab-active { background: #2c3e50; color: #f0c040; border-color: #2c3e50; }

        /* Status badges — same style as rest of admin pages */
        .badge-pending   { background-color: #ffc107; color: #333;  padding: 3px 9px; border-radius: 12px; font-size: 12px; font-weight: 600; }
        .badge-approved  { background-color: #28a745; color: white; padding: 3px 9px; border-radius: 12px; font-size: 12px; font-weight: 600; }
        .badge-rejected  { background-color: #dc3545; color: white; padding: 3px 9px; border-radius: 12px; font-size: 12px; font-weight: 600; }
        .badge-cancelled { background-color: #6c757d; color: white; padding: 3px 9px; border-radius: 12px; font-size: 12px; font-weight: 600; }

        /* Event card left border accent */
        .ev-card { border: none; }
        .ev-card.pending  { border-left: 4px solid #ffc107 !important; }
        .ev-card.approved { border-left: 4px solid #28a745 !important; }
        .ev-card.rejected { border-left: 4px solid #dc3545 !important; }

        /* Approval record box */
        .approval-record { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 6px; padding: 10px 14px; font-size: 13px; margin-top: 12px; color: #555; }
        .approval-record.approved { background: #f0fdf4; border-color: #bbf7d0; color: #166534; }
        .approval-record.rejected { background: #fef2f2; border-color: #fca5a5; color: #991b1b; }

        /* Modal */
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center; }
        .modal-overlay.active { display: flex; }
        .modal-box { background: white; border-radius: 10px; padding: 28px; width: 440px; max-width: 95vw; box-shadow: 0 8px 30px rgba(0,0,0,0.2); }
        .modal-box h5 { margin: 0 0 4px; color: #2c3e50; font-size: 16px; }
        .modal-subtitle { font-size: 13px; color: #888; margin-bottom: 20px; }
        .btn-main { background-color: #2c3e50; color: #f0c040; font-weight: bold; border: none; padding: 8px 18px; border-radius: 6px; font-size: 14px; cursor: pointer; }
        .btn-main:hover { background-color: #3d5166; color: #f0c040; }
    </style>
</head>
<body>

<!-- Decision Modal -->
<div class="modal-overlay" id="decisionModal">
  <div class="modal-box">
    <h5 id="modalTitle">Approve Event</h5>
    <p class="modal-subtitle">Event: <strong id="modalEventName"></strong></p>
    <form method="POST" id="decisionForm">
      <input type="hidden" name="action"   id="d_action">
      <input type="hidden" name="event_id" id="d_eid">
      <div class="mb-4">
        <label class="form-label" style="font-size:13px;font-weight:600;">
          Remarks for Organizer <span style="color:#aaa;font-weight:400;">(optional)</span>
        </label>
        <textarea name="remarks" class="form-control" rows="3"
                  placeholder="Add notes or reason for the organizer…"></textarea>
      </div>
      <div class="d-flex gap-2">
        <button type="submit" id="modalSubmitBtn" class="btn btn-success px-4">Approve</button>
        <button type="button" class="btn btn-outline-secondary px-4" onclick="closeModal()">Cancel</button>
      </div>
    </form>
  </div>
</div>

<div class="wrap">

    <!-- Topbar -->
    <div class="topbar">
        <h4>Event Approvals</h4>
        <span style="font-size:13px; color:#888;">Administrator: <?= htmlspecialchars($admin_name) ?></span>
    </div>

  

    <!-- Stat cards — same layout as admin_overview.php -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card text-center">
                <h2 style="color:#e67e22;"><?= $cnt_pending ?></h2>
                <p style="color:#888; margin:0; font-size:13px;">Pending Review</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center">
                <h2 style="color:#27ae60;"><?= $cnt_approved ?></h2>
                <p style="color:#888; margin:0; font-size:13px;">Approved</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center">
                <h2 style="color:#e74c3c;"><?= $cnt_rejected ?></h2>
                <p style="color:#888; margin:0; font-size:13px;">Rejected</p>
            </div>
        </div>
    </div>

    <!-- Filter tabs -->
    <div class="filter-tabs">
        <?php
        $tabs = [
             ''         => 'All Events',
            'pending'  => 'Pending',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
          
        ];
        foreach ($tabs as $val => $label):
            $active = $fStatus === $val ? 'tab-active' : '';
            $cnt = '';
            if ($val === 'pending' && $cnt_pending > 0) $cnt = ' (' . $cnt_pending . ')';
        ?>
            <a href="?status=<?= urlencode($val) ?>"
               class="tab-btn <?= $active ?>">
               <?= $label . $cnt ?>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Events -->
    <div class="card ev-list" style="padding:0;">
        <?php
        $found = false;
        while ($ev = mysqli_fetch_assoc($events)):
            $found = true;
        ?>
        <div style="padding:20px; border-bottom:1px solid #dee2e6; border-left:4px solid <?= $ev['status']==='pending'?'#ffc107':($ev['status']==='approved'?'#28a745':'#dc3545') ?>;">
            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                <div style="flex:1;">
                    <div style="font-size:15px;font-weight:700;color:#2c3e50;margin-bottom:6px;">
                        <?= htmlspecialchars($ev['title']) ?>
                    </div>
                    <div style="font-size:12px;color:#888;display:flex;flex-wrap:wrap;gap:14px;margin-bottom:8px;">
                        <span>Organizer: <strong style="color:#555;"><?= htmlspecialchars($ev['org_name'] ?? '—') ?></strong>
                            <?php if ($ev['org_role']): ?><em>(<?= htmlspecialchars($ev['org_role']) ?>)</em><?php endif; ?>
                        </span>
                        <span>Date: <?= date('M d, Y  h:i A', strtotime($ev['date'])) ?></span>
                        <span>Location: <?= htmlspecialchars($ev['location']) ?></span>
                        <span>Dept: <?= htmlspecialchars($ev['department']) ?></span>
                        <span>Capacity: <?= $ev['capacity'] ?> &nbsp;|&nbsp; Registered: <?= $ev['reg_count'] ?></span>
                        <span>Gender: <?= htmlspecialchars($ev['gender']) ?></span>
                        <span><?= $ev['is_paid']==='Yes'
                            ? 'Fee: <strong>SAR '.number_format($ev['price'],2).'</strong>'
                            : 'Free' ?></span>
                    </div>
                    <?php if ($ev['description']): ?>
                        <div style="font-size:13px;color:#666;line-height:1.6;max-width:700px;">
                            <?= nl2br(htmlspecialchars(substr($ev['description'], 0, 250))) ?><?= strlen($ev['description'])>250?'…':'' ?>
                        </div>
                    <?php endif; ?>

                    <!-- Existing approval record -->
                    <?php if ($ev['ap_decision']): ?>
                        <div class="approval-record <?= $ev['ap_decision'] ?>">
                            <strong><?= ucfirst($ev['ap_decision']) ?></strong>
                            by <?= htmlspecialchars($ev['ap_admin_name'] ?? 'Admin') ?>
                            on <?= date('M d, Y H:i', strtotime($ev['ap_date'])) ?>
                            <?php if ($ev['ap_remarks']): ?>
                                &mdash; <em><?= htmlspecialchars($ev['ap_remarks']) ?></em>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div style="font-size:11px;color:#aaa;margin-top:10px;">
                        Submitted: <?= date('M d, Y H:i', strtotime($ev['created_at'])) ?>
                        &nbsp;·&nbsp; Event #<?= $ev['eventID'] ?>
                    </div>
                </div>

                <div class="d-flex flex-column align-items-end gap-2 flex-shrink-0">
                    <span class="badge-<?= $ev['status'] ?>"><?= ucfirst($ev['status']) ?></span>
                    <?php if ($ev['status'] === 'pending'): ?>
                        <div class="d-flex gap-2 mt-1">
                            <button class="btn btn-sm btn-success"
                                    onclick="openModal('approve', <?= $ev['eventID'] ?>, <?= htmlspecialchars(json_encode($ev['title'])) ?>)">
                                Approve
                            </button>
                            <button class="btn btn-sm btn-danger"
                                    onclick="openModal('reject', <?= $ev['eventID'] ?>, <?= htmlspecialchars(json_encode($ev['title'])) ?>)">
                                Reject
                            </button>
                        </div>
                    <?php else: ?>
                        <span style="font-size:12px;color:#aaa;font-style:italic;">Decision recorded</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endwhile; ?>

        <?php if (!$found): ?>
            <div style="text-align:center;padding:60px 20px;color:#888;">
                <p style="font-size:15px;font-weight:600;margin-bottom:6px;">No events found</p>
                <p style="font-size:13px;margin:0;">
                    <?php if ($fStatus === 'pending'): ?>
                        All organizer events have been reviewed.
                    <?php else: ?>
                        No events with status: <strong><?= htmlspecialchars($fStatus ?: 'any') ?></strong>
                    <?php endif; ?>
                </p>
            </div>
        <?php endif; ?>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openModal(action, eid, title) {
    var isApprove = action === 'approve';
    document.getElementById('d_action').value        = action;
    document.getElementById('d_eid').value           = eid;
    document.getElementById('modalTitle').textContent     = isApprove ? 'Approve Event' : 'Reject Event';
    document.getElementById('modalEventName').textContent = title;
    var btn = document.getElementById('modalSubmitBtn');
    btn.textContent = isApprove ? 'Approve' : 'Reject';
    btn.className   = 'btn px-4 ' + (isApprove ? 'btn-success' : 'btn-danger');
    document.querySelector('#decisionForm textarea[name="remarks"]').value = '';
    document.getElementById('decisionModal').classList.add('active');
}
function closeModal() {
    document.getElementById('decisionModal').classList.remove('active');
}
document.getElementById('decisionModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
</script>
</body>
</html>
<?php mysqli_close($conn); ?>
