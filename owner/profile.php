<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'owner') {
    header("Location: ../login.php");
    exit();
}
require_once "../db.php";
$owner_id = (int)$_SESSION['user_id'];
$stmt = $conn->prepare("SELECT user_id, name, email, phone, profile_image, verification_status, created_at FROM users WHERE user_id=? AND role='owner' LIMIT 1");
$stmt->bind_param("i", $owner_id);
$stmt->execute();
$owner = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$owner) {
    session_destroy();
    header("Location: ../login.php");
    exit();
}
$stmt = $conn->prepare("SELECT company_name, company_registration_no, owner_photo, company_certificate, status, reject_reason, created_at FROM owner_verification WHERE owner_id=? ORDER BY verification_id DESC LIMIT 1");
$stmt->bind_param("i", $owner_id);
$stmt->execute();
$verification = $stmt->get_result()->fetch_assoc();
$stmt->close();
$profile_image = !empty($owner['profile_image']) ? $owner['profile_image'] : "default.png";
$verification_status = $owner['verification_status'] ?? 'unverified';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Owner Profile</title>
    <link rel="stylesheet" href="owner.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, Helvetica, sans-serif
        }

        body {
            background: #f5f7fb;
            color: #333
        }

        .main {
            background: #1560BD;
            padding: 10px 25px
        }

        nav {
            display: flex;
            align-items: center;
            gap: 10px
        }

        nav a {
            text-decoration: none;
            color: #000;
            background: #a59aef;
            padding: 9px 16px;
            border-radius: 5px;
            font-weight: bold
        }

        nav a:hover {
            background: #0fa070;
            color: #fff
        }

        .profile-page {
            padding: 35px 25px;
            max-width: 1100px;
            margin: auto
        }

        .profile-title {
            text-align: center;
            margin-bottom: 30px
        }

        .profile-title h1 {
            color: #1560BD;
            font-size: 28px;
            margin-bottom: 8px
        }

        .profile-title p {
            color: #777;
            font-size: 14px
        }

        .profile-container {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 25px
        }

        .profile-card,
        .details-card,
        .verification-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, .08)
        }

        .profile-card {
            padding: 30px;
            text-align: center
        }

        .profile-image {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #1560BD;
            margin-bottom: 18px
        }

        .profile-card h2 {
            font-size: 22px;
            color: #222;
            margin-bottom: 7px
        }

        .role {
            display: inline-block;
            background: #e4edff;
            color: #1560BD;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: bold;
            margin-bottom: 20px
        }

        .edit-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            width: 100%;
            padding: 11px;
            background: #1560BD;
            color: #fff;
            text-decoration: none;
            border-radius: 7px;
            font-weight: bold
        }

        .edit-btn:hover {
            background: #0d4e9c
        }

        .details-card {
            padding: 25px;
            margin-bottom: 25px
        }

        .card-title {
            font-size: 19px;
            color: #1560BD;
            border-bottom: 1px solid #eee;
            padding-bottom: 14px;
            margin-bottom: 18px
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px
        }

        .info-item {
            background: #f8f9fb;
            padding: 15px;
            border-radius: 8px
        }

        .info-item label {
            display: block;
            color: #777;
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 7px;
            text-transform: uppercase
        }

        .info-item span {
            font-size: 15px;
            color: #333;
            font-weight: 500;
            word-break: break-word
        }

        .verification-card {
            padding: 25px
        }

        .verification-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 1px solid #eee;
            padding-bottom: 14px
        }

        .verification-header h3 {
            color: #1560BD
        }

        .badge {
            padding: 6px 13px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold
        }

        .badge.verified {
            background: #dff6e5;
            color: #16803c
        }

        .badge.pending {
            background: #fff3cd;
            color: #856404
        }

        .badge.rejected {
            background: #ffe1e1;
            color: #d62828
        }

        .badge.unverified {
            background: #eee;
            color: #777
        }

        .verification-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px
        }

        .verification-item {
            padding: 14px;
            background: #f8f9fb;
            border-radius: 8px
        }

        .verification-item label {
            display: block;
            font-size: 12px;
            color: #777;
            font-weight: bold;
            margin-bottom: 6px
        }

        .verification-item span {
            font-size: 14px
        }

        .document-box {
            margin-top: 20px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px
        }

        .document {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            text-align: center
        }

        .document h4 {
            margin-bottom: 12px;
            color: #555
        }

        .document img {
            width: 130px;
            height: 130px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid #1560BD
        }

        .view-btn {
            display: inline-block;
            margin-top: 12px;
            padding: 8px 14px;
            background: #1560BD;
            color: #fff;
            text-decoration: none;
            border-radius: 5px;
            font-size: 13px
        }

        .view-btn:hover {
            background: #0d4e9c
        }

        .no-verification {
            text-align: center;
            padding: 30px;
            color: #777
        }

        .verify-btn {
            display: inline-block;
            margin-top: 15px;
            padding: 10px 18px;
            background: #1560BD;
            color: #fff;
            text-decoration: none;
            border-radius: 6px;
            font-weight: bold
        }

        .reject-reason {
            margin-top: 18px;
            padding: 15px;
            background: #ffe3e3;
            color: #721c24;
            border-radius: 8px
        }

        .footer {
            background: #1560BD;
            color: #fff;
            text-align: center;
            padding: 15px;
            margin-top: 30px
        }

        @media(max-width:800px) {
            .profile-container {
                grid-template-columns: 1fr
            }

            .info-grid,
            .verification-grid,
            .document-box {
                grid-template-columns: 1fr
            }
        }

        @media(max-width:500px) {
            .profile-page {
                padding: 20px 12px
            }

            .profile-card,
            .details-card,
            .verification-card {
                padding: 20px
            }

            .profile-image {
                width: 125px;
                height: 125px
            }
        }
    </style>
