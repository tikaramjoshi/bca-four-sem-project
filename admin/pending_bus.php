<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != "admin") {
    header("Location: ../login.php");
    exit();
}
require_once "../db.php";
$sql = " SELECT bus.*, users.name AS owner_name, users.email AS owner_email, users.phone AS owner_phone FROM bus INNER JOIN users     ON bus.owner_id = users.user_id WHERE bus.status = 'pending' ORDER BY bus.bus_id DESC ";
$result = mysqli_query($conn, $sql);
if (!$result) {
    die("Database Error: " . mysqli_error($conn));
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Buses</title>
    <link rel="stylesheet" href="pending_bus.css">
</head>

<body>
    <?php include "admin_header.php" ?>
    <div class="content">
        <div class="box">
            <h2>Pending Bus Requests</h2>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Bus ID</th>
                            <th>Image</th>
                            <th>Bus Number</th>
                            <th>Bus Name</th>
                            <th>Bus Type</th>
                            <th>Seats</th>
                            <th>Owner</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($result) > 0): ?>
                            <?php while ($bus = mysqli_fetch_assoc($result)): ?>
                                <tr>
                                    <td><?= htmlspecialchars($bus['bus_id']) ?></td>
                                    <td>
                                        <?php if (!empty($bus['bus_image'])): ?>
                                            <img src="../uploads/bus/<?= htmlspecialchars($bus['bus_image']) ?>" class="bus-image" alt="Bus Image">
                                        <?php else: ?>
                                            <img src="../images/no-image.png" class="bus-image" alt="No Image">
                                        <?php endif; ?>
                                    </td>
                                    <td> <?= htmlspecialchars($bus['bus_number']) ?>
                                    </td>
                                    <td> <?= htmlspecialchars($bus['bus_name']) ?>
                                    </td>
                                    <td> <?= htmlspecialchars($bus['bus_type']) ?>
                                    </td>
                                    <td> <?= htmlspecialchars($bus['seats']) ?>
                                    </td>
                                    <td> <?= htmlspecialchars($bus['owner_name']) ?>
                                    </td>
                                    <td>
                                        <span class="pending"> Pending </span>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <a href="view_bus.php?id=<?= $bus['bus_id'] ?>" class="view-btn"><i class="fa fa-eye"></i>&nbsp; View </a>
                                            <a href="approve_bus.php?id=<?= $bus['bus_id'] ?>" class="approve-btn" onclick="return confirm('Are you sure you want to approve this bus?');"><i class="fa fa-check"></i>&nbsp; Approve </a>
                                            <a href="reject_bus.php?id=<?= $bus['bus_id'] ?>" class="reject-btn"><i class="fa fa-close"></i>&nbsp; Reject </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" style="text-align:center; padding:25px;">No pending buses found.</td>
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