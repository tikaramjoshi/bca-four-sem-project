<?php
session_start();
require_once "db.php";
if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    header("Location: login.php");
    exit;
}
$user_id = (int)$_SESSION['user_id'];
$current_role = $_SESSION['role'];
if ($current_role === "admin") {
    header("Location: admin/dashboard.php");
    exit;
}
$message = "";
$message_type = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $requested_role = $_POST['requested_role'] ?? "";
    $reason = trim($_POST['reason'] ?? "");
    $allowed_roles = ["passenger", "driver", "owner"];
    if (!in_array($requested_role, $allowed_roles)) {
        $message = "Please select a valid role.";
        $message_type = "error";
    } elseif ($requested_role === $current_role) {
        $message = "You already have this role.";
        $message_type = "error";
    } elseif ($reason === "") {
        $message = "Please enter a reason.";
        $message_type = "error";
    } else {
        $check = $conn->prepare("SELECT request_id FROM role_change_requests WHERE user_id=? AND status='pending' LIMIT 1");
        $check->bind_param("i", $user_id);
        $check->execute();
        $check->store_result();
        if ($check->num_rows > 0) {
            $message = "You already have a pending request.";
            $message_type = "error";
        } else {
            $stmt = $conn->prepare("INSERT INTO role_change_requests (user_id,old_role,requested_role,reason) VALUES (?,?,?,?)");
            $stmt->bind_param("isss", $user_id, $current_role, $requested_role, $reason);
            if ($stmt->execute()) {
                $message = "Request sent successfully to admin.";
                $message_type = "success";
            } else {
                $message = "Request failed. Please try again.";
                $message_type = "error";
            }
            $stmt->close();
        }
        $check->close();
    }
}
$stmt = $conn->prepare("SELECT requested_role,reason,status,admin_reason,requested_at,reviewed_at FROM role_change_requests WHERE user_id=? ORDER BY request_id DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Role Change Request</title>
    <link rel="stylesheet" href="role_request.css">
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>Role Change Request</h1>
            <p>Request a new role from the system administrator</p>
        </div>

        <?php if ($message != ""): ?>
            <div id="messagePopup" class="popup">
                <div class="popup-box <?php echo $message_type; ?>">
                    <h3><?php echo $message_type === "success" ? "Success" : "Error"; ?></h3>
                    <p><?php echo htmlspecialchars($message); ?></p>
                    <?php if ($message_type === "success"): ?>
                        <button type="button" onclick="goBack()">OK</button>
                    <?php else: ?>
                        <button type="button" onclick="closePopup()">OK</button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="main">
            <div class="form-card">
                <h2>Send Request</h2>
                <p class="sub">Fill in the details below and send your request to admin.</p>
                <form method="POST" action="role_request.php" id="roleForm">
                    <div class="field">
                        <label>Current Role</label>
                        <div class="current"><?php echo ucfirst(htmlspecialchars($current_role)); ?></div>
                    </div>

                    <div class="field">
                        <label>Request New Role</label>
                        <select name="requested_role" required>
                            <option value="">Select Role</option>
                            <?php if ($current_role != "passenger"): ?>
                                <option value="passenger">Passenger</option>
                            <?php endif; ?>
                            <?php if ($current_role != "driver"): ?>
                                <option value="driver">Driver</option>
                            <?php endif; ?>
                            <?php if ($current_role != "owner"): ?>
                                <option value="owner">Owner</option>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="field">
                        <label>Reason for Role Change</label>
                        <textarea name="reason" placeholder="Write your reason for requesting this role change..." required></textarea>
                    </div>

                    <button type="submit">Send Request</button>
                </form>
            </div>

            <div class="info-card">
                <h2>Available Roles</h2>
                <div class="role-item">
                    <h3>Passenger</h3>
                    <p>Search buses, book seats and manage tickets.</p>
                    <h3>Driver</h3>
                    <p>Manage assigned bus and passenger trips.</p>
                    <h3>Owner</h3>
                    <p>Manage buses, schedules and bus services.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="history">
        <h2>My Request History</h2>
        <div class="table-box">
            <table>
                <thead>
                    <tr>
                        <th>Requested Role</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Admin Response</th>
                        <th>Requested Date</th>
                        <th>Reviewed Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo ucfirst(htmlspecialchars($row['requested_role'])); ?></td>
                                <td><?php echo htmlspecialchars($row['reason']); ?></td>
                                <td>
                                    <span class="status <?php echo htmlspecialchars($row['status']); ?>">
                                        <?php echo ucfirst(htmlspecialchars($row['status'])); ?>
                                    </span>
                                </td>
                                <td><?php echo $row['admin_reason'] ? htmlspecialchars($row['admin_reason']) : "Waiting for admin"; ?></td>
                                <td><?php echo htmlspecialchars($row['requested_at']); ?></td>
                                <td><?php echo $row['reviewed_at'] ? htmlspecialchars($row['reviewed_at']) : "-"; ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="empty">No role change requests yet.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function goBack() {
            <?php
            if ($current_role === "passenger") {
                echo 'window.location.href="passenger/dashboard.php";';
            } elseif ($current_role === "driver") {
                echo 'window.location.href="driver/dashboard.php";';
            } elseif ($current_role === "owner") {
                echo 'window.location.href="owner/dashboard.php";';
            }
            ?>
        }

        function closePopup() {
            document.getElementById("messagePopup").style.display = "none";
        }
        document.getElementById("roleForm").addEventListener("submit", function(e) {
            let role = document.querySelector("[name='requested_role']").value;
            let reason = document.querySelector("[name='reason']").value.trim();
            if (role === "") {
                e.preventDefault();
                alert("Please select a role.");
                return;
            }
            if (reason === "") {
                e.preventDefault();
                alert("Please enter a reason.");
                return;
            }
            if (!confirm("Are you sure you want to send this role change request to admin?")) {
                e.preventDefault();
            }
        });
    </script>
</body>

</html>
<?php
$stmt->close();
$conn->close();
?>