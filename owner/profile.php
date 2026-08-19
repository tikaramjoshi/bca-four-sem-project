<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'owner') {
    header("Location: ../login.php");
    exit();
}
require_once "../db.php";

$owner_id = (int)$_SESSION['user_id'];

$ownerQuery = $conn->prepare("SELECT user_id,name,email,phone,profile_image,verification_status,created_at FROM users WHERE user_id=? AND role='owner' LIMIT 1");
$ownerQuery->bind_param("i", $owner_id);
$ownerQuery->execute();
$owner = $ownerQuery->get_result()->fetch_assoc();
$ownerQuery->close();

if (!$owner) {
    session_destroy();
    header("Location: ../login.php");
    exit();
}

$owner_name = $owner['name'];
$owner_email = $owner['email'];
$verification_status = !empty($owner['verification_status']) ? $owner['verification_status'] : 'unverified';
$isVerified = $verification_status === 'verified';
$profile_image = !empty($owner['profile_image']) ? $owner['profile_image'] : '';

$verifyStmt = $conn->prepare("SELECT company_name,company_registration_no,owner_photo,company_certificate,status,reject_reason,created_at FROM owner_verification WHERE owner_id=? ORDER BY verification_id DESC LIMIT 1");
$verifyStmt->bind_param("i", $owner_id);
$verifyStmt->execute();
$verification = $verifyStmt->get_result()->fetch_assoc();
$verifyStmt->close();

$profileImagePath = "../uploads/default.png";

if (!empty($profile_image) && $profile_image !== 'default.png') {
    $profileImagePath = "../uploads/profile/" . $owner_id . "/profile/" . htmlspecialchars($profile_image);
} elseif (!empty($verification['owner_photo'])) {
    $profileImagePath = "../uploads/profile/" . $owner_id . "/profile/" . htmlspecialchars($verification['owner_photo']);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Owner Profile</title>
    <link rel="stylesheet" href="profile.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
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

                <img src="<?= $profileImagePath ?>" alt="Owner Photo" class="profile-image">

                <h2><?= htmlspecialchars($owner['name']) ?></h2>

                <span class="role">
                    <i class="fa fa-user"></i> Owner
                </span>

                <a href="edit_profile.php" class="edit-btn">
                    <i class="fa fa-edit"></i> Edit Profile
                </a>

            </div>

            <div>

                <div class="details-card">

                    <h3 class="card-title">
                        <i class="fa fa-user"></i> Personal Information
                    </h3>

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
                            <span><?= htmlspecialchars($owner['phone'] ?? 'N/A') ?></span>
                        </div>

                        <div class="info-item">
                            <label>Account Role</label>
                            <span>Bus Owner</span>
                        </div>

                        <div class="info-item">
                            <label>Account Created</label>
                            <span>
                                <?= !empty($owner['created_at']) ? date("d M Y", strtotime($owner['created_at'])) : 'N/A' ?>
                            </span>
                        </div>

                        <div class="info-item">
                            <label>Verification Status</label>
                            <span><?= ucfirst(htmlspecialchars($verification_status)) ?></span>
                        </div>

                    </div>

                </div>

                <div class="verification-card">

                    <div class="verification-header">

                        <h3>
                            <i class="fa fa-certificate"></i>
                            Company Verification
                        </h3>

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
                                <span><?= htmlspecialchars($verification['company_name'] ?? 'N/A') ?></span>
                            </div>

                            <div class="verification-item">
                                <label>Registration Number</label>
                                <span><?= htmlspecialchars($verification['company_registration_no'] ?? 'N/A') ?></span>
                            </div>

                            <div class="verification-item">
                                <label>Verification Status</label>
                                <span><?= ucfirst(htmlspecialchars($verification['status'] ?? 'N/A')) ?></span>
                            </div>

                            <div class="verification-item">
                                <label>Submitted Date</label>
                                <span>
                                    <?= !empty($verification['created_at']) ? date("d M Y", strtotime($verification['created_at'])) : 'N/A' ?>
                                </span>
                            </div>

                        </div>

                        <?php if (($verification['status'] ?? '') === 'rejected'): ?>

                            <div class="reject-reason">
                                <strong>Rejection Reason:</strong>
                                <br>
                                <?= htmlspecialchars($verification['reject_reason'] ?? 'No reason provided') ?>
                            </div>

                        <?php endif; ?>

                        <div class="document-box">

                            <div class="document">

                                <h4>Owner Photo</h4>

                                <?php if (!empty($verification['owner_photo'])): ?>

                                    <img src="../uploads/profile/<?= $owner_id ?>/profile/<?= htmlspecialchars($verification['owner_photo']) ?>" alt="Owner Photo">

                                    <br>

                                    <a href="../uploads/profile/<?= $owner_id ?>/profile/<?= htmlspecialchars($verification['owner_photo']) ?>" target="_blank" class="view-btn">
                                        <i class="fa fa-eye"></i> View Photo
                                    </a>

                                <?php else: ?>

                                    <p>No photo uploaded</p>

                                <?php endif; ?>

                            </div>

                            <div class="document">

                                <h4>Company Certificate</h4>

                                <?php if (!empty($verification['company_certificate'])): ?>

                                    <?php
                                    $certificate_ext = strtolower(pathinfo($verification['company_certificate'], PATHINFO_EXTENSION));
                                    $certificate_file = "../uploads/profile/" . $owner_id . "/profile/" . htmlspecialchars($verification['company_certificate']);
                                    ?>

                                    <?php if (in_array($certificate_ext, ['jpg', 'jpeg', 'png', 'webp'])): ?>

                                        <img src="<?= $certificate_file ?>" alt="Company Certificate">

                                    <?php else: ?>

                                        <div class="pdf-icon">
                                            <i class="fa fa-file-pdf"></i>
                                        </div>

                                    <?php endif; ?>

                                    <br>

                                    <a href="<?= $certificate_file ?>" target="_blank" class="view-btn">
                                        <i class="fa fa-eye"></i> View Certificate
                                    </a>

                                <?php else: ?>

                                    <p>No certificate uploaded</p>

                                <?php endif; ?>

                            </div>

                        </div>

                    <?php else: ?>

                        <div class="no-verification">

                            <i class="fa fa-file-circle-question"></i>

                            <p>You have not submitted company verification yet.</p>

                            <a href="verification.php" class="verify-btn">
                                <i class="fa fa-check-circle"></i>
                                Verify Company
                            </a>

                        </div>

                    <?php endif; ?>

                </div>

            </div>

        </div>

    </div>

    <footer class="footer">
        <p>&copy;2026 Online Bus Ticket Booking System || All rights reserved.</p>
    </footer>

</body>

</html>