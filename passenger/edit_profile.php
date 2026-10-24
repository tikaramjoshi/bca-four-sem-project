<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != "passenger") {
    header("Location: ../login.php");
    exit();
}
require_once "../db.php";

include "pass_header.php";
$user_id = $_SESSION['user_id'];
$message = "";
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id=? AND role='passenger'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
if (!$user) {
    session_destroy();
    header("Location: ../login.php");
    exit();
}
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $image_name = $user['profile_image'] ?? '';
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email=? AND user_id!=?");
    $stmt->bind_param("si", $email, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $message = "<div class='error'>Email already exists.</div>";
    } else {
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE phone=? AND user_id!=?");
        $stmt->bind_param("si", $phone, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $message = "<div class='error'>Phone number already exists.</div>";
        } else {
            if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
                $image = $_FILES['profile_image'];
                $extension = strtolower(pathinfo($image['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                if (!in_array($extension, $allowed)) {
                    $message = "<div class='error'>Invalid image format.</div>";
                } else {
                    $image_name = $user_id . "_" . time() . "." . $extension;
                    $folder = "../uploads/profile/passenger/" . $user['name'] . "/";
                    if (!is_dir($folder)) {
                        mkdir($folder, 0777, true);
                    }
                    $target = $folder . $image_name;
                    if (move_uploaded_file($image['tmp_name'], $target)) {
                        $old_image = $user['profile_image'] ?? '';
                        $old_folder = "../uploads/profile/passenger/" . $user['name'] . "/";
                        if (!empty($old_image) && $old_image != $image_name && file_exists($old_folder . $old_image)) {
                            unlink($old_folder . $old_image);
                        }
                    } else {
                        $message = "<div class='error'>Profile image upload failed.</div>";
                    }
                }
            }
            if (empty($message)) {
                $stmt = $conn->prepare("UPDATE users SET name=?, email=?, phone=?, profile_image=? WHERE user_id=? AND role='passenger'");
                $stmt->bind_param("ssssi", $name, $email, $phone, $image_name, $user_id);
                if ($stmt->execute()) {
                    $_SESSION['name'] = $name;
                    $message = "<div class='success'>Profile updated successfully.</div>";
                    $stmt = $conn->prepare("SELECT * FROM users WHERE user_id=? AND role='passenger'");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $user = $result->fetch_assoc();
                } else {
                    $message = "<div class='error'>Profile update failed.</div>";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile</title>
    <link rel="stylesheet" href="edit_profile.css">
</head>

<body>

    <div class="container">
        <div class="box">
            <h2>Update Profile</h2>
            <?php echo $message; ?>
            <div class="status <?= strtolower($user['verification_status'] ?? 'pending') ?>">
                Verification Status: <?= htmlspecialchars(ucfirst($user['verification_status'] ?? 'pending')) ?>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <label>Full Name</label>
                <input type="text" name="name" value="<?= htmlspecialchars($user['name'] ?? '') ?>" required>
                <label>Email</label>
                <input type="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
                <label>Phone</label>
                <input type="text" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" required>
                <label>Profile Picture</label>
                <div class="profile-preview">
                    <?php
                    $image = !empty($user['profile_image']) ? $user['profile_image'] : 'default.png';

                    $image_path = "../uploads/profile/passenger/" . $user['name'] . "/" . $image;
                    if (!file_exists($image_path)) {
                        $folders = glob("../uploads/profile/passenger/*/" . basename($image));
                        if (!empty($folders)) {
                            $image_path = $folders[0];
                        } else {
                            $image_path = "../uploads/default.png";
                        }
                    }
                    ?>
                    <img src="<?= htmlspecialchars($image_path) ?>" alt="Profile">
                </div>
                <input type="file" name="profile_image" accept=".jpg,.jpeg,.png,.webp,image/*">
                <button type="submit"><i class="fa fa-upload"></i> Update Profile</button>
            </form>
            <a class="back" href="profile.php"> <i class="fa fa-arrow-left"></i> Back to Profile</a>
        </div>
    </div>
    <?php include "pass_footer.php" ?>
</body>

</html>