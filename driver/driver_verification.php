<?php
session_start();
require_once "../db.php";
if (!isset($_SESSION['user_id'], $_SESSION['role']) || $_SESSION['role'] !== 'driver') {
    header("Location: ../login.php");
    exit;
}
$driver_id = (int)$_SESSION['user_id'];
$stmt = $conn->prepare("SELECT user_id,name,email,phone,profile_image FROM users WHERE user_id=? AND role='driver' LIMIT 1");
$stmt->bind_param("i", $driver_id);
$stmt->execute();
$result = $stmt->get_result();
if (!$result->num_rows) die("Driver account not found.");
$driver = $result->fetch_assoc();

$stmt = $conn->prepare("SELECT verification_id,license_number,license_issue_date,license_expiry_date,profile_photo,license_photo_front,license_photo_back,status,reject_reason FROM driver_verification WHERE driver_id=? ORDER BY verification_id DESC LIMIT 1");
$stmt->bind_param("i", $driver_id);
$stmt->execute();
$result = $stmt->get_result();
$verification = $result->num_rows ? $result->fetch_assoc() : null;

$error = $success = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $license_number = trim($_POST["license_number"] ?? "");
    $license_issue_date = trim($_POST["license_issue_date"] ?? "");
    $license_expiry_date = trim($_POST["license_expiry_date"] ?? "");
    if (!$license_number || !$license_issue_date || !$license_expiry_date) $error = "Please fill in all license information.";
    elseif ($license_expiry_date <= $license_issue_date) $error = "License expiry date must be after issue date.";

    $profile_photo = $verification["profile_photo"] ?? null;
    $license_photo_front = $verification["license_photo_front"] ?? null;
    $license_photo_back = $verification["license_photo_back"] ?? null;
    $allowed = ["jpg", "jpeg", "png", "webp"];

    foreach (["profile_photo", "license_photo_front", "license_photo_back"] as $field) {
        if ($error) break;
        if (!isset($_FILES[$field]) || $_FILES[$field]["error"] === UPLOAD_ERR_NO_FILE) {
            if ($field === "license_photo_front" && !$license_photo_front) $error = "Front license photo is required.";
            continue;
        }
        if ($_FILES[$field]["error"] !== UPLOAD_ERR_OK) {
            $error = ucwords(str_replace("_", " ", $field)) . " upload failed.";
            break;
        }
        $ext = strtolower(pathinfo($_FILES[$field]["name"], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed, true)) {
            $error = "Only JPG, JPEG, PNG and WEBP files are allowed.";
            break;
        }
        if ($_FILES[$field]["size"] > 5 * 1024 * 1024) {
            $error = ucwords(str_replace("_", " ", $field)) . " must be less than 5MB.";
            break;
        }

        $type = $field === "profile_photo" ? "profile" : "license";
        $folder = "../uploads/driver/$type/";
        if (!is_dir($folder)) mkdir($folder, 0777, true);
        $name = $field . "_" . $driver_id . "_" . time() . "." . $ext;
        if (!move_uploaded_file($_FILES[$field]["tmp_name"], $folder . $name)) {
            $error = "Unable to save " . str_replace("_", " ", $field) . ".";
            break;
        }
        if ($field === "profile_photo") $profile_photo = $name;
        elseif ($field === "license_photo_front") $license_photo_front = $name;
        else $license_photo_back = $name;
    }

    if (!$error) {
        $insert = $conn->prepare("INSERT INTO driver_verification(driver_id,license_number,license_issue_date,license_expiry_date,profile_photo,license_photo_front,license_photo_back,status) VALUES(?,?,?,?,?,?,?,'pending')");
        $insert->bind_param("issssss", $driver_id, $license_number, $license_issue_date, $license_expiry_date, $profile_photo, $license_photo_front, $license_photo_back);
        if ($insert->execute()) {
            $success = "Verification request submitted successfully. Please wait for admin approval.";
            $verification = ["verification_id" => $insert->insert_id, "license_number" => $license_number, "license_issue_date" => $license_issue_date, "license_expiry_date" => $license_expiry_date, "profile_photo" => $profile_photo, "license_photo_front" => $license_photo_front, "license_photo_back" => $license_photo_back, "status" => "pending", "reject_reason" => null];
        } else $error = "Database error: " . $insert->error;
    }
}
$status = $verification["status"] ?? "unverified";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Driver Verification</title>
    <link rel="stylesheet" href="driver_verification.css">
</head>

