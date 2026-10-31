<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != "admin") {
    header("Location: ../login.php");
    exit();
}
require_once "../db.php";
$sql = "SELECT u.user_id,u.name,u.email,u.phone,u.profile_image,u.verification_status,u.created_at,ov.owner_photo FROM users u LEFT JOIN owner_verification ov ON u.user_id=ov.owner_id WHERE u.role='owner' AND (ov.verification_id IS NULL OR ov.verification_id = (     SELECT MAX(verification_id) FROM owner_verification WHERE owner_id=u.user_id )) ORDER BY u.user_id DESC";
$result = mysqli_query($conn, $sql);
if (!$result) die("Database Error: " . mysqli_error($conn));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>View Owners</title>
    <link rel="stylesheet" href="view_owners.css">

</head>

<body>
    <?php include "admin_header.php"; ?>

    <div class="content">
        <div class="box">
            <h2 class="title">All Owners</h2>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Owner ID</th>
                            <th>Owner Image</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Verification </th>
                            <th>Registered Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($result) > 0): ?>
                            <?php while ($owner = mysqli_fetch_assoc($result)): ?>
                                <tr>
                                    <td><?= htmlspecialchars($owner['user_id']) ?></td>
                                    <td>
                                        <?php
                                        $image = !empty($owner['profile_image']) ? "../uploads/profile/" . (int)$owner['user_id'] . "/profile/" . $owner['profile_image'] : (!empty($owner['owner_photo']) ? "../uploads/profile/" . (int)$owner['user_id'] . "/profile/" . $owner['owner_photo']  : "../uploads/default.png");
                                        ?>
                                        <div class="profile">
                                            <img src="<?= htmlspecialchars($image) ?>" alt="Owner" onerror="this.onerror=null;this.src='../uploads/default.png';">
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($owner['name']) ?></td>
                                    <td><?= htmlspecialchars($owner['email']) ?></td>
                                    <td><?= htmlspecialchars($owner['phone']) ?></td>
                                    <td>
                                        <?php if ($owner['verification_status'] == "verified"): ?>
                                            <span class="verified">Verified</span>
                                        <?php else: ?>
                                            <span class="unverified">Unverified</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($owner['created_at']) ?></td>
                                    <td><a href="view_owner.php?id=<?= (int)$owner['user_id'] ?>" class="view-btn"><i class="fa fa-eye"></i>&nbsp; View Details</a></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align:center">No owners found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <a href="dashboard.php" class="back"><i class="fa fa-arrow-left"></i>&nbsp; Back</a>
        </div>
    </div>
</body>

</html>