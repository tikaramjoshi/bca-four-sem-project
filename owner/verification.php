<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != "owner") {
    header("Location: ../login.php");
    exit();
}
require_once "../db.php";
$owner_id = (int)$_SESSION['user_id'];
$message = "";
$message_type = "";
$check = $conn->prepare("SELECT * FROM owner_verification WHERE owner_id = ?");
$check->bind_param("i", $owner_id);
$check->execute();
$result = $check->get_result();
$data = $result->fetch_assoc();
$check->close();
if (isset($_POST['submit'])) {
    $company_name = trim($_POST['company_name'] ?? '');
    $company_registration_no = trim($_POST['company_registration_no'] ?? '');
    $uploadDir = "../uploads/";
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    if ($company_name === '' || $company_registration_no === '') {
        $message = "Please fill all required fields.";
        $message_type = "error";
    } else {
        $owner_photo = $data['owner_photo'] ?? '';
        $company_certificate = $data['company_certificate'] ?? '';
        if (!empty($_FILES['owner_photo']['name']) && $_FILES['owner_photo']['error'] === UPLOAD_ERR_OK) {
            $allowedImage = ['jpg', 'jpeg', 'png', 'webp'];
            $extension = strtolower(pathinfo($_FILES['owner_photo']['name'], PATHINFO_EXTENSION));
            if (!in_array($extension, $allowedImage)) {
                $message = "Invalid owner photo format.";
                $message_type = "error";
            } else {
                $owner_photo = time() . "_owner_" . uniqid() . "." . $extension;
                move_uploaded_file($_FILES['owner_photo']['tmp_name'], $uploadDir . $owner_photo);
            }
        }
        if ($message === "" && !empty($_FILES['company_certificate']['name']) && $_FILES['company_certificate']['error'] === UPLOAD_ERR_OK) {
            $allowedCertificate = ['jpg', 'jpeg', 'png', 'pdf'];
            $extension = strtolower(pathinfo($_FILES['company_certificate']['name'], PATHINFO_EXTENSION));
            if (!in_array($extension, $allowedCertificate)) {
                $message = "Invalid certificate format.";
                $message_type = "error";
            } else {
                $company_certificate = time() . "_certificate_" . uniqid() . "." . $extension;
                move_uploaded_file($_FILES['company_certificate']['tmp_name'], $uploadDir . $company_certificate);
            }
        }
        if ($message === "") {
            if ($data) {
                if ($data['status'] === "rejected") {
                    $stmt = $conn->prepare("UPDATE owner_verification SET company_name=?, company_registration_no=?, owner_photo=?, company_certificate=?, status='pending', reject_reason=NULL WHERE owner_id=?");
                    $stmt->bind_param("ssssi", $company_name, $company_registration_no, $owner_photo, $company_certificate, $owner_id);
                    if ($stmt->execute()) {
                        $update = $conn->prepare("UPDATE users SET verification_status='pending' WHERE user_id=?");
                        $update->bind_param("i", $owner_id);
                        $update->execute();
                        $update->close();
                        $message = "Verification resubmitted successfully. Please wait for admin approval.";
                        $message_type = "success";
                        $data['status'] = 'pending';
                    } else {
                        $message = "Failed to resubmit verification.";
                        $message_type = "error";
                    }
                    $stmt->close();
                } else {
                    $message = "Verification already submitted.";
                    $message_type = "error";
                }
            } else {
                if ($owner_photo === '' || $company_certificate === '') {
                    $message = "Please upload all required documents.";
                    $message_type = "error";
                } else {
                    $insert = $conn->prepare("INSERT INTO owner_verification (owner_id, company_name, company_registration_no, owner_photo, company_certificate, status) VALUES (?, ?, ?, ?, ?, 'pending')");
                    $insert->bind_param("issss", $owner_id, $company_name, $company_registration_no, $owner_photo, $company_certificate);
                    if ($insert->execute()) {
                        $update = $conn->prepare("UPDATE users SET verification_status='pending' WHERE user_id=?");
                        $update->bind_param("i", $owner_id);
                        $update->execute();
                        $update->close();

                        $message = "Verification submitted successfully. Please wait for admin approval.";
                        $message_type = "success";
                        $data = [
                            'company_name' => $company_name,
                            'company_registration_no' => $company_registration_no,
                            'owner_photo' => $owner_photo,
                            'company_certificate' => $company_certificate,
                            'status' => 'pending'
                        ];
                    } else {
                        $message = "Something went wrong.";
                        $message_type = "error";
                    }
                    $insert->close();
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
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Company Verification</title>
    <link rel="stylesheet" href="owner.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, Helvetica, sans-serif
        }

        html,
        body {
            min-height: 100%;
            background: #f5f7fb
        }

        .main {
            background: #1560BD;
            padding: 10px 25px
        }

        nav {
            display: flex;
            justify-content: flex-start;
            align-items: center
        }

        nav a {
            text-decoration: none;
            color: #000;
            background: #a59aef;
            padding: 8px 15px;
            font-weight: bold;
            border-radius: 4px
        }

        nav a:hover {
            color: #fff;
            background: #0fa070
        }

        .last-policy {
            padding: 20px 40px;
            min-height: calc(100vh - 115px)
        }

        .verification-box {
            width: 500px;
            max-width: 95%;
            margin: 40px auto;
            background: #fff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, .15)
        }

        .verification-box h2 {
            text-align: center;
            margin: 0 0 25px;
            color: #1e3a8a
        }

        .verification-box label {
            display: block;
            margin-top: 15px;
            margin-bottom: 6px;
            font-weight: bold;
            color: #333
        }

        .verification-box input[type=text] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 6px;
            outline: none
        }

        .verification-box input[type=text]:focus {
            border-color: #1560BD
        }

        .verification-box input[type=file] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 6px;
            background: #fafafa
        }

        .verification-box button {
            width: 100%;
            padding: 14px;
            margin-top: 25px;
            border: none;
            border-radius: 6px;
            background: #2563eb;
            color: #fff;
            font-size: 17px;
            cursor: pointer;
            transition: .3s
        }

        .verification-box button:hover {
            background: #1d4ed8
        }

        .success {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            text-align: center
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            text-align: center
        }

        .current-file {
            margin: 15px 0;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 10px;
            background: #f8f9fa;
            text-align: center
        }

        .preview {
            width: 140px;
            height: 140px;
            object-fit: cover;
            border-radius: 10px;
            border: 2px solid #1560BD;
            display: block;
            margin: 0 auto 15px
        }

        .view-btn {
            display: inline-block;
            background: #1560BD;
            color: #fff;
            text-decoration: none;
            padding: 10px 18px;
            border-radius: 6px;
            margin-bottom: 15px
        }

        .view-btn:hover {
            background: #0d4e9e
        }

        .pdf-box {
            width: 140px;
            height: 140px;
            margin: 0 auto 15px;
            display: flex;
            justify-content: center;
            align-items: center;
            border: 2px solid #1560BD;
            border-radius: 10px;
            background: #eee;
            font-size: 18px;
            font-weight: bold
        }

        .last {
            background: #1560BD;
            color: #fff;
            text-align: center;
            padding: 15px 0;
            width: 100%
        }

        @media(max-width:600px) {
            .verification-box {
                width: 95%;
                padding: 20px
            }

            .last-policy {
                padding: 15px
            }
        }
    </style>
</head>

<body>
    <div class="main">
        <nav>
            <a href="dashboard.php">Home</a>
        </nav>
    </div>
    <div class="last-policy">
        <div class="verification-box">
            <h2>Company Verification</h2>
            <?php if ($message != ""): ?>
                <div class="<?= htmlspecialchars($message_type) ?>"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
            <?php if ($data && $data['status'] === 'rejected'): ?>
                <div class="error">
                    <strong>Your verification was rejected.</strong><br>
                    Reason: <?= htmlspecialchars($data['reject_reason'] ?? 'No reason provided') ?>
                </div>
            <?php endif; ?>
            <form method="POST" enctype="multipart/form-data">
                <label>Company Name</label>
                <input type="text" name="company_name" value="<?= htmlspecialchars($data['company_name'] ?? '') ?>" required>
                <label>Company Registration Number</label>
                <input type="text" name="company_registration_no" value="<?= htmlspecialchars($data['company_registration_no'] ?? '') ?>" required>
                <label>Owner Photo</label>
                <?php if (!empty($data['owner_photo'])): ?>
                    <div class="current-file">
                        <strong>Current Owner Photo</strong><br><br>
                        <img src="../uploads/<?= htmlspecialchars($data['owner_photo']) ?>" class="preview" alt="Owner Photo">
                        <a href="../uploads/<?= htmlspecialchars($data['owner_photo']) ?>" target="_blank" class="view-btn">View Photo</a><br>
                        <input type="file" name="owner_photo" accept="image/*">
                    </div>
                <?php else: ?>
                    <input type="file" name="owner_photo" accept="image/*" required>
                <?php endif; ?>
                <label>Company Registration Certificate</label>
                <?php if (!empty($data['company_certificate'])): ?>
                    <div class="current-file">
                        <strong>Current Certificate</strong><br><br>
                        <?php
                        $ext = strtolower(pathinfo($data['company_certificate'], PATHINFO_EXTENSION));
                        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])):
                        ?>
                            <img src="../uploads/<?= htmlspecialchars($data['company_certificate']) ?>" class="preview" alt="Certificate">
                        <?php else: ?>
                            <div class="pdf-box">PDF Certificate</div>
                        <?php endif; ?>
                        <a href="../uploads/<?= htmlspecialchars($data['company_certificate']) ?>" target="_blank" class="view-btn">View Certificate</a><br>
                        <input type="file" name="company_certificate" accept=".jpg,.jpeg,.png,.pdf">
                    </div>
                <?php else: ?>
                    <input type="file" name="company_certificate" accept=".jpg,.jpeg,.png,.pdf" required>
                <?php endif; ?>
                <button type="submit" name="submit">Submit Verification</button>
            </form>
        </div>
    </div>
    <footer class="last">
        <p>&copy;2026 Online Bus Ticket Booking System | All rights reserved.</p>
    </footer>
    <?php if ($message_type === "success"): ?>
        <script>
            setTimeout(function() {
                window.location.href = "dashboard.php";
            }, 3000);
        </script>
    <?php endif; ?>
</body>

</html>