<?php
session_start();
require_once "../db.php";

if (!isset($_SESSION['user_id'], $_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$user_id = (int)($_GET['id'] ?? 0);

if ($user_id <= 0) {
    header("Location: passengers.php");
    exit;
}

$stmt = $conn->prepare("SELECT user_id,name,email,phone,password,profile_image,role,created_at,verification_status FROM users WHERE user_id=? AND role='passenger'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    header("Location: passengers.php");
    exit;
}

$message = "";
$message_type = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $new_user_id = (int)($_POST['user_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $role = trim($_POST['role'] ?? '');
    $verification_status = trim($_POST['verification_status'] ?? '');
    $password = $_POST['password'] ?? '';
    $created_at = $_POST['created_at'] ?? '';
    $allowed_roles = ['admin', 'owner', 'driver', 'passenger'];
    $allowed_status = ['pending', 'verified', 'rejected'];

    if ($name === '' || $email === '' || $phone === '') {
        $message = "Name, email and phone are required.";
        $message_type = "error";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
        $message_type = "error";
    } elseif (!in_array($role, $allowed_roles, true)) {
        $message = "Invalid role selected.";
        $message_type = "error";
    } elseif (!in_array($verification_status, $allowed_status, true)) {
        $message = "Invalid verification status.";
        $message_type = "error";
    } else {
        $check = $conn->prepare("SELECT user_id FROM users WHERE email=? AND user_id!=?");
        $check->bind_param("si", $email, $user_id);
        $check->execute();
        $check_result = $check->get_result();

        if ($check_result->num_rows > 0) {
            $message = "This email is already used by another user.";
            $message_type = "error";
        } else {
            $profile_image = $user['profile_image'];

            if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                if ($_FILES['profile_image']['error'] !== UPLOAD_ERR_OK) {
                    $message = "Failed to upload profile image.";
                    $message_type = "error";
                } else {
                    $upload_dir = "../uploads/profile/";

                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }

                    $extension = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
                    $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];

                    if (!in_array($extension, $allowed_extensions, true)) {
                        $message = "Only JPG, JPEG, PNG and WEBP images are allowed.";
                        $message_type = "error";
                    } elseif ($_FILES['profile_image']['size'] > 5 * 1024 * 1024) {
                        $message = "Image size must be less than 5MB.";
                        $message_type = "error";
                    } else {
                        $new_image = "passenger_" . $user_id . "_" . time() . "." . $extension;

                        if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_dir . $new_image)) {
                            if ($profile_image && $profile_image !== 'default.png' && file_exists($upload_dir . $profile_image)) {
                                unlink($upload_dir . $profile_image);
                            }

                            $profile_image = $new_image;
                        } else {
                            $message = "Failed to save profile image.";
                            $message_type = "error";
                        }
                    }
                }
            }

            if ($message_type !== "error") {
                if ($password !== '') {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                    $update = $conn->prepare("UPDATE users SET name=?,email=?,phone=?,password=?,profile_image=?,role=?,verification_status=? WHERE user_id=?");
                    $update->bind_param(
                        "sssssssi",
                        $name,
                        $email,
                        $phone,
                        $hashed_password,
                        $profile_image,
                        $role,
                        $verification_status,
                        $user_id
                    );
                } else {
                    $update = $conn->prepare(" UPDATE users SET user_id=?,name=?,email=?,phone=?,password=?,profile_image=?,role=?,verification_status=?,created_at=? WHERE user_id=?");
                    $update->bind_param(
                        "ssssssi",
                        $name,
                        $email,
                        $phone,
                        $profile_image,
                        $role,
                        $verification_status,
                        $user_id
                    );
                }

                if ($update->execute()) {
                    header("Location: passengers.php?msg=updated");
                    exit;
                } else {
                    $message = "Failed to update user.";
                    $message_type = "error";
                }
            }
        }
    }

    $user['name'] = $name;
    $user['email'] = $email;
    $user['phone'] = $phone;
    $user['role'] = $role;
    $user['verification_status'] = $verification_status;
    $user['profile_image'] = $profile_image ?? $user['profile_image'];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Edit Passenger</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f4f7fb;
            min-height: 100vh;
        }

        .page {
            max-width: 900px;
            margin: 35px auto;
            padding: 20px;
        }

        .header {
            background: #fff;
            padding: 25px 30px;
            border-radius: 14px 14px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, .08);
        }

        .header h1 {
            color: #1d3557;
            font-size: 26px;
        }

        .header p {
            color: #777;
            margin-top: 6px;
        }

        .back-btn {
            text-decoration: none;
            background: #6c757d;
            color: #fff;
            padding: 10px 18px;
            border-radius: 7px;
        }

        .form-box {
            background: #fff;
            padding: 30px;
            border-radius: 0 0 14px 14px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, .08);
        }

        .message {
            padding: 13px 16px;
            margin-bottom: 20px;
            border-radius: 7px;
        }

        .error {
            background: #ffe5e5;
            color: #c62828;
        }

        .profile-section {
            text-align: center;
            margin-bottom: 30px;
        }

        .profile-section img {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #e8eef7;
            display: block;
            margin: 0 auto 15px;
        }

        .profile-section input {
            display: block;
            margin: auto;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-group {
            margin-bottom: 5px;
        }

        .form-group.full {
            grid-column: 1/-1;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #ccc;
            border-radius: 7px;
            font-size: 15px;
            outline: none;
            background: #fff;
        }

        .form-group input:focus,
        .form-group select:focus {
            border-color: #1560bd;
        }

        .password-note {
            font-size: 13px;
            color: #777;
            margin-top: 6px;
        }

        .readonly {
            background: #f1f3f5 !important;
        }

        .buttons {
            display: flex;
            gap: 12px;
            margin-top: 30px;
        }

        .update-btn {
            border: 0;
            background: #1560bd;
            color: #fff;
            padding: 12px 28px;
            border-radius: 7px;
            cursor: pointer;
            font-size: 15px;
        }

        .cancel-btn {
            text-decoration: none;
            background: #6c757d;
            color: #fff;
            padding: 12px 28px;
            border-radius: 7px;
        }

        @media(max-width:650px) {
            .page {
                margin: 15px auto;
                padding: 10px;
            }

            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-group.full {
                grid-column: auto;
            }
        }
    </style>
</head>

<body>
    <div class="page">
        <div class="header">
            <div>
                <h1>Edit Passenger</h1>
                <p>Update complete passenger account information</p>
            </div>
            <a href="passengers.php" class="back-btn">Back</a>
        </div>

        <div class="form-box">
            <?php if ($message): ?>
                <div class="message <?= htmlspecialchars($message_type) ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="profile-section">
                    <?php
                    $image = $user['profile_image'] ?: 'default.png';
                    ?>
                    <img src="../uploads/profile/<?= htmlspecialchars($image) ?>" onerror="this.onerror=null;this.src='../images/default.png';" alt="Profile">
                    <input type="file" name="profile_image" accept=".jpg,.jpeg,.png,.webp">
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>User ID</label>
                        <input type="number" name="user_id" value="<?= (int)$user['user_id'] ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Created Date</label>
                        <input type="datetime-local" name="created_at" value="<?= date('Y-m-d\TH:i', strtotime($user['created_at'])) ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Phone</label>
                        <input type="text" name="phone" value="<?= htmlspecialchars($user['phone']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Role</label>
                        <select name="role" required>
                            <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                            <option value="owner" <?= $user['role'] === 'owner' ? 'selected' : '' ?>>Owner</option>
                            <option value="driver" <?= $user['role'] === 'driver' ? 'selected' : '' ?>>Driver</option>
                            <option value="passenger" <?= $user['role'] === 'passenger' ? 'selected' : '' ?>>Passenger</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Verification Status</label>
                        <select name="verification_status" required>
                            <option value="pending" <?= $user['verification_status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="verified" <?= $user['verification_status'] === 'verified' ? 'selected' : '' ?>>Verified</option>
                            <option value="rejected" <?= $user['verification_status'] === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                        </select>
                    </div>

                    <div class="form-group full">
                        <label>New Password</label>
                        <input type="password" name="password" placeholder="Leave blank to keep current password">
                        <div class="password-note">Leave blank if you do not want to change the password.</div>
                    </div>
                </div>

                <div class="buttons">
                    <button type="submit" class="update-btn">Update Passenger</button>
                    <a href="passengers.php" class="cancel-btn">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</body>

</html>