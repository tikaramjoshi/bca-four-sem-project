<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}
require_once "../db.php";

if (isset($_POST['approve_request'])) {
    $request_id = (int)$_POST['request_id'];

    $stmt = $conn->prepare("SELECT user_id,requested_role FROM role_change_requests WHERE request_id=? AND status='pending'");
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $request_result = $stmt->get_result();

    if ($request_result->num_rows > 0) {
        $request = $request_result->fetch_assoc();
        $user_id = (int)$request['user_id'];
        $requested_role = $request['requested_role'];

        $stmt = $conn->prepare("SELECT role FROM users WHERE user_id=?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user_result = $stmt->get_result();

        if ($user_result->num_rows > 0) {
            $user = $user_result->fetch_assoc();

            if ($user['role'] != 'admin') {
                $stmt = $conn->prepare("UPDATE users SET role=?,verification_status='pending' WHERE user_id=?");
                $stmt->bind_param("si", $requested_role, $user_id);
                $stmt->execute();

                $admin_reason = "Role change approved. Verification is pending.";

                $stmt = $conn->prepare("UPDATE role_change_requests SET status='approved',admin_reason=?,reviewed_at=NOW() WHERE request_id=?");
                $stmt->bind_param("si", $admin_reason, $request_id);
                $stmt->execute();

                $_SESSION['success'] = "Role change approved successfully";
            } else {
                $_SESSION['error'] = "Admin role cannot be changed";
            }
        }
    }

    header("Location: change_role.php");
    exit();
}

if (isset($_POST['reject_request'])) {
    $request_id = (int)$_POST['request_id'];
    $admin_reason = trim($_POST['admin_reason'] ?? '');

    if ($admin_reason == '') {
        $_SESSION['error'] = "Please enter reject reason";
    } else {
        $stmt = $conn->prepare("UPDATE role_change_requests SET status='rejected',admin_reason=?,reviewed_at=NOW() WHERE request_id=? AND status='pending'");
        $stmt->bind_param("si", $admin_reason, $request_id);
        $stmt->execute();

        $_SESSION['success'] = "Role change request rejected";
    }

    header("Location: change_role.php");
    exit();
}

$result = $conn->query("SELECT r.request_id,r.user_id,r.old_role,r.requested_role,r.reason,r.status,r.admin_reason,r.requested_at,r.reviewed_at,u.name,u.email,u.phone,u.profile_image,u.role,u.verification_status FROM role_change_requests r INNER JOIN users u ON r.user_id=u.user_id ORDER BY r.request_id DESC");

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';

unset($_SESSION['success']);
unset($_SESSION['error']);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change User Role</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f5f6fa;
        }

        .content {
            padding: 25px;
        }

        .container {
            width: 100%;
        }

        h3 {
            margin: 0 0 20px;
            font-size: 24px;
            color: #222;
        }

        .alert {
            padding: 12px 15px;
            margin-bottom: 20px;
            border-radius: 6px;
            font-size: 14px;
        }

        .alert-success {
            background: #d1e7dd;
            color: #0f5132;
            border: 1px solid #badbcc;
        }

        .alert-danger {
            background: #f8d7da;
            color: #842029;
            border: 1px solid #f5c2c7;
        }

        .table-box {
            width: 100%;
            overflow-x: auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, .08);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1300px;
        }

        th {
            background: #4413e5;
            color: white;
            padding: 13px 10px;
            text-align: left;
            font-size: 14px;
        }

        td {
            padding: 12px 10px;
            border-bottom: 1px solid #ddd;
            font-size: 14px;
            color: #333;
            vertical-align: middle;
        }

        tr:hover {
            background: #f8f8ff;
        }

        .profile {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 50%;
            border: 1px solid #ddd;
        }

        .admin-btn {
            padding: 8px 14px;
            border: 0;
            border-radius: 5px;
            background: #6c757d;
            color: white;
            cursor: not-allowed;
        }

        .status {
            padding: 6px 10px;
            border-radius: 5px;
            font-weight: bold;
            display: inline-block;
        }

        .pending {
            background: #fff3cd;
            color: #856404;
        }

        .approved {
            background: #d1e7dd;
            color: #0f5132;
        }

        .rejected {
            background: #f8d7da;
            color: #842029;
        }

        .approve-btn {
            padding: 8px 12px;
            border: 0;
            border-radius: 5px;
            background: #198754;
            color: white;
            cursor: pointer;
        }

        .reject-btn {
            padding: 8px 12px;
            border: 0;
            border-radius: 5px;
            background: #dc3545;
            color: white;
            cursor: pointer;
        }

        .approve-btn:hover {
            background: #157347;
        }

        .reject-btn:hover {
            background: #bb2d3b;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, .5);
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }

        .modal-box {
            width: 420px;
            max-width: 90%;
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, .2);
        }

        .modal-box h3 {
            margin: 0 0 20px;
            font-size: 22px;
        }

        .modal-box label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
        }

        .modal-box textarea {
            width: 100%;
            height: 120px;
            resize: none;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-family: Arial, sans-serif;
            font-size: 14px;
        }

        .modal-box textarea:focus {
            outline: none;
            border-color: #4413e5;
        }

        .modal-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .cancel-btn {
            padding: 8px 12px;
            border: 0;
            border-radius: 5px;
            background: #6c757d;
            color: white;
            cursor: pointer;
        }

        @media(max-width:768px) {
            .content {
                padding: 15px;
            }

            h3 {
                font-size: 20px;
            }
        }
    </style>
