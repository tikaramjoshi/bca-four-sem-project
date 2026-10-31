<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != "admin") {
    header("Location: ../login.php");
    exit();
}
require_once "../db.php";
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: view_owners.php");
    exit();
}
$owner_id = (int)$_GET['id'];
$sql = "SELECT u.user_id,u.name,u.email,u.phone,u.profile_image,u.verification_status,u.created_at,ov.verification_id,ov.company_name,ov.company_registration_no,ov.owner_photo,ov.company_certificate,ov.status AS verification_request_status,ov.reject_reason FROM users u LEFT JOIN owner_verification ov ON u.user_id=ov.owner_id WHERE u.user_id=$owner_id AND u.role='owner' ORDER BY ov.verification_id DESC LIMIT 1";
$result = mysqli_query($conn, $sql);
if (!$result) die("Database Error: " . mysqli_error($conn));
if (mysqli_num_rows($result) == 0) die("Owner Not Found");
$owner = mysqli_fetch_assoc($result);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Owner Details</title>
    <link rel="stylesheet" href="side.css">
    <link rel="stylesheet" href="view_owner.css">
</head>

<body>
    <?php include "admin_header.php"; ?>
    <div class="content">
        <div class="box">
            <h2>Owner Details</h2>

            <div class="profile">
                <?php
                $image = !empty($owner['profile_image'])
                    ? "../uploads/profile/" . (int)$owner['user_id'] . "/profile/" . $owner['profile_image']
                    : (!empty($owner['owner_photo']) ? "../uploads/" . $owner['owner_photo'] : "../uploads/default.png");
                ?>
                <img src="<?= htmlspecialchars($image) ?>" alt="Profile"
                    onerror="this.onerror=null; this.src='../uploads/default.png';">
            </div>
            <table>
                <tr>
                    <td colspan="2" class="section-title">Basic Information</td>
                </tr>
                <tr>
                    <td>Owner ID</td>
                    <td><?= htmlspecialchars($owner['user_id']) ?></td>
                </tr>
                <tr>
                    <td>Name</td>
                    <td><?= htmlspecialchars($owner['name']) ?></td>
                </tr>
                <tr>
                    <td>Email</td>
                    <td><?= htmlspecialchars($owner['email']) ?></td>
                </tr>
                <tr>
                    <td>Phone</td>
                    <td><?= htmlspecialchars($owner['phone']) ?></td>
                </tr>
                <tr>
                    <td>Registered Date</td>
                    <td><?= htmlspecialchars($owner['created_at']) ?></td>
                </tr>
                <tr>
                    <td>Account Verification</td>
                    <td><?= ($owner['verification_status'] == "verified") ? '<span class="verified">Verified</span>' : '<span class="unverified">Unverified</span>' ?></td>
                </tr>
                <tr>
                    <td colspan="2" class="section-title">Company Information</td>
                </tr>
                <tr>
                    <td>Company Name</td>
                    <td><?= !empty($owner['company_name']) ? htmlspecialchars($owner['company_name']) : "-" ?></td>
                </tr>
                <tr>
                    <td>Registration Number</td>
                    <td><?= !empty($owner['company_registration_no']) ? htmlspecialchars($owner['company_registration_no']) : "-" ?></td>
                </tr>
                <tr>
                    <td colspan="2" class="section-title">Verification Information</td>
                </tr>
                <tr>
                    <td>Verification Status</td>
                    <td>
                        <?php
                        if ($owner['verification_request_status'] == "verified") echo '<span class="approved">Verified</span>';
                        elseif ($owner['verification_request_status'] == "pending") echo '<span class="pending">Pending</span>';
                        elseif ($owner['verification_request_status'] == "rejected") echo '<span class="rejected">Rejected</span>';
                        else echo "-";
                        ?>
                    </td>
                </tr>
                <tr>
                    <td>Owner Verification Photo</td>
                    <td>
                        <?php if (!empty($owner['owner_photo'])): ?>
                            <img src="../uploads/profile/<?= (int)$owner['user_id'] ?>/profile/<?= htmlspecialchars($owner['owner_photo']) ?>" class="document" alt="Owner Photo"><br>
                            <a href="../uploads/profile/<?= (int)$owner['user_id'] ?>/profile/<?= htmlspecialchars($owner['owner_photo']) ?>" target="_blank" class="view-document"><i class="fa fa-eye"></i>&nbsp; View Full Image</a><br>
                            <?php else: ?>-<?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td>Company Certificate</td>
                    <td>
                        <?php if (!empty($owner['company_certificate'])): ?>
                            <img src="../uploads/profile/<?= (int)$owner['user_id'] ?>/profile/<?= htmlspecialchars($owner['company_certificate']) ?>" class="document" alt="Company Certificate"><br>
                            <a href="../uploads/profile/<?= (int)$owner['user_id'] ?>/profile/<?= htmlspecialchars($owner['company_certificate']) ?>" target="_blank" class="view-document"><i class="fa fa-eye"></i>&nbsp; View Certificate</a>
                            <?php else: ?>-<?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td>Reject Reason</td>
                    <td><?= !empty($owner['reject_reason']) ? nl2br(htmlspecialchars($owner['reject_reason'])) : "-" ?></td>
                </tr>
            </table>
            <a href="view_owners.php" class="back"><i class="fa fa-arrow-left"></i>&nbsp; Back</a>
        </div>
    </div>
</body>

</html>