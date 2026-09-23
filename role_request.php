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
            min-height: 100vh;
            padding: 35px;
            background: linear-gradient(120deg, #ffecd2, #fcb69f, #c2e9fb, #d4fc79, #a1c4fd);
            background-size: 400% 400%;
            animation: bg 12s ease infinite;
        }

        @keyframes bg {
            0% {
                background-position: 0% 50%
            }

            50% {
                background-position: 100% 50%
            }

            100% {
                background-position: 0% 50%
            }
        }

        .container {
            max-width: 1150px;
            margin: auto;
        }

        .header {
            background: linear-gradient(135deg, #6a11cb, #2575fc, #00c6ff);
            padding: 30px;
            border-radius: 22px;
            color: white;
            box-shadow: 0 15px 35px rgba(0, 0, 0, .18);
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 34px;
            margin-bottom: 8px;
        }

        .header p {
            font-size: 17px;
            opacity: .95;
        }

        .role {
            padding: 15px 25px;
            border-radius: 50px;
            background: linear-gradient(135deg, #ff512f, #dd2476);
            font-size: 17px;
            font-weight: bold;
            box-shadow: 0 8px 20px rgba(0, 0, 0, .2);
        }

        .message {
            padding: 16px 20px;
            border-radius: 15px;
            margin-bottom: 25px;
            font-size: 17px;
            font-weight: bold;
            text-align: center;
        }

        .success {
            background: linear-gradient(135deg, #00b09b, #96c93d);
            color: white;
        }

        .error {
            background: linear-gradient(135deg, #ff416c, #ff4b2b);
            color: white;
        }

        .main {
            display: grid;
            grid-template-columns: 1.1fr .9fr;
            gap: 25px;
        }

        .form-card {
            background: rgba(255, 255, 255, .94);
            padding: 30px;
            border-radius: 22px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, .15);
        }

        .form-card h2 {
            color: #6a11cb;
            font-size: 28px;
            margin-bottom: 8px;
        }

        .form-card .sub {
            color: #64748b;
            font-size: 15px;
            margin-bottom: 25px;
        }

        .field {
            margin-bottom: 20px;
        }

        .field label {
            display: block;
            font-size: 16px;
            font-weight: bold;
            color: #334155;
            margin-bottom: 8px;
        }

        .current {
            width: 100%;
            padding: 15px;
            border-radius: 12px;
            background: linear-gradient(135deg, #ede9fe, #dbeafe);
            color: #6d28d9;
            font-size: 17px;
            font-weight: bold;
            border: 2px solid #c4b5fd;
        }

        select,
        textarea {
            width: 100%;
            border: 2px solid #cbd5e1;
            border-radius: 12px;
            padding: 14px;
            font-size: 16px;
            outline: none;
            background: white;
            transition: .3s;
        }

        select:focus,
        textarea:focus {
            border-color: #7c3aed;
            box-shadow: 0 0 0 4px rgba(124, 58, 237, .12);
        }

        textarea {
            min-height: 140px;
            resize: vertical;
        }

        button {
            width: 100%;
            border: 0;
            padding: 16px;
            border-radius: 13px;
            color: white;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            background: linear-gradient(90deg, #ff512f, #dd2476, #6a11cb, #2575fc);
            background-size: 300% 100%;
            transition: .4s;
        }

        button:hover {
            background-position: 100% 0;
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(106, 17, 203, .3);
        }

        .info-card {
            padding: 30px;
            border-radius: 22px;
            color: white;
            background: linear-gradient(145deg, #11998e, #38ef7d, #00c6ff, #0072ff);
            background-size: 250% 250%;
            animation: bg 8s ease infinite;
            box-shadow: 0 15px 35px rgba(0, 0, 0, .16);
        }

        .info-card h2 {
            font-size: 28px;
            margin-bottom: 25px;
        }

        .role-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 18px;
            margin-bottom: 15px;
            border-radius: 16px;
            background: rgba(255, 255, 255, .2);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, .3);
        }

        .icon {
            width: 55px;
            height: 55px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: white;
            color: #6a11cb;
            font-size: 22px;
            font-weight: bold;
        }

        .role-item h3 {
            font-size: 19px;
            margin-bottom: 4px;
        }

        .role-item p {
            font-size: 14px;
            opacity: .9;
        }

        .history {
            margin-top: 25px;
            background: rgba(255, 255, 255, .95);
            border-radius: 22px;
            padding: 30px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, .15);
        }

        .history h2 {
            color: #2575fc;
            font-size: 27px;
            margin-bottom: 20px;
        }

        .table-box {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 900px;
        }

        th {
            padding: 15px;
            text-align: left;
            color: white;
            font-size: 15px;
            background: linear-gradient(90deg, #6a11cb, #2575fc, #00c6ff);
        }

        td {
            padding: 14px 15px;
            border-bottom: 1px solid #e2e8f0;
            color: #334155;
            font-size: 14px;
            font-weight: 600;
        }

        tr:hover td {
            background: #f8f7ff;
        }

        .status {
            display: inline-block;
            padding: 7px 13px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: bold;
        }

        .status.pending {
            background: #fff3cd;
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
            padding: 30px;
            color: #64748b;
        }

        @media(max-width:800px) {
            body {
                padding: 15px;
            }

            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 20px;
            }

            .header h1 {
                font-size: 27px;
            }

            .main {
                grid-template-columns: 1fr;
            }

            .form-card,
            .info-card,
            .history {
                padding: 20px;
            }

        }
    </style>

</head>

<body>

    <div class="container">

        ```
        <div class="header">
            <div>
                <h1>Role Change Request</h1>
                <p>Request a new role from the system administrator</p>
            </div>
            <div class="role">
                <?php echo strtoupper(htmlspecialchars($current_role)); ?>
            </div>
        </div>

        <?php if ($message != ""): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="main">

            <div class="form-card">
                <h2>Send Request</h2>
                <p class="sub">Fill in the details below and send your request to admin.</p>

                <form method="POST" action="role_request.php" id="roleForm">

                    <div class="field">
                        <label>Current Role</label>
                        <div class="current">
                            <?php echo ucfirst(htmlspecialchars($current_role)); ?>
                        </div>
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

                    <button type="submit">Send Request to Admin</button>

                </form>
            </div>

            <div class="info-card">
                <h2>Available Roles</h2>

                <div class="role-item">
                    <div class="icon">P</div>
                    <div>
                        <h3>Passenger</h3>
                        <p>Search buses, book seats and manage tickets.</p>
                    </div>
                </div>

                <div class="role-item">
                    <div class="icon">D</div>
                    <div>
                        <h3>Driver</h3>
                        <p>Manage assigned bus and passenger trips.</p>
                    </div>
                </div>

                <div class="role-item">
                    <div class="icon">O</div>
                    <div>
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
                                    <td>
                                        <?php echo ucfirst(htmlspecialchars($row['requested_role'])); ?>
                                    </td>

                                    <td>
                                        <?php echo htmlspecialchars($row['reason']); ?>
                                    </td>

                                    <td>
                                        <span class="status <?php echo htmlspecialchars($row['status']); ?>">
                                            <?php echo ucfirst(htmlspecialchars($row['status'])); ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?php echo $row['admin_reason'] ? htmlspecialchars($row['admin_reason']) : "Waiting for admin"; ?>
                                    </td>

                                    <td>
                                        <?php echo htmlspecialchars($row['requested_at']); ?>
                                    </td>

                                    <td>
                                        <?php echo $row['reviewed_at'] ? htmlspecialchars($row['reviewed_at']) : "-"; ?>
                                    </td>
                                </tr>

                            <?php endwhile; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="6" class="empty">
                                    No role change requests yet.
                                </td>
                            </tr>

                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        ```

    </div>

    <script>
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