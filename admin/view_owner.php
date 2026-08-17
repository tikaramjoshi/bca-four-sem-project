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
    <style>
        * {
            box-sizing: border-box
        }

        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            margin: 0;
            padding: 0
        }

        .box {
            width: 750px;
            max-width: 92%;
            margin: 40px auto;
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, .1)
        }

        h2 {
            text-align: center;
            margin: 0 0 25px;
            color: #333
        }

        .profile {
            text-align: center;
            margin-bottom: 25px
        }

        .profile img {
            width: 140px;
            height: 140px;
            object-fit: cover;
            border-radius: 50%;
            border: 3px solid #1560BD
        }

        table {
            width: 100%;
            border-collapse: collapse
        }

        td {
            padding: 13px;
            border-bottom: 1px solid #e5e5e5;
            vertical-align: middle
        }

        td:first-child {
            width: 240px;
            font-weight: 600;
            color: #444
        }

        .section-title {
            background: #f1f4f8;
            font-size: 17px;
            font-weight: bold;
            color: #1560BD
        }

        .approved,
        .verified {
            color: #198754;
            font-weight: bold
        }

        .pending {
            color: #f39c12;
            font-weight: bold
        }

        .rejected,
        .unverified {
            color: #dc3545;
            font-weight: bold
        }

        .document {
            width: 220px;
            max-height: 180px;
            object-fit: contain;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 5px;
            background: #fafafa
        }

        .view-document,
        .back {
            display: inline-block;
            color: #fff;
            text-decoration: none;
            border-radius: 6px
        }

        .view-document {
            margin-top: 8px;
            padding: 7px 12px;
            background: #1560BD;
            font-size: 13px
        }

        .view-document:hover {
            background: #0d4f9c
        }

        .back {
            margin-top: 25px;
            padding: 10px 22px;
            background: #555
        }

        .back:hover {
            background: #333
        }

        @media(max-width:600px) {
            .box {
                padding: 18px;
                margin: 20px auto
            }

            td {
                padding: 10px
            }

            td:first-child {
                width: 40%
            }

            .document {
                width: 160px
            }
        }
    </style>
</head>

<body>
    <?php include "admin_header.php"; ?>
    <div class="content">
        <div class="box">
            <h2>Owner Details</h2>
            <div class="profile">
                <?php if (!empty($owner['profile_image'])): ?>
                    <img src="../uploads/<?= htmlspecialchars($owner['profile_image']) ?>" alt="Owner Profile">
                <?php elseif (!empty($owner['owner_photo'])): ?>
                    <img src="../uploads/<?= htmlspecialchars($owner['owner_photo']) ?>" alt="Owner Photo">
                <?php else: ?>
                    <img src="../images/default.png" alt="Default Profile">
                <?php endif; ?>
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
                            <img src="../uploads/<?= htmlspecialchars($owner['owner_photo']) ?>" class="document" alt="Owner Photo"><br>
                            <a href="../uploads/<?= htmlspecialchars($owner['owner_photo']) ?>" target="_blank" class="view-document">View Full Image</a>
                            <?php else: ?>-<?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td>Company Certificate</td>
                    <td>
                        <?php if (!empty($owner['company_certificate'])): ?>
                            <img src="../uploads/<?= htmlspecialchars($owner['company_certificate']) ?>" class="document" alt="Company Certificate"><br>
                            <a href="../uploads/<?= htmlspecialchars($owner['company_certificate']) ?>" target="_blank" class="view-document">View Certificate</a>
                            <?php else: ?>-<?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td>Reject Reason</td>
                    <td><?= !empty($owner['reject_reason']) ? nl2br(htmlspecialchars($owner['reject_reason'])) : "-" ?></td>
                </tr>
            </table>
            <a href="view_owners.php" class="back">Back</a>
        </div>
    </div>
</body>

</html>