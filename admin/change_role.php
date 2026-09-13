<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}
require_once "../db.php";

if (isset($_POST['change_role'])) {
    $user_id = (int)$_POST['user_id'];
    $role = $_POST['role'];

    $allowed_roles = ['passenger', 'owner', 'driver'];

    if (in_array($role, $allowed_roles)) {
        $stmt = $conn->prepare("SELECT role FROM users WHERE user_id=?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();

            if ($user['role'] != 'admin') {
                $stmt = $conn->prepare("UPDATE users SET role=? WHERE user_id=?");
                $stmt->bind_param("si", $role, $user_id);
                $stmt->execute();
                $_SESSION['success'] = "User role changed successfully";
            } else {
                $_SESSION['error'] = "Admin role cannot be changed";
            }
        }
    }

    header("Location: change_role.php");
    exit();
}

$result = $conn->query("SELECT * FROM users ORDER BY user_id DESC");

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
            min-width: 1100px;
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

        .role-select {
            width: 130px;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 5px;
            background: white;
            font-size: 14px;
            cursor: pointer;
        }

        .role-select:focus {
            outline: none;
            border-color: #4413e5;
        }

        .admin-btn {
            padding: 8px 14px;
            border: 0;
            border-radius: 5px;
            background: #6c757d;
            color: white;
            cursor: not-allowed;
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
                            <th>Address</th>
                            <th>Role</th>
                            <th>Verification</th>
                            <th>Change Role</th>
                        </tr>
                    </thead>
                    <tbody>

                        <?php while ($row = $result->fetch_assoc()) { ?>

                            <tr>
                                <td><?php echo $row['user_id']; ?></td>

                                <td>
                                    <?php
                                    $image = !empty($row['profile_image']) ? $row['profile_image'] : 'default.png';
                                    ?>
                                    <img class="profile" src="../uploads/<?php echo htmlspecialchars($image); ?>">
                                </td>

                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                <td><?php echo htmlspecialchars($row['email']); ?></td>
                                <td><?php echo htmlspecialchars($row['phone']); ?></td>
                                <td><?php echo htmlspecialchars($row['address'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($row['role']); ?></td>
                                <td><?php echo htmlspecialchars($row['verification_status'] ?? ''); ?></td>

                                <td>
                                    <?php if ($row['role'] == 'admin') { ?>

                                        <button class="admin-btn" disabled>Admin</button>

                                    <?php } else { ?>

                                        <form method="POST" action="change_role.php">
                                            <input type="hidden" name="change_role" value="1">
                                            <input type="hidden" name="user_id" value="<?php echo $row['user_id']; ?>">

                                            <select name="role" class="role-select" onchange="this.form.submit()">
                                                <option value="passenger" <?php echo $row['role'] == 'passenger' ? 'selected' : ''; ?>>Passenger</option>
                                                <option value="owner" <?php echo $row['role'] == 'owner' ? 'selected' : ''; ?>>Owner</option>
                                                <option value="driver" <?php echo $row['role'] == 'driver' ? 'selected' : ''; ?>>Driver</option>
                                            </select>
                                        </form>

                                    <?php } ?>

                                </td>
                            </tr>
                        <?php } ?>

                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>

</html>