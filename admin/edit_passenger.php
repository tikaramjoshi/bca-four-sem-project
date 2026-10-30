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
$stmt = $conn->prepare("SELECT user_id,name,email,phone,profile_image,role,created_at,verification_status FROM users WHERE user_id=? AND role='passenger'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();
if (!$user) {
    header("Location: passengers.php");
    exit;
}
$message = "";
$message_type = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $role = trim($_POST['role'] ?? '');
    $verification_status = trim($_POST['verification_status'] ?? '');

    $allowed_roles = ['owner', 'driver', 'passenger'];
    $allowed_status = ['pending', 'verified', 'rejected'];
    $profile_image = $user['profile_image'] ?: 'default.png';

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
    }

    if ($message_type !== "error") {
        $check = $conn->prepare("SELECT user_id FROM users WHERE email=? AND user_id!=?");
        $check->bind_param("si", $email, $user_id);
        $check->execute();
        $check_result = $check->get_result();

        if ($check_result->num_rows > 0) {
            $message = "This email is already used by another user.";
            $message_type = "error";
        }
        $check->close();
    }

    if ($message_type !== "error") {
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['profile_image']['error'] !== UPLOAD_ERR_OK) {
                $message = "Failed to upload profile image.";
                $message_type = "error";
            } else {
                $upload_dir = "../uploads/profile/passenger/" . $name . "/";

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
                    $new_image = $user_id . "_" . time() . "." . $extension;
                    if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_dir . $new_image)) {
                        $profile_image = $new_image;
                    } else {
                        $message = "Failed to save profile image.";
                        $message_type = "error";
                    }
                }
            }
        }
    }

    if ($message_type !== "error") {
        $update = $conn->prepare("UPDATE users SET name=?,email=?,phone=?,profile_image=?,role=?,verification_status=? WHERE user_id=?");
        $update->bind_param("ssssssi", $name, $email, $phone, $profile_image, $role, $verification_status, $user_id);

        if ($update->execute()) {
            $update->close();
            header("Location: passengers.php?msg=updated");
            exit;
        } else {
            $message = "Failed to update user: " . $update->error;
            $message_type = "error";
            $update->close();
        }
    }

    $user['name'] = $name;
    $user['email'] = $email;
    $user['phone'] = $phone;
    $user['role'] = $role;
    $user['verification_status'] = $verification_status;
    $user['profile_image'] = $profile_image;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Edit Passenger</title>
    <link rel="stylesheet" href="edit_passenger.css">
</head>

<body>
    <?php include "admin_header.php" ?>
    <div class="container">
        <div class="form-box">
            <div>
                <h1>Edit Passenger</h1>
                <p>Update complete passenger account information</p>
            </div>
            <?php if ($message): ?>
                <div class="message <?= htmlspecialchars($message_type) ?>"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
            <form method="POST" enctype="multipart/form-data">
                <div class="profile-section">
                    <?php
                    $image = !empty($user['profile_image']) ? $user['profile_image'] : 'default.png';
                    $image_path = "../uploads/profile/passenger/" . $user['name'] . "/" . $image;
                    ?>

                    <img src="<?= htmlspecialchars($image_path) ?>"
                        onerror="this.onerror=null;this.src='../uploads/default.png';"
                        alt="Passenger Profile">
                    <input type="file" name="profile_image" accept=".jpg,.jpeg,.png,.webp">
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>User ID</label>
                        <input type="text" value="<?= (int)$user['user_id'] ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label>Created Date</label>
                        <input type="text" value="<?= htmlspecialchars(date('Y-m-d h:i A', strtotime($user['created_at']))) ?>" readonly>
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
                </div>
                <div class="buttons">
                    <button type="submit" class="update-btn"><i class="fa fa-save"></i>&nbsp; Update Passenger</button>
                    <a href="passengers.php" class="cancel-btn"><i class="fa fa-close"></i>&nbsp; Cancel</a>
                </div>
            </form>
        </div>
    </div>
</body>

</html>