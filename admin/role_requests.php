<?php
session_start();
require_once "../db.php";

if (!isset($_SESSION['user_id'], $_SESSION['role']) || $_SESSION['role'] !== "admin") {
    header("Location: ../login.php");
    exit;
}

$message = "";
$message_type = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $request_id = (int)($_POST['request_id'] ?? 0);
    $action = $_POST['action'] ?? "";
    $admin_reason = trim($_POST['admin_reason'] ?? "");

    if ($request_id <= 0) {
        $message = "Invalid request.";
        $message_type = "error";
    } elseif ($action !== "approve" && $action !== "reject") {
        $message = "Invalid action.";
        $message_type = "error";
    } elseif ($action === "reject" && $admin_reason === "") {
        $message = "Please enter a reason for rejection.";
        $message_type = "error";
    } else {
        $stmt = $conn->prepare("SELECT user_id,requested_role,status FROM role_change_requests WHERE request_id=? LIMIT 1");
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $request = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$request) {
            $message = "Request not found.";
            $message_type = "error";
        } elseif ($request['status'] !== "pending") {
            $message = "This request has already been reviewed.";
            $message_type = "error";
        } else {
            if ($action === "approve") {
                $status = "approved";
                $reason = $admin_reason !== "" ? $admin_reason : "Request approved by admin.";

                $conn->begin_transaction();

                try {
                    $update_user = $conn->prepare("UPDATE users SET role=? WHERE user_id=?");
                    $update_user->bind_param("si", $request['requested_role'], $request['user_id']);
                    $update_user->execute();
                    $update_user->close();

                    $update_request = $conn->prepare("UPDATE role_change_requests SET status=?,admin_reason=?,reviewed_at=NOW() WHERE request_id=?");
                    $update_request->bind_param("ssi", $status, $reason, $request_id);
                    $update_request->execute();
                    $update_request->close();

                    $conn->commit();

                    $message = "Role change request approved successfully.";
                    $message_type = "success";
                } catch (Exception $e) {
                    $conn->rollback();
                    $message = "Failed to approve request.";
                    $message_type = "error";
                }
            } else {
                $status = "rejected";

                $stmt = $conn->prepare("UPDATE role_change_requests SET status=?,admin_reason=?,reviewed_at=NOW() WHERE request_id=?");
                $stmt->bind_param("ssi", $status, $admin_reason, $request_id);

                if ($stmt->execute()) {
                    $message = "Role change request rejected.";
                    $message_type = "success";
                } else {
                    $message = "Failed to reject request.";
                    $message_type = "error";
                }

                $stmt->close();
            }
        }
    }
}

$sql = "SELECT 
        r.request_id,
        r.user_id,
        r.old_role,
        r.requested_role,
        r.reason,
        r.status,
        r.admin_reason,
        r.requested_at,
        r.reviewed_at,
        u.name,
        u.email,
        u.phone,
        u.profile_image
      FROM role_change_requests r
      INNER JOIN users u ON r.user_id=u.user_id
      ORDER BY 
        CASE WHEN r.status='pending' THEN 0 ELSE 1 END,
        r.requested_at DESC";

$result = $conn->query($sql);
?>

<!DOCTYPE html>

