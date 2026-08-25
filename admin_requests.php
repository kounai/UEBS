<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }
require_once 'db.php';

$admin_id = $_SESSION['admin_id'];
$msg = '';
$msg_type = '';

// Handle reply
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'reply') {
    $request_id = (int)$_POST['request_id'];
    $reply_text = trim($_POST['reply_text']);
    $new_status = in_array($_POST['status_after'], ['Pending','Resolved']) ? $_POST['status_after'] : 'Resolved';

    if (!empty($reply_text)) {
        $stmt = mysqli_prepare($conn, "INSERT INTO responses (requestID, adminID, reply, status_after) VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'iiss', $request_id, $admin_id, $reply_text, $new_status);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $stmt2 = mysqli_prepare($conn, "UPDATE requests SET status=? WHERE requestID=?");
        mysqli_stmt_bind_param($stmt2, 'si', $new_status, $request_id);
        mysqli_stmt_execute($stmt2);
        mysqli_stmt_close($stmt2);

        $msg = 'Reply sent successfully.'; $msg_type = 'success';
    } else {
        $msg = 'Reply cannot be empty.'; $msg_type = 'danger';
    }
}

// Fetch requests — use senderType (correct column name from schema)
$requests = [];
$res = mysqli_query($conn,
    "SELECT r.*, s.name AS student_name, o.name AS org_name
     FROM requests r
     LEFT JOIN students   s ON r.studentID   = s.studentID
     LEFT JOIN organizers o ON r.organizerID = o.organizerID
     ORDER BY r.created_at DESC");

while ($row = mysqli_fetch_assoc($res)) {
    // Get latest reply
    $rid  = (int)$row['requestID'];
    $resp = mysqli_query($conn, "SELECT * FROM responses WHERE requestID = $rid ORDER BY replied_at DESC LIMIT 1");
    if ($resp_row = mysqli_fetch_assoc($resp)) {
        $row['admin_reply'] = $resp_row['reply'];
        $row['replied_at']  = $resp_row['replied_at'];
    }
    $requests[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Support - DUEMS Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body { background-color: #f0f2f5; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }

        .topbar { background: white; padding: 15px 25px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 1px 4px rgba(0,0,0,0.08); margin-bottom: 25px; }
        .topbar h4 { margin: 0; color: #333; font-size: 16px; }

        .container-inner { padding: 0 25px 25px; }

        .ticket-card { background: white; border-radius: 8px; padding: 20px; box-shadow: 0 1px 4px rgba(0,0,0,0.08); margin-bottom: 16px; border-left: 4px solid #2c3e50; }
        .ticket-card.resolved { border-left-color: #28a745; opacity: 0.85; }

        .ticket-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; border-bottom: 1px solid #f0f0f0; padding-bottom: 10px; }
        .ticket-title  { font-size: 15px; font-weight: 700; color: #2c3e50; margin: 0; }
        .ticket-meta   { font-size: 12px; color: #888; margin-top: 4px; }
        .ticket-body   { font-size: 14px; color: #444; line-height: 1.7; background: #f8f9fa; padding: 12px 15px; border-radius: 6px; margin-bottom: 12px; }

        .badge-pending  { background-color: #ffc107; color: #333;  padding: 3px 9px; border-radius: 12px; font-size: 12px; font-weight: 600; }
        .badge-resolved { background-color: #28a745; color: white; padding: 3px 9px; border-radius: 12px; font-size: 12px; font-weight: 600; }

        .reply-block { background: #fffbea; border: 1px solid #fde68a; border-radius: 6px; padding: 12px 15px; margin-top: 10px; font-size: 13px; }
        .reply-block strong { color: #2c3e50; }
    </style>
</head>
<body>

<div class="topbar">
    <h4>Support Tickets</h4>
    <?php
    $total    = count($requests);
    $pending  = count(array_filter($requests, fn($r) => $r['status'] == 'Pending'));
    $resolved = $total - $pending;
    ?>
    <span style="font-size:13px; color:#888;">
        Total: <strong><?= $total ?></strong> &nbsp;|&nbsp;
        Pending: <strong style="color:#e67e22;"><?= $pending ?></strong> &nbsp;|&nbsp;
        Resolved: <strong style="color:#27ae60;"><?= $resolved ?></strong>
    </span>
</div>

<div class="container-inner">

    <?php if ($msg): ?>
        <div class="alert alert-<?= $msg_type ?>"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <?php if (empty($requests)): ?>
        <div class="ticket-card text-center" style="border-left-color:#ccc;">
            <p style="color:#888; margin:0;">No support tickets found.</p>
        </div>
    <?php endif; ?>

    <?php foreach ($requests as $r):
        // senderType column from database schema
        $sender_type = $r['senderType'] ?? 'student';
        $sender_name = ($sender_type == 'student') ? ($r['student_name'] ?? 'Unknown') : ($r['org_name'] ?? 'Unknown');
        $is_resolved = $r['status'] == 'Resolved';
    ?>
    <div class="ticket-card <?= $is_resolved ? 'resolved' : '' ?>">
        <div class="ticket-header">
            <div>
                <p class="ticket-title"><?= htmlspecialchars($r['subject']) ?></p>
                <p class="ticket-meta">
                    From: <strong><?= htmlspecialchars($sender_name) ?></strong>
                    (<?= ucfirst($sender_type) ?>) &bull;
                    Category: <?= htmlspecialchars($r['category']) ?> &bull;
                    <?= date('M d, Y H:i', strtotime($r['created_at'])) ?>
                </p>
            </div>
            <span class="badge-<?= strtolower($r['status']) ?>"><?= $r['status'] ?></span>
        </div>

        <div class="ticket-body">
            <?= nl2br(htmlspecialchars($r['message'])) ?>
        </div>

        <?php if (isset($r['admin_reply'])): ?>
            <div class="reply-block">
                <strong>Admin Reply:</strong><br>
                <?= nl2br(htmlspecialchars($r['admin_reply'])) ?>
                <div style="font-size:11px; color:#999; margin-top:5px;"><?= date('M d, Y H:i', strtotime($r['replied_at'])) ?></div>
            </div>
        <?php elseif (!$is_resolved): ?>
            <form method="POST" class="mt-3">
                <input type="hidden" name="action"     value="reply">
                <input type="hidden" name="request_id" value="<?= $r['requestID'] ?>">
                <div class="mb-2">
                    <textarea name="reply_text" class="form-control" rows="3" placeholder="Write your reply..." required></textarea>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    <select name="status_after" class="form-select" style="width:auto;">
                        <option value="Resolved">Mark as Resolved</option>
                    </select>
                    <button type="submit" class="btn btn-dark">Send Reply</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>

</div>
</body>
</html>
<?php mysqli_close($conn); ?>
