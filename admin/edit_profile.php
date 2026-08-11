<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != "admin") {
    header("Location: ../login.php");
    exit();
}
require_once "../db.php";
$user_id = $_SESSION['user_id'];
$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $image_name = "";

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

                move_uploaded_file(
                    $image['tmp_name'],
                    $target
                );
            } else {
                $stmt = $conn->prepare("SELECT profile_image FROM users WHERE user_id=?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $old = $stmt->get_result()->fetch_assoc();
                $image_name = $old['profile_image'];
            }

            $stmt = $conn->prepare("UPDATE users SET name=?, email=?, phone=?, profile_image=? WHERE user_id=?");
            $stmt->bind_param("ssssi", $name, $email, $phone, $image_name, $user_id);
            $stmt->execute();

            $_SESSION['name'] = $name;
            $message = "<div class='success'>Profile updated successfully.</div>";
        }
    }
}

$stmt = $conn->prepare("SELECT * FROM users WHERE user_id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
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
            background: #f4f7f6;
        }

        .header {
            background: #1560BD;
            color: white;
            padding: 18px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header a {
            text-decoration: none;
            background: red;
            color: white;
            padding: 10px 18px;
            border-radius: 5px;
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
            background: #0f8c63;
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
            margin: 5px 0 5px;
        }

        .profile-preview img {
            width: 100px;
            height: 100px;
            border-radius: 10px;
            object-fit: cover;
            border: 3px solid #1560BD;
        }
    </style>
</head>

<body>
    <div class="header">
        <h2><strong>Edit
                <?= htmlspecialchars($_SESSION['name']) ?>
                Profile </strong></h2>
        <a href="dashboard.php">Dashboard</a>
    </div>
    <div class="container">
        <div class="box">
            <h2>Update Profile</h2>
            <?php echo $message; ?>
            <form method="POST" enctype="multipart/form-data">
                <label>Full Name</label>
                <input type="text" name="name"
                    value="<?php echo htmlspecialchars($user['name']); ?>" required>
                <label>Email</label>
                <input type="email" name="email"
                    value="<?php echo htmlspecialchars($user['email']); ?>" required>
                <label>Phone</label>
                <input type="text" name="phone"
                    value="<?php echo htmlspecialchars($user['phone']); ?>" required>
                <label>Profile Picture</label>
                <div class="profile-preview">
                    <img src="../uploads/profile/admin/<?= htmlspecialchars($user['name']) ?>/<?= htmlspecialchars($user['profile_image']) ?>" alt="Profile">
                </div>
                <input type="file" name="profile_image" accept="image/*">
                <button type="submit">Update Profile</button>
            </form>
            <a class="back" href="dashboard.php"> Back to Home</a>
        </div>
    </div>
</body>

</html>