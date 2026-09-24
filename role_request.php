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
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        body {
            background: #f4f6f9;
            padding: 25px;
            color: #333;
        }

        .container {
            max-width: 1100px;
            margin: auto;
        }

        .header {
            background: #2575fc;
            color: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .header h1 {
            font-size: 26px;
            margin-bottom: 6px;
        }

        .header p {
            font-size: 15px;
        }

        .main {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-card,
        .info-card,
        .history {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, .08);
        }

        .form-card h2,
        .info-card h2,
        .history h2 {
            font-size: 22px;
            margin-bottom: 10px;
        }

        .form-card h2,
        .history h2 {
            color: #2575fc;
        }

        .sub {
            color: #666;
            font-size: 14px;
            margin-bottom: 20px;
        }

        .field {
            margin-bottom: 18px;
        }

        .field label {
            display: block;
            font-size: 15px;
            font-weight: bold;
            margin-bottom: 7px;
        }

        .current {
            padding: 12px;
            background: #f1f5f9;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 15px;
        }

        select,
        textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 15px;
            outline: none;
        }

        select:focus,
        textarea:focus {
            border-color: #2575fc;
        }

        textarea {
            height: 100px;
            resize: vertical;
        }

        button {
            width: 100%;
            padding: 12px;
            border: 0;
            border-radius: 6px;
            background: #2575fc;
            color: white;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }

        button:hover {
            background: #145dcc;
        }

        .info-card {
            background: #505d61;
            color: white;
        }

        .info-card h2 {
            color: white;
        }

        .role-item h3 {
            font-size: 17px;
            margin-top: 18px;
            margin-bottom: 5px;
        }

        .role-item p {
            font-size: 14px;
            line-height: 1.5;
        }

        .history {
            max-width: 1100px;
            margin: 20px auto 0;
            overflow-x: auto;
        }

        .history h2 {
            margin-bottom: 15px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #4f6387;
            color: white;
            padding: 12px;
            text-align: left;
            font-size: 14px;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid #ddd;
            font-size: 14px;
        }

        tr:hover {
            background: #f8fafc;
        }

        .status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 13px;
            font-weight: bold;
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

        .empty {
            text-align: center;
            padding: 25px;
            color: #777;
        }

        .popup {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, .5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }

        .popup-box {
            width: 380px;
            background: white;
            padding: 30px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0, 0, 0, .2);
        }

        .popup-box h3 {
            font-size: 24px;
            margin-bottom: 12px;
        }

        .popup-box p {
            font-size: 16px;
            margin-bottom: 20px;
        }

        .popup-box.success h3 {
            color: #16a34a;
        }

        .popup-box.error h3 {
            color: #dc2626;
        }

        .popup-box button {
            width: auto;
            min-width: 100px;
            padding: 10px 25px;
            background: #2575fc;
            color: white;
        }

        .popup-box button:hover {
            background: #145dcc;
        }

        @media(max-width:768px) {
            body {
                padding: 15px;
            }

            .main {
                grid-template-columns: 1fr;
            }

            .header h1 {
                font-size: 22px;
            }

            .form-card,
            .info-card,
            .history {
                padding: 18px;
            }

            table {
                min-width: 750px;
            }
        }
    </style>
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