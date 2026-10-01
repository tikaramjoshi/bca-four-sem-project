<?php
session_start();
require_once "../db.php";
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'driver') {
    header("Location: ../login.php");
    exit;
}
$id = (int)$_SESSION['user_id'];
$message = "";
$stmt = $conn->prepare("SELECT name,email,phone,profile_image FROM users WHERE user_id=? AND role='driver' LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$driver = $stmt->get_result()->fetch_assoc();
if (!$driver) {
    header("Location: dashboard.php");
    exit;
}
$stmt = $conn->prepare("SELECT verification_id,license_number,license_issue_date,license_expiry_date,profile_photo,license_photo_front,license_photo_back,status FROM driver_verification WHERE driver_id=? ORDER BY verification_id DESC LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$verification = $stmt->get_result()->fetch_assoc();
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $license_number = trim($_POST['license_number'] ?? '');
    $license_issue_date = $_POST['license_issue_date'] ?? '';
    $license_expiry_date = $_POST['license_expiry_date'] ?? '';
    if ($name === '' || $email === '' || $phone === '' || $license_number === '' || $license_issue_date === '' || $license_expiry_date === '') {
        $message = "Please fill all fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email.";
    } else {
        $profile_photo = $verification['profile_photo'] ?? '';
        $license_photo_front = $verification['license_photo_front'] ?? '';
        $license_photo_back = $verification['license_photo_back'] ?? '';
        $license_changed = false;
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        if (!empty($_FILES['profile_photo']['name'])) {
            $ext = strtolower(pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, $allowed)) {
                $profile_photo = "profile_photo_" . $id . "_" . time() . "." . $ext;
                if (!is_dir("../uploads/driver/profile/")) {
                    mkdir("../uploads/driver/profile/", 0777, true);
                }
                move_uploaded_file($_FILES['profile_photo']['tmp_name'], "../uploads/driver/profile/" . $profile_photo);
            }
        }
        if (!empty($_FILES['license_photo_front']['name'])) {
            $ext = strtolower(pathinfo($_FILES['license_photo_front']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, $allowed)) {
                $license_photo_front = "license_photo_front_" . $id . "_" . time() . "." . $ext;
                if (!is_dir("../uploads/driver/license/")) {
                    mkdir("../uploads/driver/license/", 0777, true);
                }
                move_uploaded_file($_FILES['license_photo_front']['tmp_name'], "../uploads/driver/license/" . $license_photo_front);
                $license_changed = true;
            }
        }
        if (!empty($_FILES['license_photo_back']['name'])) {
            $ext = strtolower(pathinfo($_FILES['license_photo_back']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, $allowed)) {
                $license_photo_back = "license_photo_back_" . $id . "_" . time() . "." . $ext;
                if (!is_dir("../uploads/driver/license/")) {
                    mkdir("../uploads/driver/license/", 0777, true);
                }
                move_uploaded_file($_FILES['license_photo_back']['tmp_name'], "../uploads/driver/license/" . $license_photo_back);
                $license_changed = true;
            }
        }
        if (
            !$verification ||
            $license_number !== ($verification['license_number'] ?? '') ||
            $license_issue_date !== ($verification['license_issue_date'] ?? '') ||
            $license_expiry_date !== ($verification['license_expiry_date'] ?? '')
        ) {
            $license_changed = true;
        }
        $stmt = $conn->prepare("UPDATE users SET name=?,email=?,phone=? WHERE user_id=? AND role='driver'");
        $stmt->bind_param("sssi", $name, $email, $phone, $id);
        if ($stmt->execute()) {
            if ($verification) {
                if ($license_changed) {
                    $status = "pending";
                    $stmt = $conn->prepare("UPDATE driver_verification SET license_number=?,license_issue_date=?,license_expiry_date=?,profile_photo=?,license_photo_front=?,license_photo_back=?,status=?,updated_at=NOW() WHERE verification_id=? AND driver_id=?");
                    $stmt->bind_param("sssssssii", $license_number, $license_issue_date, $license_expiry_date, $profile_photo, $license_photo_front, $license_photo_back, $status, $verification['verification_id'], $id);
                    $stmt->execute();
                    $stmt = $conn->prepare("UPDATE users SET verification_status='pending' WHERE user_id=? AND role='driver'");
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                } else {
                    $stmt = $conn->prepare("UPDATE driver_verification SET profile_photo=? WHERE verification_id=? AND driver_id=?");
                    $stmt->bind_param("sii", $profile_photo, $verification['verification_id'], $id);
                    $stmt->execute();
                }
            } else {
                $status = "pending";
                $stmt = $conn->prepare("INSERT INTO driver_verification (driver_id,license_number,license_issue_date,license_expiry_date,profile_photo,license_photo_front,license_photo_back,status) VALUES (?,?,?,?,?,?,?,?)");
                $stmt->bind_param("isssssss", $id, $license_number, $license_issue_date, $license_expiry_date, $profile_photo, $license_photo_front, $license_photo_back, $status);
                $stmt->execute();
                $stmt = $conn->prepare("UPDATE users SET verification_status='pending' WHERE user_id=? AND role='driver'");
                $stmt->bind_param("i", $id);
                $stmt->execute();
            }
            header("Location: profile.php");
            exit;
        } else {
            $message = "Failed to update profile.";
        }
    }
}
$name = $driver['name'];
$email = $driver['email'];
$phone = $driver['phone'];
$license_number = $verification['license_number'] ?? '';
$license_issue_date = $verification['license_issue_date'] ?? '';
$license_expiry_date = $verification['license_expiry_date'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Edit Driver Profile</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .edit-profile {
            width: 90%;
            max-width: 900px;
            margin: 40px auto;
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            box-sizing: border-box;
        }

        .edit-profile h2 {
            text-align: center;
            color: #1560bd;
            margin: 0 0 25px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full {
            grid-column: 1 / 3;
        }

        .form-group label {
            color: #555;
            font-size: 14px;
            margin-bottom: 6px;
        }

        .form-group input {
            padding: 11px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            box-sizing: border-box;
        }

        .form-group input:focus {
            outline: none;
            border-color: #1560bd;
        }

        .buttons {
            margin-top: 20px;
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 6px;
            border: none;
            text-decoration: none;
            font-size: 14px;
            cursor: pointer;
        }

        .save-btn {
            background: #1560bd;
            color: #fff;
        }

        .save-btn:hover {
            background: #0d4d9c;
        }

        .back-btn {
            background: #777;
            color: #fff;
        }

        .back-btn:hover {
            background: #555;
        }

        .message {
            padding: 10px;
            margin-bottom: 15px;
            background: #ffe5e5;
            color: #c00;
            border-radius: 6px;
        }

        @media (max-width: 600px) {
            .edit-profile {
                width: 95%;
                padding: 20px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-group.full {
                grid-column: 1;
            }

            .buttons {
                flex-direction: column;
            }
        }
    </style>
</head>

<body>
    <?php include "dri_header.php"; ?>
    <div class="container">
        <div class="edit-profile">
            <h2>Edit Driver Profile</h2>
            <?php if ($message): ?>
                <div class="message"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
            <form method="POST" enctype="multipart/form-data">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" name="name" value="<?= htmlspecialchars($name) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($email) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="text" name="phone" value="<?= htmlspecialchars($phone) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Profile Photo</label>
                        <input type="file" name="profile_photo" accept="image/*">
                    </div>
                    <div class="form-group full">
                        <label>License Number</label>
                        <input type="text" name="license_number" value="<?= htmlspecialchars($license_number) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Issue Date</label>
                        <input type="date" name="license_issue_date" value="<?= htmlspecialchars($license_issue_date) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Expiry Date</label>
                        <input type="date" name="license_expiry_date" value="<?= htmlspecialchars($license_expiry_date) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>License Front</label>
                        <input type="file" name="license_photo_front" accept="image/*">
                    </div>
                    <div class="form-group">
                        <label>License Back</label>
                        <input type="file" name="license_photo_back" accept="image/*">
                    </div>
                </div>
                <div class="buttons">
                    <button type="submit" class="btn save-btn">Save Changes</button>
                    <a href="profile.php" class="btn back-btn">Back</a>
                </div>
            </form>
        </div>
    </div>
</body>

</html>