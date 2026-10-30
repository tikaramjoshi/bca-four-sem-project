<?php
session_start();
require_once "../db.php";
if (!isset($_SESSION['user_id'], $_SESSION['role']) || $_SESSION['role'] !== "admin") {
    header("Location: ../login.php");
    exit;
}
$message = $type = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $id = (int)($_POST['request_id'] ?? 0);
    $action = $_POST['action'] ?? "";
    $reason = trim($_POST['admin_reason'] ?? "");
    if ($id <= 0) {
        $message = "Invalid request.";
        $type = "error";
    } elseif (!in_array($action, ["approve", "reject"])) {
        $message = "Invalid action.";
        $type = "error";
    } elseif ($action === "reject" && !$reason) {
        $message = "Please enter a reason for rejection.";
        $type = "error";
    } else {
        $s = $conn->prepare("SELECT user_id,requested_role,status FROM role_change_requests WHERE request_id=? LIMIT 1");
        $s->bind_param("i", $id);
        $s->execute();
        $r = $s->get_result()->fetch_assoc();
        $s->close();
        if (!$r) {
            $message = "Request not found.";
            $type = "error";
        } elseif ($r['status'] !== "pending") {
            $message = "This request has already been reviewed.";
            $type = "error";
        } else {
            $status = $action === "approve" ? "approved" : "rejected";
            $reason = $action === "approve" && $reason === "" ? "Request approved by admin." : $reason;
            $conn->begin_transaction();
            try {
                if ($action === "approve") {
                    $s = $conn->prepare("UPDATE users SET role=? WHERE user_id=?");
                    $s->bind_param("si", $r['requested_role'], $r['user_id']);
                    $s->execute();
                    $s->close();
                }
                $s = $conn->prepare("UPDATE role_change_requests SET status=?,admin_reason=?,reviewed_at=NOW() WHERE request_id=?");
                $s->bind_param("ssi", $status, $reason, $id);
                $s->execute();
                $s->close();
                $conn->commit();
                $message = $action === "approve" ? "Role change request approved successfully." : "Role change request rejected.";
                $type = "success";
            } catch (Exception $e) {
                $conn->rollback();
                $message = "Failed to process request.";
                $type = "error";
            }
        }
    }
}
$result = $conn->query("SELECT r.request_id,r.user_id,r.old_role,r.requested_role,r.reason,r.status,r.requested_at,u.name,u.email,u.phone,u.profile_image FROM role_change_requests r JOIN users u ON r.user_id=u.user_id ORDER BY CASE WHEN r.status='pending' THEN 0 ELSE 1 END,r.requested_at DESC");
$p = $conn->query("SELECT COUNT(*) total FROM role_change_requests WHERE status='pending'")->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Role Change Requests</title>
    <link rel="stylesheet" href="role_requests.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>

<body>
    <?php include "admin_header.php" ?>
    <div class="contain">
        <?php if ($message): ?><div class="message <?= $type ?>"><?= htmlspecialchars($message) ?></div><?php endif; ?>
        <div class="table-card">
            <div class="pending"> <?= $p ?> Pending</div>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Phone</th>
                            <th>Current Role</th>
                            <th>Requested Role</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th>Requested Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows): while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <div class="user"><?php $img = !empty($row['profile_image']) ? "../uploads/" . $row['profile_image'] : "../uploads/default.png"; ?><img src="<?= htmlspecialchars($img) ?>" onerror="this.src='../uploads/default.png'">
                                            <div>
                                                <div class="user-name"><?= htmlspecialchars($row['name']) ?></div>
                                                <div class="user-email"><?= htmlspecialchars($row['email']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($row['phone']) ?></td>
                                    <td><span class="role <?= $row['old_role'] ?>"><?= htmlspecialchars($row['old_role']) ?></span></td>
                                    <td><span class="role <?= $row['requested_role'] ?>"><?= htmlspecialchars($row['requested_role']) ?></span></td>
                                    <td>
                                        <div class="reason"><?= htmlspecialchars($row['reason']) ?></div>
                                    </td>
                                    <td><span class="status <?= $row['status'] ?>"><?= ucfirst(htmlspecialchars($row['status'])) ?></span></td>
                                    <td><?= htmlspecialchars($row['requested_at']) ?></td>
                                    <td>
                                        <?php if ($row['status'] === "pending"): ?>
                                            <div class="action-box">
                                                <form method="POST" class="approveForm"><input type="hidden" name="request_id" value="<?= $row['request_id'] ?>"><input type="hidden" name="action" value="approve"><button class="approve"> <i class="fa fa-check"></i>&nbsp; Approve</button></form>
                                                <form method="POST" class="rejectForm"><input type="hidden" name="request_id" value="<?= $row['request_id'] ?>"><input type="hidden" name="action" value="reject"><input type="hidden" name="admin_reason" class="adminReason"><button class="reject"> <i class="fa fa-close"></i>&nbsp; Reject</button></form>
                                            </div>
                                        <?php else: ?><span class="waiting">Reviewed</span><?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile;
                        else: ?>
                            <tr>
                                <td colspan="8" class="empty">No role change requests found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <script>
        document.querySelectorAll(".approveForm").forEach(f => f.onsubmit = e => {
            if (!confirm("Are you sure you want to approve this role change request?")) e.preventDefault()
        });
        document.querySelectorAll(".rejectForm").forEach(f => f.onsubmit = e => {
            let r = prompt("Enter reason for rejecting this request:");
            if (r === null || !(r = r.trim())) {
                e.preventDefault();
                if (r !== "") alert("Rejection reason is required.");
            } else f.querySelector(".adminReason").value = r
        });
    </script>
</body>

</html>
<?php $conn->close(); ?>