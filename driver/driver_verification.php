<?php
session_start();
require_once "../db.php";

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'driver'
) {
    header("Location: ../login.php");
    exit;
}

$driver_id = (int) $_SESSION['user_id'];

$stmt = $conn->prepare("
    SELECT user_id, name, email, phone, profile_image
    FROM users
    WHERE user_id = ? AND role = 'driver'
    LIMIT 1
");

$stmt->bind_param("i", $driver_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Driver account not found.");
}

$driver = $result->fetch_assoc();

$stmt = $conn->prepare("
    SELECT
        verification_id,
        license_number,
        license_issue_date,
        license_expiry_date,
        profile_photo,
        license_photo_front,
        license_photo_back,
        status,
        reject_reason,
        created_at,
        updated_at
    FROM driver_verification
    WHERE driver_id = ?
    ORDER BY verification_id DESC
    LIMIT 1
");

$stmt->bind_param("i", $driver_id);
$stmt->execute();

$verification_result = $stmt->get_result();

$verification = null;

if ($verification_result->num_rows > 0) {
    $verification = $verification_result->fetch_assoc();
}

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $license_number = trim($_POST["license_number"] ?? "");
    $license_issue_date = trim($_POST["license_issue_date"] ?? "");
    $license_expiry_date = trim($_POST["license_expiry_date"] ?? "");

    if (
        $license_number === "" ||
        $license_issue_date === "" ||
        $license_expiry_date === ""
    ) {
        $error = "Please fill in all license information.";
    } elseif ($license_expiry_date <= $license_issue_date) {
        $error = "License expiry date must be after issue date.";
    }

    $profile_photo = $verification["profile_photo"] ?? null;
    $license_photo_front = $verification["license_photo_front"] ?? null;
    $license_photo_back = $verification["license_photo_back"] ?? null;

    if ($error === "") {

        if (
            isset($_FILES["profile_photo"]) &&
            $_FILES["profile_photo"]["error"] !== UPLOAD_ERR_NO_FILE
        ) {

            if ($_FILES["profile_photo"]["error"] !== UPLOAD_ERR_OK) {
                $error = "Profile photo upload failed.";
            } else {

                $allowed = ["jpg", "jpeg", "png", "webp"];

                $extension = strtolower(
                    pathinfo(
                        $_FILES["profile_photo"]["name"],
                        PATHINFO_EXTENSION
                    )
                );

                if (!in_array($extension, $allowed, true)) {
                    $error = "Only JPG, JPEG, PNG and WEBP files are allowed.";
                } elseif ($_FILES["profile_photo"]["size"] > 5 * 1024 * 1024) {
                    $error = "Profile photo must be less than 5MB.";
                } else {

                    $folder = "../uploads/driver/profile/";

                    if (!is_dir($folder)) {
                        mkdir($folder, 0777, true);
                    }

                    $filename =
                        "driver_" .
                        $driver_id .
                        "_" .
                        time() .
                        "." .
                        $extension;

                    if (
                        move_uploaded_file(
                            $_FILES["profile_photo"]["tmp_name"],
                            $folder . $filename
                        )
                    ) {
                        $profile_photo = $filename;
                    } else {
                        $error = "Unable to save profile photo.";
                    }
                }
            }
        }
    }

    if ($error === "") {

        if (
            isset($_FILES["license_photo_front"]) &&
            $_FILES["license_photo_front"]["error"] === UPLOAD_ERR_OK
        ) {

            $allowed = ["jpg", "jpeg", "png", "webp"];

            $extension = strtolower(
                pathinfo(
                    $_FILES["license_photo_front"]["name"],
                    PATHINFO_EXTENSION
                )
            );

            if (!in_array($extension, $allowed, true)) {
                $error = "Invalid front license photo format.";
            } elseif (
                $_FILES["license_photo_front"]["size"] >
                5 * 1024 * 1024
            ) {
                $error = "Front license photo must be less than 5MB.";
            } else {

                $folder = "../uploads/driver/license/";

                if (!is_dir($folder)) {
                    mkdir($folder, 0777, true);
                }

                $filename =
                    "license_front_" .
                    $driver_id .
                    "_" .
                    time() .
                    "." .
                    $extension;

                if (
                    move_uploaded_file(
                        $_FILES["license_photo_front"]["tmp_name"],
                        $folder . $filename
                    )
                ) {
                    $license_photo_front = $filename;
                } else {
                    $error = "Unable to save front license photo.";
                }
            }
        } elseif (empty($license_photo_front)) {
            $error = "Front license photo is required.";
        }
    }

    if ($error === "") {

        if (
            isset($_FILES["license_photo_back"]) &&
            $_FILES["license_photo_back"]["error"] !== UPLOAD_ERR_NO_FILE
        ) {

            if ($_FILES["license_photo_back"]["error"] !== UPLOAD_ERR_OK) {
                $error = "Back license photo upload failed.";
            } else {

                $allowed = ["jpg", "jpeg", "png", "webp"];

                $extension = strtolower(
                    pathinfo(
                        $_FILES["license_photo_back"]["name"],
                        PATHINFO_EXTENSION
                    )
                );

                if (!in_array($extension, $allowed, true)) {
                    $error = "Invalid back license photo format.";
                } elseif (
                    $_FILES["license_photo_back"]["size"] >
                    5 * 1024 * 1024
                ) {
                    $error = "Back license photo must be less than 5MB.";
                } else {

                    $folder = "../uploads/driver/license/";

                    if (!is_dir($folder)) {
                        mkdir($folder, 0777, true);
                    }

                    $filename =
                        "license_back_" .
                        $driver_id .
                        "_" .
                        time() .
                        "." .
                        $extension;

                    if (
                        move_uploaded_file(
                            $_FILES["license_photo_back"]["tmp_name"],
                            $folder . $filename
                        )
                    ) {
                        $license_photo_back = $filename;
                    } else {
                        $error = "Unable to save back license photo.";
                    }
                }
            }
        }
    }

    if ($error === "") {

        $insert = $conn->prepare("
            INSERT INTO driver_verification (
                driver_id,
                license_number,
                license_issue_date,
                license_expiry_date,
                profile_photo,
                license_photo_front,
                license_photo_back,
                status
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
        ");

        $insert->bind_param(
            "issssss",
            $driver_id,
            $license_number,
            $license_issue_date,
            $license_expiry_date,
            $profile_photo,
            $license_photo_front,
            $license_photo_back
        );

        if ($insert->execute()) {

            $success =
                "Verification request submitted successfully. " .
                "Please wait for admin approval.";

            $verification = [
                "verification_id" => $insert->insert_id,
                "license_number" => $license_number,
                "license_issue_date" => $license_issue_date,
                "license_expiry_date" => $license_expiry_date,
                "profile_photo" => $profile_photo,
                "license_photo_front" => $license_photo_front,
                "license_photo_back" => $license_photo_back,
                "status" => "pending",
                "reject_reason" => null
            ];
        } else {
            $error = "Database error: " . $insert->error;
        }
    }
}

$status = $verification["status"] ?? "unverified";

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Driver Verification</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, Helvetica, sans-serif;
        }

        body {
            background: #f4f6f9;
            color: #222;
        }

        .container {
            width: 94%;
            max-width: 1000px;
            margin: 35px auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .header h1 {
            font-size: 28px;
            color: #222;
        }

        .header p {
            margin-top: 6px;
            color: #777;
            font-size: 14px;
        }

        .back-btn {
            background: #1560bd;
            color: white;
            text-decoration: none;
            padding: 11px 18px;
            border-radius: 7px;
            font-size: 14px;
        }

        .card {
            background: white;
            border-radius: 14px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, .08);
        }

        .alert {
            padding: 14px 16px;
            border-radius: 7px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-error {
            background: #f8d7da;
            color: #842029;
        }

        .alert-success {
            background: #d1e7dd;
            color: #0f5132;
        }

        .status {
            text-align: center;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 28px;
        }

        .status.pending {
            background: #fff3cd;
            color: #664d03;
        }

        .status.verified {
            background: #d1e7dd;
            color: #0f5132;
        }

        .status.rejected {
            background: #f8d7da;
            color: #842029;
        }

        .status.unverified {
            background: #e2e3e5;
            color: #41464b;
        }

        .status h3 {
            font-size: 20px;
            margin-bottom: 6px;
        }

        .driver-info {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 20px;
            background: #f8f9fb;
            border-radius: 10px;
            margin-bottom: 28px;
        }

        .driver-info img {
            width: 90px;
            height: 90px;
            object-fit: cover;
            border-radius: 50%;
            border: 3px solid #1560bd;
        }

        .driver-info h2 {
            font-size: 21px;
            margin-bottom: 7px;
        }

        .driver-info p {
            color: #666;
            font-size: 13px;
            margin-top: 4px;
        }

        .section {
            margin-top: 28px;
        }

        .section-title {
            font-size: 19px;
            color: #1560bd;
            padding-bottom: 10px;
            border-bottom: 2px solid #edf0f4;
            margin-bottom: 18px;
        }

        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .full {
            grid-column: 1 / -1;
        }

        label {
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 7px;
            color: #444;
        }

        input {
            padding: 12px;
            border: 1px solid #d5dbe3;
            border-radius: 7px;
            font-size: 14px;
            outline: none;
        }

        input:focus {
            border-color: #1560bd;
        }

        .file-box {
            border: 1px dashed #b8c1cd;
            border-radius: 8px;
            padding: 15px;
            background: #fafbfc;
        }

        .file-box input {
            border: none;
            padding: 4px 0;
            width: 100%;
        }

        .note {
            font-size: 11px;
            color: #888;
            margin-top: 6px;
        }

        .preview {
            margin-top: 12px;
            width: 150px;
            height: 120px;
            object-fit: cover;
            border: 1px solid #ddd;
            border-radius: 7px;
        }

        .license-preview {
            margin-top: 12px;
            width: 240px;
            height: 150px;
            object-fit: contain;
            border: 1px solid #ddd;
            border-radius: 7px;
            background: #f8f8f8;
        }

        .reject-box {
            background: #f8d7da;
            color: #842029;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .submit-area {
            margin-top: 30px;
            text-align: center;
        }

        .submit-btn {
            border: none;
            background: #1560bd;
            color: white;
            padding: 13px 35px;
            border-radius: 7px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
        }

        .submit-btn:hover {
            background: #0d4f9c;
        }

        .info-box {
            margin-top: 20px;
            padding: 14px;
            background: #f1f4f8;
            border-left: 4px solid #1560bd;
            color: #555;
            font-size: 13px;
            line-height: 1.6;
        }

        @media(max-width:700px) {

            .container {
                width: 94%;
                margin: 20px auto;
            }

            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .card {
                padding: 20px;
            }

            .grid {
                grid-template-columns: 1fr;
            }

            .full {
                grid-column: auto;
            }

            .driver-info {
                flex-direction: column;
                text-align: center;
            }

        }
    </style>

</head>

<body>

    <div class="container">

        <div class="header">

            <div>

                <h1>Driver Verification</h1>

                <p>
                    Submit your driving license information for verification.
                </p>

            </div>

            <a href="dashboard.php" class="back-btn">
                ← Dashboard
            </a>

        </div>

        <div class="card">

            <?php if ($error): ?>

                <div class="alert alert-error">
                    <?= htmlspecialchars($error) ?>
                </div>

            <?php endif; ?>

            <?php if ($success): ?>

                <div class="alert alert-success">
                    <?= htmlspecialchars($success) ?>
                </div>

            <?php endif; ?>

            <div class="status <?= htmlspecialchars($status) ?>">

                <?php if ($status === "verified"): ?>

                    <h3>✓ Driver Verified</h3>

                    <p>
                        Your verification has been approved by the administrator.
                    </p>

                <?php elseif ($status === "pending"): ?>

                    <h3>⏳ Verification Pending</h3>

                    <p>
                        Your information is currently being reviewed by the administrator.
                    </p>

                <?php elseif ($status === "rejected"): ?>

                    <h3>✗ Verification Rejected</h3>

                    <p>
                        Your verification request was rejected.
                    </p>

                <?php else: ?>

                    <h3>! Not Verified</h3>

                    <p>
                        Please submit your verification information.
                    </p>

                <?php endif; ?>

            </div>

            <?php if (
                $status === "rejected" &&
                !empty($verification["reject_reason"])
            ): ?>

                <div class="reject-box">

                    <strong>Admin Rejection Reason</strong>

                    <p style="margin-top:7px;">
                        <?= nl2br(
                            htmlspecialchars(
                                $verification["reject_reason"]
                            )
                        ) ?>
                    </p>

                </div>

            <?php endif; ?>

            <div class="driver-info">

                <?php
                $driver_image = !empty($driver["profile_image"])
                    ? $driver["profile_image"]
                    : "default.png";
                ?>

                <img
                    src="../uploads/profile/<?= htmlspecialchars($driver_image) ?>"
                    alt="Driver"
                    onerror="this.onerror=null;this.src='../images/default.png';">

                <div>

                    <h2>
                        <?= htmlspecialchars($driver["name"]) ?>
                    </h2>

                    <p>
                        Email:
                        <?= htmlspecialchars($driver["email"]) ?>
                    </p>

                    <p>
                        Phone:
                        <?= htmlspecialchars($driver["phone"]) ?>
                    </p>

                </div>

            </div>

            <form
                method="POST"
                enctype="multipart/form-data">

                <div class="section">

                    <h2 class="section-title">
                        Driving License Information
                    </h2>

                    <div class="grid">

                        <div class="form-group">

                            <label>
                                License Number *
                            </label>

                            <input
                                type="text"
                                name="license_number"
                                value="<?= htmlspecialchars(
                                            $verification["license_number"] ?? ""
                                        ) ?>"
                                placeholder="Enter license number"
                                required>

                        </div>

                        <div></div>

                        <div class="form-group">

                            <label>
                                License Issue Date *
                            </label>

                            <input
                                type="date"
                                name="license_issue_date"
                                value="<?= htmlspecialchars(
                                            $verification["license_issue_date"] ?? ""
                                        ) ?>"
                                required>

                        </div>

                        <div class="form-group">

                            <label>
                                License Expiry Date *
                            </label>

                            <input
                                type="date"
                                name="license_expiry_date"
                                value="<?= htmlspecialchars(
                                            $verification["license_expiry_date"] ?? ""
                                        ) ?>"
                                required>

                        </div>

                    </div>

                </div>

                <div class="section">

                    <h2 class="section-title">
                        Profile Photo
                    </h2>

                    <div class="form-group">

                        <label>
                            Driver Profile Photo
                        </label>

                        <div class="file-box">

                            <input
                                type="file"
                                name="profile_photo"
                                accept=".jpg,.jpeg,.png,.webp">

                            <div class="note">
                                JPG, JPEG, PNG or WEBP. Maximum 5MB.
                            </div>

                            <?php if (
                                !empty($verification["profile_photo"])
                            ): ?>

                                <img
                                    src="../uploads/driver/profile/<?= htmlspecialchars(
                                                                        $verification["profile_photo"]
                                                                    ) ?>"
                                    class="preview"
                                    alt="Profile Photo">

                            <?php endif; ?>

                        </div>

                    </div>

                </div>

                <div class="section">

                    <h2 class="section-title">
                        Driving License Photos
                    </h2>

                    <div class="grid">

                        <div class="form-group">

                            <label>
                                License Front Photo *
                            </label>

                            <div class="file-box">

                                <input
                                    type="file"
                                    name="license_photo_front"
                                    accept=".jpg,.jpeg,.png,.webp"
                                    <?= empty($verification["license_photo_front"]) ? "required" : "" ?>>

                                <div class="note">
                                    Upload a clear front photo of your license.
                                </div>

                                <?php if (
                                    !empty($verification["license_photo_front"])
                                ): ?>

                                    <img
                                        src="../uploads/driver/license/<?= htmlspecialchars(
                                                                            $verification["license_photo_front"]
                                                                        ) ?>"
                                        class="license-preview"
                                        alt="License Front">

                                <?php endif; ?>

                            </div>

                        </div>

                        <div class="form-group">

                            <label>
                                License Back Photo
                            </label>

                            <div class="file-box">

                                <input
                                    type="file"
                                    name="license_photo_back"
                                    accept=".jpg,.jpeg,.png,.webp">

                                <div class="note">
                                    Upload the back side of your license.
                                </div>

                                <?php if (
                                    !empty($verification["license_photo_back"])
                                ): ?>

                                    <img
                                        src="../uploads/driver/license/<?= htmlspecialchars(
                                                                            $verification["license_photo_back"]
                                                                        ) ?>"
                                        class="license-preview"
                                        alt="License Back">

                                <?php endif; ?>

                            </div>

                        </div>

                    </div>

                </div>

                <div class="info-box">

                    Please make sure your license number, issue date,
                    expiry date and uploaded license photos are correct
                    and clearly visible. The administrator will review
                    this information before approving your driver account.

                </div>

                <div class="submit-area">

                    <button
                        type="submit"
                        class="submit-btn">

                        Submit Verification

                    </button>

                </div>

            </form>

        </div>

    </div>

</body>

</html>