<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Role Change Requests</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #eef2ff, #fdf2f8, #ecfeff);
            padding: 30px;
        }

        .container {
            width: 100%;
            max-width: 1250px;
            margin: auto;
        }

        .header {
            background: linear-gradient(135deg, #6a11cb, #2575fc, #00c6ff);
            color: white;
            padding: 28px 30px;
            border-radius: 20px;
            margin-bottom: 25px;
            box-shadow: 0 12px 30px rgba(0, 0, 0, .15);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 30px;
            margin-bottom: 7px;
        }

        .header p {
            font-size: 16px;
        }

        .pending {
            background: linear-gradient(135deg, #ff512f, #dd2476);
            padding: 14px 22px;
            border-radius: 30px;
            font-size: 16px;
            font-weight: bold;
        }

        .message {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            color: white;
            font-size: 16px;
            font-weight: bold;
            text-align: center;
        }

        .message.success {
            background: linear-gradient(135deg, #00b09b, #96c93d);
        }

        .message.error {
            background: linear-gradient(135deg, #ff416c, #ff4b2b);
        }

        .table-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 12px 30px rgba(0, 0, 0, .1);
        }

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 1200px;
            border-collapse: collapse;
        }

        thead {
            background: linear-gradient(90deg, #6a11cb, #2575fc, #00c6ff);
        }

        th {
            color: white;
            padding: 15px 12px;
            text-align: left;
            font-size: 14px;
            white-space: nowrap;
        }

        td {
            padding: 15px 12px;
            border-bottom: 1px solid #e5e7eb;
            color: #334155;
            font-size: 14px;
            vertical-align: middle;
        }

        tbody tr:hover .user {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user img {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #ddd6fe;
        }

        .user-name {
            font-weight: bold;
            color: #4c1d95;
        }

        .user-email {
            font-size: 12px;
            color: #64748b;
            margin-top: 3px;
        }

        .role {
            display: inline-block;
            padding: 7px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .passenger {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .driver {
            background: #dcfce7;
            color: #15803d;
        }

        .owner {
            background: #fef3c7;
            color: #b45309;
        }

        .status {
            display: inline-block;
            padding: 7px 13px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 12px;
        }

        .status.pending {
            background: #fef3c7;
            color: #b45309;
        }

        .status.approved {
            background: #dcfce7;
            color: #15803d;
        }

        .status.rejected {
            background: #fee2e2;
            color: #dc2626;
        }

        .reason {
            max-width: 220px;
            line-height: 1.5;
        }

        .action-box {
            display: flex;
            gap: 8px;
        }

        .action-box form {
            margin: 0;
        }

        .approve,
        .reject {
            border: 0;
            padding: 9px 13px;
            border-radius: 8px;
            color: white;
            font-weight: bold;
            cursor: pointer;
            font-size: 12px;
        }

        .approve {
            background: linear-gradient(135deg, #00b09b, #38ef7d);
        }

        .reject {
            background: linear-gradient(135deg, #ff416c, #ff4b2b);
        }

        .approve:hover,
        .reject:hover {
            transform: translateY(-1px);
        }

        .waiting {
            color: #94a3b8;
            font-weight: bold;
        }

        .empty {
            text-align: center;
            padding: 45px;
            color: #64748b;
            font-size: 17px;
        }

        @media(max-width:700px) {
            body {
                padding: 15px;
            }

            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .header h1 {
                font-size: 25px;
            }

            .table-card {
                padding: 15px;
            }

        }
    </style>

</head>

<body>

    <div class="container">

        ```
        <div class="header">
            <div>
                <h1>Role Change Requests</h1>
                <p>Manage passenger, driver and owner role requests</p>
            </div>

            <?php
            $pending_result = $conn->query("SELECT COUNT(*) AS total FROM role_change_requests WHERE status='pending'");
            $pending_row = $pending_result->fetch_assoc();
            ?>

            <div class="pending">
                🔔 <?php echo (int)$pending_row['total']; ?> Pending
            </div>
        </div>

        <?php if ($message != ""): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="table-card">

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

                        <?php if ($result && $result->num_rows > 0): ?>

                            <?php while ($row = $result->fetch_assoc()): ?>

                                <tr>

                                    <td>
                                        <div class="user">
                                            <?php
                                            $image = "../uploads/default.png";

                                            if (!empty($row['profile_image'])) {
                                                $image = "../uploads/" . htmlspecialchars($row['profile_image']);
                                            }
                                            ?>
                                            <img src="<?php echo $image; ?>" onerror="this.src='../uploads/default.png'">

                                            <div>
                                                <div class="user-name">
                                                    <?php echo htmlspecialchars($row['name']); ?>
                                                </div>
                                                <div class="user-email">
                                                    <?php echo htmlspecialchars($row['email']); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <td>
                                        <?php echo htmlspecialchars($row['phone']); ?>
                                    </td>

                                    <td>
                                        <span class="role <?php echo htmlspecialchars($row['old_role']); ?>">
                                            <?php echo htmlspecialchars($row['old_role']); ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span class="role <?php echo htmlspecialchars($row['requested_role']); ?>">
                                            <?php echo htmlspecialchars($row['requested_role']); ?>
                                        </span>
                                    </td>

                                    <td>
                                        <div class="reason">
                                            <?php echo htmlspecialchars($row['reason']); ?>
                                        </div>
                                    </td>

                                    <td>
                                        <span class="status <?php echo htmlspecialchars($row['status']); ?>">
                                            <?php echo ucfirst(htmlspecialchars($row['status'])); ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?php echo htmlspecialchars($row['requested_at']); ?>
                                    </td>

                                    <td>

                                        <?php if ($row['status'] === "pending"): ?>

                                            <div class="action-box">

                                                <form method="POST" action="role_requests.php" class="approveForm">
                                                    <input type="hidden" name="request_id" value="<?php echo (int)$row['request_id']; ?>">
                                                    <input type="hidden" name="action" value="approve">
                                                    <button type="submit" class="approve">Approve</button>
                                                </form>

                                                <form method="POST" action="role_requests.php" class="rejectForm">
                                                    <input type="hidden" name="request_id" value="<?php echo (int)$row['request_id']; ?>">
                                                    <input type="hidden" name="action" value="reject">
                                                    <input type="hidden" name="admin_reason" class="adminReason">
                                                    <button type="submit" class="reject">Reject</button>
                                                </form>

                                            </div>

                                        <?php else: ?>

                                            <span class="waiting">Reviewed</span>

                                        <?php endif; ?>

                                    </td>

                                </tr>

                            <?php endwhile; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="8" class="empty">
                                    No role change requests found.
                                </td>
                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>


    </div>

    <script>
        document.querySelectorAll(".approveForm").forEach(function(form) {
            form.addEventListener("submit", function(e) {
                if (!confirm("Are you sure you want to approve this role change request?")) {
                    e.preventDefault();
                }
            });
        });

        document.querySelectorAll(".rejectForm").forEach(function(form) {
            form.addEventListener("submit", function(e) {
                let reason = prompt("Enter reason for rejecting this request:");

                if (reason === null) {
                    e.preventDefault();
                    return;
                }

                reason = reason.trim();

                if (reason === "") {
                    e.preventDefault();
                    alert("Rejection reason is required.");
                    return;
                }

                form.querySelector(".adminReason").value = reason;
            });
        });
    </script>

</body>

</html>
<?php
$conn->close();
?>