<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>Driver Verification</h1>
                <p>Submit your driving license information for verification.</p>
            </div>
            <a href="dashboard.php" class="back-btn">← Dashboard</a>
        </div>
        <div class="card">
            <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

            <div class="status <?= htmlspecialchars($status) ?>">
                <?php if ($status === "verified"): ?>
                    <h3>✓ Driver Verified</h3>
                    <p>Your verification has been approved by the administrator.</p>
                <?php elseif ($status === "pending"): ?>
                    <h3>⏳ Verification Pending</h3>
                    <p>Your information is currently being reviewed by the administrator.</p>
                <?php elseif ($status === "rejected"): ?>
                    <h3>✗ Verification Rejected</h3>
                    <p>Your verification request was rejected.</p>
                <?php else: ?>
                    <h3>! Not Verified</h3>
                    <p>Please submit your verification information.</p>
                <?php endif; ?>
            </div>

            <?php if ($status === "rejected" && !empty($verification["reject_reason"])): ?>
                <div class="reject-box"><strong>Admin Rejection Reason</strong>
                    <p style="margin-top:7px"><?= nl2br(htmlspecialchars($verification["reject_reason"])) ?></p>
                </div>
            <?php endif; ?>

            <div class="driver-info">
                <?php $driver_image = $driver["profile_image"] ?: "default.png"; ?>
                <img src="../uploads/profile/<?= htmlspecialchars($driver_image) ?>" alt="Driver" onerror="this.onerror=null;this.src='../images/default.png';">
                <div>
                    <h2><?= htmlspecialchars($driver["name"]) ?></h2>
                    <p>Email: <?= htmlspecialchars($driver["email"]) ?></p>
                    <p>Phone: <?= htmlspecialchars($driver["phone"]) ?></p>
                </div>
            </div>

            <form method="POST" enctype="multipart/form-data">
                <div class="section">
                    <h2 class="section-title">Driving License Information</h2>
                    <div class="grid">
                        <div class="form-group"><label>License Number *</label><input type="text" name="license_number" value="<?= htmlspecialchars($verification["license_number"] ?? "") ?>" placeholder="Enter license number" required></div>
                        <div></div>
                        <div class="form-group"><label>License Issue Date *</label><input type="date" name="license_issue_date" value="<?= htmlspecialchars($verification["license_issue_date"] ?? "") ?>" required></div>
                        <div class="form-group"><label>License Expiry Date *</label><input type="date" name="license_expiry_date" value="<?= htmlspecialchars($verification["license_expiry_date"] ?? "") ?>" required></div>
                    </div>
                </div>

                <div class="section">
                    <h2 class="section-title">Profile Photo</h2>
                    <div class="form-group"><label>Driver Profile Photo</label>
                        <div class="file-box">
                            <input type="file" name="profile_photo" accept=".jpg,.jpeg,.png,.webp">
                            <div class="note">JPG, JPEG, PNG or WEBP. Maximum 5MB.</div>
                            <?php if (!empty($verification["profile_photo"])): ?><img src="../uploads/driver/profile/<?= htmlspecialchars($verification["profile_photo"]) ?>" class="preview" alt="Profile Photo"><?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="section">
                    <h2 class="section-title">Driving License Photos</h2>
                    <div class="grid">
                        <div class="form-group"><label>License Front Photo *</label>
                            <div class="file-box">
                                <input type="file" name="license_photo_front" accept=".jpg,.jpeg,.png,.webp" <?= empty($verification["license_photo_front"]) ? "required" : "" ?>>
                                <div class="note">Upload a clear front photo of your license.</div>
                                <?php if (!empty($verification["license_photo_front"])): ?><img src="../uploads/driver/license/<?= htmlspecialchars($verification["license_photo_front"]) ?>" class="license-preview" alt="License Front"><?php endif; ?>
                            </div>
                        </div>

                        <div class="form-group"><label>License Back Photo</label>
                            <div class="file-box">
                                <input type="file" name="license_photo_back" accept=".jpg,.jpeg,.png,.webp">
                                <div class="note">Upload the back side of your license.</div>
                                <?php if (!empty($verification["license_photo_back"])): ?><img src="../uploads/driver/license/<?= htmlspecialchars($verification["license_photo_back"]) ?>" class="license-preview" alt="License Back"><?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="info-box">Please make sure your license number, issue date, expiry date and uploaded license photos are correct and clearly visible. The administrator will review this information before approving your driver account.</div>
                <div class="submit-area"><button type="submit" class="submit-btn">Submit Verification</button></div>
            </form>
        </div>
    </div>
</body>

</html>