</head>

<body>
    <div class="main">
        <nav>
            <a href="dashboard.php"><i class="fa fa-home"></i> Home</a>
        </nav>
    </div>
    <div class="profile-page">
        <div class="profile-title">
            <h1>My Profile</h1>
            <p>View your owner account and verification information</p>
        </div>
        <div class="profile-container">
            <div class="profile-card">
                <img src="../uploads/profile/<?= htmlspecialchars($profile_image) ?>" class="profile-image" alt="Profile">
                <h2><?= htmlspecialchars($owner['name']) ?></h2>
                <span class="role"><i class="fa fa-user-tie"></i> Owner</span>
                <a href="edit_profile.php" class="edit-btn"><i class="fa fa-edit"></i> Edit Profile</a>
            </div>
            <div>
                <div class="details-card">
                    <h3 class="card-title"><i class="fa fa-user"></i> Personal Information</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <label>Full Name</label>
                            <span><?= htmlspecialchars($owner['name']) ?></span>
                        </div>
                        <div class="info-item">
                            <label>Email Address</label>
                            <span><?= htmlspecialchars($owner['email']) ?></span>
                        </div>
                        <div class="info-item">
                            <label>Phone Number</label>
                            <span><?= htmlspecialchars($owner['phone']) ?></span>
                        </div>
                        <div class="info-item">
                            <label>Account Role</label>
                            <span>Bus Owner</span>
                        </div>
                        <div class="info-item">
                            <label>Account Created</label>
                            <span><?= date("d M Y", strtotime($owner['created_at'])) ?></span>
                        </div>
                        <div class="info-item">
                            <label>Verification Status</label>
                            <span><?= ucfirst(htmlspecialchars($verification_status)) ?></span>
                        </div>
                    </div>
                </div>
                <div class="verification-card">
                    <div class="verification-header">
                        <h3><i class="fa fa-certificate"></i> Company Verification</h3>
                        <?php if ($verification_status === 'verified'): ?>
                            <span class="badge verified">Verified</span>
                        <?php elseif ($verification_status === 'pending'): ?>
                            <span class="badge pending">Pending</span>
                        <?php elseif ($verification_status === 'rejected'): ?>
                            <span class="badge rejected">Rejected</span>
                        <?php else: ?>
                            <span class="badge unverified">Unverified</span>
                        <?php endif; ?>
                    </div>
                    <?php if ($verification): ?>
                        <div class="verification-grid">
                            <div class="verification-item">
                                <label>Company Name</label>
                                <span><?= htmlspecialchars($verification['company_name']) ?></span>
                            </div>
                            <div class="verification-item">
                                <label>Registration Number</label>
                                <span><?= htmlspecialchars($verification['company_registration_no']) ?></span>
                            </div>
                            <div class="verification-item">
                                <label>Verification Status</label>
                                <span><?= ucfirst(htmlspecialchars($verification['status'])) ?></span>
                            </div>
                            <div class="verification-item">
                                <label>Submitted Date</label>
                                <span><?= date("d M Y", strtotime($verification['created_at'])) ?></span>
                            </div>
                        </div>
                        <?php if ($verification['status'] === 'rejected'): ?>
                            <div class="reject-reason">
                                <strong>Rejection Reason:</strong><br>
                                <?= htmlspecialchars($verification['reject_reason'] ?? 'No reason provided') ?>
                            </div>
                        <?php endif; ?>
                        <div class="document-box">
                            <div class="document">
                                <h4>Owner Photo</h4>
                                <?php if (!empty($verification['owner_photo'])): ?>
                                    <img src="../uploads/<?= htmlspecialchars($verification['owner_photo']) ?>" alt="Owner Photo">
                                    <br>
                                    <a href="../uploads/<?= htmlspecialchars($verification['owner_photo']) ?>" target="_blank" class="view-btn"><i class="fa fa-eye"></i> View Photo</a>
                                <?php else: ?>
                                    <p>No photo uploaded</p>
                                <?php endif; ?>
                            </div>
                            <div class="document">
                                <h4>Company Certificate</h4>
                                <?php if (!empty($verification['company_certificate'])): ?>
                                    <?php
                                    $certificate_ext = strtolower(pathinfo($verification['company_certificate'], PATHINFO_EXTENSION));
                                    if (in_array($certificate_ext, ['jpg', 'jpeg', 'png', 'webp'])):
                                    ?>
                                        <img src="../uploads/<?= htmlspecialchars($verification['company_certificate']) ?>" alt="Certificate">
                                    <?php else: ?>
                                        <div style="font-size:55px;color:#d62828;margin:35px 0"><i class="fa fa-file-pdf"></i></div>
                                    <?php endif; ?>
                                    <br>
                                    <a href="../uploads/<?= htmlspecialchars($verification['company_certificate']) ?>" target="_blank" class="view-btn"><i class="fa fa-eye"></i> View Certificate</a>
                                <?php else: ?>
                                    <p>No certificate uploaded</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="no-verification">
                            <i class="fa fa-file-circle-question" style="font-size:45px;color:#999"></i>
                            <p style="margin-top:12px">You have not submitted company verification yet.</p>
                            <a href="verification.php" class="verify-btn"><i class="fa fa-check-circle"></i> Verify Company</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <footer class="footer">
        <p>&copy;2026 Online Bus Ticket Booking System | All rights reserved.</p>
    </footer>
</body>

</html>