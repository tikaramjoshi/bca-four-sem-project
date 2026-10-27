<?php
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'driver') {
    header("Location: ../login.php");
    exit();
}
require_once "../db.php";

$driver_id = (int)$_SESSION['user_id'];
$message = $message_type = "";

$upload_profile_dir = __DIR__ . "/../uploads/driver/profile/";
$upload_license_dir = __DIR__ . "/../uploads/driver/license/";
$profile_url_dir = "../uploads/driver/profile/";
$license_url_dir = "../uploads/driver/license/";

foreach ([$upload_profile_dir, $upload_license_dir] as $dir) {
    if (!is_dir($dir)) mkdir($dir, 0777, true);
}

$stmt = $conn->prepare("SELECT user_id,name,email,phone,profile_image FROM users WHERE user_id=? AND role='driver' LIMIT 1");
$stmt->bind_param("i", $driver_id);
$stmt->execute();
$driver = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$driver) {
    session_destroy();
    header("Location: ../login.php");
    exit();
}

function getDriverVerification($conn, $driver_id)
{
    $stmt = $conn->prepare("SELECT * FROM driver_verification WHERE driver_id=? ORDER BY verification_id DESC LIMIT 1");
    $stmt->bind_param("i", $driver_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $result;
}

function uploadDriverImage($field, $directory, $prefix, &$error)
{
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) return null;

    if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        $error = "Image upload failed. Please try again.";
        return false;
    }

    if ($_FILES[$field]['size'] > 10 * 1024 * 1024) {
        $error = "Each image must be smaller than 10 MB.";
        return false;
    }

    $tmp = $_FILES[$field]['tmp_name'];
    if (!is_uploaded_file($tmp)) {
        $error = "Invalid uploaded file.";
        return false;
    }

    $info = getimagesize($tmp);
    if ($info === false) {
        $error = "Please upload a valid image.";
        return false;
    }

    $types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($types[$info['mime'] ?? ''])) {
        $error = "Only JPG, PNG and WEBP images are allowed.";
        return false;
    }

    $filename = $prefix . "_" . time() . "_" . bin2hex(random_bytes(4)) . "." . $types[$info['mime']];
    if (!move_uploaded_file($tmp, $directory . $filename)) {
        $error = "Unable to save the uploaded image.";
        return false;
    }
    return $filename;
}

