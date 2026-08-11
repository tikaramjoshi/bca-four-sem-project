<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != "passenger") {
    header("Location: ../login.php");
    exit();
}
require_once "../db.php";
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
                    $folder = "../uploads/profile/passenger/" . $name . "/";
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
$profile_image = !empty($user['profile_image']) ? $user['profile_image'] : 'default.png';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        body {
            background: #eef4fb;
        }

        .header {
            background: #1560BD;
            color: white;
            padding: 18px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h2 {
            font-size: 20px;
        }

        .header a {
            text-decoration: none;
            background: white;
            color: #1560BD;
            padding: 10px 18px;
            border-radius: 5px;
            font-weight: bold;
        }

        .container {
            width: 500px;
            max-width: 95%;
            margin: 40px auto;
        }

        .box {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, .15);
        }

        .box h2 {
            text-align: center;
            color: #1560BD;
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-top: 15px;
            margin-bottom: 5px;
            font-weight: bold;
        }

        input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 15px;
        }

        input:focus {
            outline: none;
            border-color: #1560BD;
        }

        button {
            width: 100%;
            padding: 12px;
            margin-top: 20px;
            border: none;
            border-radius: 5px;
            background: #1560BD;
            color: white;
            font-size: 16px;
            cursor: pointer;
        }

        button:hover {
            background: #0d47a1;
        }

        .success {
            background: #d4edda;
            color: green;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 5px;
            text-align: center;
        }

        .error {
            background: #f8d7da;
            color: red;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 5px;
            text-align: center;
        }

        .back {
            display: block;
            text-align: center;
            margin-top: 15px;
            text-decoration: none;
            color: #1560BD;
            font-weight: bold;
        }

        .profile-preview {
            text-align: center;
            margin: 10px 0;
        }

        .profile-preview img {
            width: 100px;
            height: 100px;
            border-radius: 20px;
            object-fit: cover;
            border: 3px solid #1560BD;
        }

        .status {
            text-align: center;
            margin: 15px 0;
            font-weight: bold;
        }

        .pending {
            color: #f39c12;
        }

        .verified {
            color: #28a745;
        }

        .rejected {
            color: #dc3545;
        }
    </style>
</head>

<body>
    <div class="header">
        <h2>Edit Profile</h2>
        <a href="dashboard.php">Dashboard</a>
    </div>
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
                    <img src="../uploads/profile/passenger/<?= htmlspecialchars($user['name'] ?? '') ?>/<?= htmlspecialchars($profile_image) ?>" alt="Profile">
                </div>
                <input type="file" name="profile_image" accept=".jpg,.jpeg,.png,.webp,image/*">
                <button type="submit">Update Profile</button>
            </form>
            <a class="back" href="profile.php">Back to Profile</a>
        </div>
    </div>
</body>

</html>