</head>

<body>
    <?php include "admin_header.php"; ?>

    <div class="content">
        <div class="container">
            <h3>Manage User Roles</h3>

            <?php if ($success) { ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php } ?>

            <?php if ($error) { ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php } ?>

            <div class="table-box">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Profile</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Old Role</th>
                            <th>Requested Role</th>
                            <th>Reason</th>
                            <th>Verification</th>
                            <th>Status / Action</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php while ($row = $result->fetch_assoc()) { ?>
                            <tr>
                                <td><?php echo $row['request_id']; ?></td>

                                <td>
                                    <?php
                                    $image = !empty($row['profile_image']) ? $row['profile_image'] : 'default.png';
                                    ?>
                                    <img class="profile" src="../uploads/<?php echo htmlspecialchars($image); ?>">
                                </td>

                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                <td><?php echo htmlspecialchars($row['email']); ?></td>
                                <td><?php echo htmlspecialchars($row['phone']); ?></td>
                                <td><?php echo htmlspecialchars($row['old_role']); ?></td>
                                <td><?php echo htmlspecialchars($row['requested_role']); ?></td>
                                <td><?php echo htmlspecialchars($row['reason']); ?></td>
                                <td><?php echo htmlspecialchars($row['verification_status'] ?? ''); ?></td>

                                <td>
                                    <?php
                                    if ($row['status'] == 'pending' && $row['role'] != 'admin') {
                                    ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="request_id" value="<?php echo $row['request_id']; ?>">
                                            <button type="submit" name="approve_request" class="approve-btn" onclick="return confirm('Approve this role request?')">Approve</button>
                                        </form>
                                        <button type="button" class="reject-btn" onclick="openRejectForm(<?php echo $row['request_id']; ?>)">Reject</button>
                                    <?php
                                    } elseif ($row['status'] == 'approved') {
                                    ?>
                                        <span class="status approved">Approved</span>
                                    <?php
                                    } elseif ($row['status'] == 'rejected') {
                                    ?>
                                        <span class="status rejected">Rejected</span>
                                    <?php
                                    }
                                    ?>
                                </td>

                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="rejectModal" class="modal">
        <div class="modal-box">
            <h3>Reject Role Request</h3>

            <form method="POST">
                <input type="hidden" name="request_id" id="rejectRequestId">

                <label>Reject Reason</label>
                <textarea name="admin_reason" id="adminReason" placeholder="Enter reject reason..." required></textarea>

                <div class="modal-buttons">
                    <button type="submit" name="reject_request" class="reject-btn">Reject Request</button>
                    <button type="button" class="cancel-btn" onclick="closeRejectForm()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openRejectForm(requestId) {
            document.getElementById("rejectRequestId").value = requestId;
            document.getElementById("rejectModal").style.display = "flex";
            document.getElementById("adminReason").focus();
        }

        function closeRejectForm() {
            document.getElementById("rejectModal").style.display = "none";
            document.getElementById("rejectRequestId").value = "";
            document.getElementById("adminReason").value = "";
        }

        document.getElementById("rejectModal").addEventListener("click", function(e) {
            if (e.target === this) {
                closeRejectForm();
            }
        });
    </script>
</body>

</html>