$verification = getDriverVerification($conn, $driver_id);
$status = strtolower($verification['status'] ?? 'unverified');
$license_number = $verification['license_number'] ?? '';
$issue_date = $verification['license_issue_date'] ?? '';
$expiry_date = $verification['license_expiry_date'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_verification'])) {
    $license_number = trim($_POST['license_number'] ?? '');
    $issue_date = trim($_POST['license_issue_date'] ?? '');
    $expiry_date = trim($_POST['license_expiry_date'] ?? '');
    $error = "";

    if ($verification && $status === 'pending') {
        $error = "Your verification is pending admin approval.";
    } elseif ($verification && $status === 'verified' && !isset($_GET['edit'])) {
        $error = "Please open Edit Verification Details first.";
    } elseif ($license_number === '' || $issue_date === '' || $expiry_date === '') {
        $error = "Please fill in all license details.";
    } elseif (
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $issue_date) ||
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiry_date) ||
        !checkdate((int)substr($issue_date, 5, 2), (int)substr($issue_date, 8, 2), (int)substr($issue_date, 0, 4)) ||
        !checkdate((int)substr($expiry_date, 5, 2), (int)substr($expiry_date, 8, 2), (int)substr($expiry_date, 0, 4))
    ) {
        $error = "Please enter valid license dates.";
    } elseif ($expiry_date < date('Y-m-d')) {
        $error = "Your license has expired. Please check the expiry date.";
    } elseif ($issue_date > date('Y-m-d')) {
        $error = "License issue date cannot be in the future.";
    } elseif ($issue_date > $expiry_date) {
        $error = "License issue date cannot be after the expiry date.";
    }

    $old_profile = $verification['profile_photo'] ?? '';
    $old_front = $verification['license_photo_front'] ?? '';
    $old_back = $verification['license_photo_back'] ?? '';
    $new_profile = $new_front = $new_back = null;

    if ($error === '') {
        $new_profile = uploadDriverImage('profile_photo', $upload_profile_dir, 'driver_profile_' . $driver_id, $error);
        if ($error === '' && $new_profile !== false) {
            $new_front = uploadDriverImage('license_photo_front', $upload_license_dir, 'license_front_' . $driver_id, $error);
        }
        if ($error === '' && $new_front !== false) {
            $new_back = uploadDriverImage('license_photo_back', $upload_license_dir, 'license_back_' . $driver_id, $error);
        }
    }

    if ($new_profile !== false && $new_front !== false && $new_back !== false && $error === '') {
        $profile_photo = $new_profile ?: $old_profile;
        $front_photo = $new_front ?: $old_front;
        $back_photo = $new_back ?: $old_back;

        if ($front_photo === '' || $back_photo === '') {
            $error = "Please upload both front and back license photos.";
        } elseif ($verification) {
            $verification_id = (int)$verification['verification_id'];
            $stmt = $conn->prepare("UPDATE driver_verification SET license_number=?,license_issue_date=?,license_expiry_date=?,profile_photo=?,license_photo_front=?,license_photo_back=?,status='pending',reject_reason=NULL WHERE verification_id=? AND driver_id=?");
            $stmt->bind_param("ssssssii", $license_number, $issue_date, $expiry_date, $profile_photo, $front_photo, $back_photo, $verification_id, $driver_id);

            if ($stmt->execute()) {
                $stmt->close();
                header("Location: driver_verification.php?success=1");
                exit();
            }
            $error = "Unable to save verification details. Please try again.";
            $stmt->close();
        } else {
            $stmt = $conn->prepare("INSERT INTO driver_verification (driver_id,license_number,license_issue_date,license_expiry_date,profile_photo,license_photo_front,license_photo_back,status) VALUES (?,?,?,?,?,?,?,'pending')");
            $stmt->bind_param("issssss", $driver_id, $license_number, $issue_date, $expiry_date, $profile_photo, $front_photo, $back_photo);

            if ($stmt->execute()) {
                $stmt->close();
                header("Location: driver_verification.php?success=1");
                exit();
            }
            $error = "Unable to save verification details. Please try again.";
            $stmt->close();
        }
    }

    if ($error !== '') {
        $message = $error;
        $message_type = "error";
    }
}

$verification = getDriverVerification($conn, $driver_id);
$status = strtolower($verification['status'] ?? 'unverified');

if (isset($_GET['success'])) {
    $message = "Verification details submitted successfully. Please wait for admin approval.";
    $message_type = "success";
}

$driver_image = $driver['profile_image'] ?? '';
$verification_image = $verification['profile_photo'] ?? '';
$verification_image_path = $upload_profile_dir . $verification_image;
$user_image_path = __DIR__ . "/../uploads/profile/" . $driver_image;

if ($verification_image !== '' && file_exists($verification_image_path)) {
    $display_image = $profile_url_dir . rawurlencode($verification_image);
} elseif ($driver_image !== '' && file_exists($user_image_path)) {
    $display_image = "../uploads/profile/" . rawurlencode($driver_image);
} else {
    $display_image = "../uploads/default.png";
}

$front = $verification['license_photo_front'] ?? '';
$back = $verification['license_photo_back'] ?? '';
$front_exists = $front !== '' && file_exists($upload_license_dir . $front);
$back_exists = $back !== '' && file_exists($upload_license_dir . $back);
$show_form = !$verification || $status === 'rejected' || ($status === 'verified' && isset($_GET['edit']));
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver Verification</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="driver_verification.css">
</head>

