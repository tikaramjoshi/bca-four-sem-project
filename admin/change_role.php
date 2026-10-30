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
$result = $conn->query("SELECT r.request_id,r.user_id,r.old_role,r.requested_role,r.reason,r.status,r.admin_reason,r.requested_at,r.reviewed_at,u.name,u.email,u.phone,u.profile_image,u.role,u.verification_status,dv.profile_photo,ov.owner_photo FROM role_change_requests r INNER JOIN users u ON r.user_id=u.user_id LEFT JOIN (SELECT driver_id,profile_photo FROM driver_verification WHERE verification_id IN (SELECT MAX(verification_id) FROM driver_verification GROUP BY driver_id)) dv ON dv.driver_id=u.user_id LEFT JOIN (SELECT owner_id,owner_photo FROM owner_verification WHERE verification_id IN (SELECT MAX(verification_id) FROM owner_verification GROUP BY owner_id)) ov ON ov.owner_id=u.user_id ORDER BY r.request_id DESC");
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
    <link rel="stylesheet" href="change_role.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
        .profile {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            object-fit: cover;
            display: block;
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
                                    $image = "../uploads/default.png";

                                    if ($row['role'] === 'owner' && !empty($row['owner_photo'])) {
                                        $candidate = "../uploads/profile/" . (int)$row['user_id'] . "/profile/" . basename($row['owner_photo']);
                                        if (file_exists($candidate)) {
                                            $image = $candidate;
                                        }
                                    } elseif ($row['role'] === 'driver' && !empty($row['profile_photo'])) {
                                        $candidate = "../uploads/driver/profile/" . basename($row['profile_photo']);
                                        if (file_exists($candidate)) {
                                            $image = $candidate;
                                        }
                                    } elseif ($row['role'] === 'passenger' && !empty($row['profile_image'])) {
                                        $candidate = "../uploads/profile/passenger/" . $row['name'] . "/" . basename($row['profile_image']);
                                        if (file_exists($candidate)) {
                                            $image = $candidate;
                                        }
                                    } elseif (!empty($row['profile_image'])) {
                                        $candidate = "../uploads/profile/" . basename($row['profile_image']);
                                        if (file_exists($candidate)) {
                                            $image = $candidate;
                                        }
                                    }
                                    ?>

                                    <img class="profile" src="<?= htmlspecialchars($image) ?>" alt="Profile" onerror="this.onerror=null;this.src='../uploads/default.png';">
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
                                            <button type="submit" name="approve_request" class="status approved" onclick="return confirm('Approve this role request?')"><i class="fa fa-check"></i>&nbsp; Approve</button>
                                        </form>
                                        <button type="button" class="status rejected" onclick="openRejectForm(<?php echo $row['request_id']; ?>)"><i class="fa fa-trash"></i>&nbsp; Reject</button>
                                    <?php
                                    } elseif ($row['status'] == 'approved') {
                                    ?>
                                        <span class="status approved" style="background-color: #3d383884;"><i class="fa fa-check"></i>&nbsp; Approved</span>
                                    <?php
                                    } elseif ($row['status'] == 'rejected') {
                                    ?>
                                        <span class="status rejected" style="background-color: #3d383884;"><i class="fa fa-trash"></i>&nbsp; Rejected</button>
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
                    <button type="submit" name="reject_request" class="reject-btn"><i class="fa fa-trash"></i>&nbsp; Reject Request</button>
                    <button type="button" class="cancel-btn" onclick="closeRejectForm()"><i class="fa fa-close"></i>&nbsp; Cancel</button>
                </div>
            </form>
        </div>
    </div>
    <div id="reasonModal" class="reason-modal">
        <div class="reason-box">
            <h3>Reject Reason</h3>
            <div class="reason-text" id="reasonText"></div>
            <div class="modal-buttons">
                <button type="button" class="cancel-btn" onclick="closeReason()"><i class="fa fa-close"></i>&nbsp; Close </button>
            </div>
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

        function viewRejectReason(reason) {
            document.getElementById("reasonText").textContent = reason;
            document.getElementById("reasonModal").style.display = "flex";
        }

        function closeReason() {
            document.getElementById("reasonModal").style.display = "none";
        }
    </script>
</body>

</html>