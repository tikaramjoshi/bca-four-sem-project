<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != "admin") {
    header("Location: ../login.php");
    exit();
}
require_once "../db.php";
$user_id = (int)$_SESSION['user_id'];
$message = "";
$image_uploaded = false;

$stmt = $conn->prepare("SELECT * FROM users WHERE user_id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
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
            $image_name = $user['profile_image'] ?? '';
            if (
                isset($_FILES['profile_image']) &&
                $_FILES['profile_image']['error'] === UPLOAD_ERR_OK
            ) {
                $image = $_FILES['profile_image'];
                $extension = strtolower(
                    pathinfo($image['name'], PATHINFO_EXTENSION)
                );
                $image_name = $user_id . "_" . time() . "." . $extension;
                $folder = "../uploads/profile/admin/" . $name . "/";

                if (!is_dir($folder)) {
                    mkdir($folder, 0777, true);
                }
                $target = $folder . $image_name;
                if (move_uploaded_file($image['tmp_name'], $target)) {
                    $image_uploaded = true;
                    $message = "<div class='success'>Image uploaded successfully.</div>";
                } else {
                    $message = "<div class='error'>Image upload failed.</div>";
                }
            }

            if ($image_uploaded) {
                $stmt = $conn->prepare(" UPDATE users SET name=?, email=?, phone=?, profile_image=?,     verification_status='verified' WHERE user_id=? ");

                $stmt->bind_param("ssssi", $name, $email, $phone, $image_name, $user_id);
            } else {
                $stmt = $conn->prepare("UPDATE users SET name=?, email=?, phone=? WHERE user_id=?");
                $stmt->bind_param("sssi", $name, $email, $phone, $user_id);
            }

            $stmt->execute();

            $_SESSION['name'] = $name;

            $user['name'] = $name;
            $user['email'] = $email;
            $user['phone'] = $phone;

            if ($image_uploaded) {
                $user['profile_image'] = $image_name;
            }

            if ($image_uploaded) {
                $message = "<div class='success'> Profile image uploaded and verified successfully. </div>";
            } else {
                $message = "<div class='success'> Profile updated successfully. </div>";
            }
            $image_name = $user['profile_image'] ?? '';
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
    <?php include "admin_header.php"; ?>
    <div class="container">
        <div class="box">
            <h2>Update Profile</h2>
            <?php echo $message; ?>
            <form method="POST" enctype="multipart/form-data">
                <label>Full Name</label>
                <input type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                <label>Email</label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                <label>Phone</label>
                <input type="text" name="phone" id="phone" maxlength="10" value="<?php echo htmlspecialchars($user['phone']); ?>" required>
                <label>Profile Picture</label>
                <div class="profile-preview">
                    <?php
                    if (!empty($user['profile_image'])) {
                        $preview_image = "../uploads/profile/admin/" .
                            $user['name'] . "/" .
                            $user['profile_image'];
                    } else {
                        $preview_image = "../uploads/profile/admin/default.png";
                    }
                    ?>
                    <img src="<?= htmlspecialchars($preview_image) ?>" alt="Profile">
                </div>
                <input type="file" name="profile_image" accept="image/*">
                <button type="submit"> <i class="fa fa-save"></i>&nbsp; Update Profile</button>
            </form>
            <a class="back" href="dashboard.php"> Back to Home</a>
            <a class="back" href="../changepassword.php"> Change Password</a>
        </div>
    </div>
    <script>
        const phone = document.getElementById("phone");
        phone.addEventListener("input", function() {
            this.value = this.value.replace(/[^0-9]/g, "").slice(0, 10);
        })
    </script>
</body>

</html>