<body>
    <?php include "driver_header.php"; ?>

    <div class="container">
        <h1 class="page-title">Driver Verification</h1>

        <?php if ($message !== ''): ?>
            <div class="message <?= htmlspecialchars($message_type) ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <div class="section">
            <div class="driver-info">
                <img src="<?= htmlspecialchars($display_image) ?>" alt="Driver Profile" onerror="this.onerror=null;this.src='../uploads/default.png';">
                <h2>Name: <?= htmlspecialchars($driver['name']) ?></h2>
                <p>Email: <?= htmlspecialchars($driver['email'] ?? '') ?></p>
                <p>Phone: <?= htmlspecialchars($driver['phone'] ?? '') ?></p>
            </div>

            <div class="verification-status">
                <h2>Verification Status</h2>
                <span class="status <?= htmlspecialchars($status) ?>"><?= htmlspecialchars(ucfirst($status)) ?></span>

                <?php if ($status === 'pending'): ?>
                    <p>Your verification request is waiting for admin approval.</p>
                <?php elseif ($status === 'verified'): ?>
                    <p>Your driver verification has been approved.</p>
                <?php elseif ($status === 'rejected'): ?>
                    <p>Your verification request was rejected. Please review the reason and submit the details again.</p>
                <?php else: ?>
                    <p>Please submit your license information for verification.</p>
                <?php endif; ?>

                <?php if (!empty($verification['reject_reason'])): ?>
                    <p><strong>Reason:</strong> <?= htmlspecialchars($verification['reject_reason']) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($show_form): ?>
            <div class="section">
                <h2 class="section-title">Update Profile Photo</h2>
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-photo">
                        <label for="profile_photo">Update Profile Photo</label>
                        <?php if ($verification_image !== '' && file_exists($verification_image_path)): ?>
                            <img src="<?= htmlspecialchars($profile_url_dir . rawurlencode($verification_image)) ?>" class="preview" alt="Current Profile Photo">
                        <?php endif; ?>
                        <input type="file" id="profile_photo" name="profile_photo" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                    </div>

                    <h2 class="section-title">Update License Details</h2>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="license_number">License Number</label>
                            <input type="text" id="license_number" name="license_number" value="<?= htmlspecialchars($license_number) ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="license_issue_date">License Issue Date</label>
                            <input type="date" id="license_issue_date" name="license_issue_date" value="<?= htmlspecialchars($issue_date) ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="license_expiry_date">License Expiry Date</label>
                            <input type="date" id="license_expiry_date" name="license_expiry_date" value="<?= htmlspecialchars($expiry_date) ?>" required>
                        </div>
                    </div>

                    <div class="licen-photo-grid">
                        <div class="form-group">
                            <label for="license_photo_front">License Front Photo</label>
                            <p class="help-text">Upload a clear image of the front side. Maximum 10 MB.</p>
                            <?php if ($front_exists): ?>
                                <img src="<?= htmlspecialchars($license_url_dir . rawurlencode($front)) ?>" class="preview" alt="Current License Front">
                            <?php endif; ?>
                            <input type="file" id="license_photo_front" name="license_photo_front" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" <?= empty($verification['license_photo_front']) ? 'required' : '' ?>>
                        </div>

                        <div class="form-group">
                            <label for="license_photo_back">License Back Photo</label>
                            <p class="help-text">Upload a clear image of the back side. Maximum 10 MB.</p>
                            <?php if ($back_exists): ?>
                                <img src="<?= htmlspecialchars($license_url_dir . rawurlencode($back)) ?>" class="preview" alt="Current License Back">
                            <?php endif; ?>
                            <input type="file" id="license_photo_back" name="license_photo_back" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" <?= empty($verification['license_photo_back']) ? 'required' : '' ?>>
                        </div>
                    </div>
                    <button type="button" class="btn btn-close" onclick="window.location.href='driver_verification.php'"><i class="fa fa-arrow-left"></i> Close</button>
                    <button type="submit" name="submit_verification" class="btn"><i class="fa-solid fa-floppy-disk"></i> Update License Details </button>
                </form>
            </div>
        <?php elseif ($status === 'verified'): ?>
            <div class="section">
                <a href="driver_verification.php?edit=1" class="btn"><i class="fa-solid fa-pen"></i> Edit Verification Details</a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        const issueDate = document.getElementById('license_issue_date');
        const expiryDate = document.getElementById('license_expiry_date');

        if (issueDate && expiryDate) {
            issueDate.addEventListener('change', function() {
                expiryDate.min = this.value || '<?= date('Y-m-d') ?>';
            });
        }
    </script>
</body>

</html>