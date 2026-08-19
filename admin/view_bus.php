<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != "admin") {
    header("Location: ../login.php");
    exit();
}
require_once "../db.php";
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: viewall_bus.php");
    exit();
}
$bus_id = (int)$_GET['id'];
$sql = "SELECT bus.*,users.name AS owner_name,users.email AS owner_email,users.phone AS owner_phone FROM bus INNER JOIN users ON bus.owner_id=users.user_id WHERE bus.bus_id=$bus_id";
$result = mysqli_query($conn, $sql);
if (!$result) {
    die("Database Error: " . mysqli_error($conn));
}
if (mysqli_num_rows($result) == 0) {
    die("Bus Not Found");
}
$bus = mysqli_fetch_assoc($result);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Bus Details</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            margin: 0;
            padding: 0;
        }

        .box {
            width: 700px;
            max-width: 90%;
            margin: 40px auto;
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, .12);
        }

        h2 {
            text-align: center;
            margin-bottom: 25px;
            color: #333;
        }

        .image-box {
            text-align: center;
            margin-bottom: 25px;
        }

        .image-box img {
            width: 250px;
            height: 160px;
            object-fit: cover;
            border-radius: 10px;
            border: 1px solid #ddd;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid #e5e5e5;
            vertical-align: middle;
        }

        td:first-child {
            font-weight: 600;
            width: 220px;
            color: #444;
        }

        .approved {
            color: #198754;
            font-weight: 600;
        }

        .pending {
            color: #f39c12;
            font-weight: 600;
        }

        .rejected {
            color: #dc3545;
            font-weight: 600;
        }

        .back {
            display: inline-block;
            margin-top: 25px;
            padding: 10px 22px;
            background: #1560BD;
            color: #fff;
            text-decoration: none;
            border-radius: 6px;
        }

        .back:hover {
            background: #0d4f9c;
        }
    </style>
</head>

<body>

    <div class="box">
        <a class="back" href="dashboard.php">Back to Home</a>
        <h2>Bus Details</h2>
        <div class="image-box">
            <?php if (!empty($bus['bus_image'])): ?>
                <img src="../uploads/bus/<?= htmlspecialchars($bus['bus_image']) ?>" alt="Bus Image">
            <?php else: ?>
                <img src="../images/no-image.png" alt="No Image">
            <?php endif; ?>
        </div>
        <table>
            <tr>
                <td>Bus ID</td>
                <td><?= htmlspecialchars($bus['bus_id']) ?></td>
            </tr>
            <tr>
                <td>Bus Number</td>
                <td><?= htmlspecialchars($bus['bus_number']) ?></td>
            </tr>
            <tr>
                <td>Bus Name</td>
                <td><?= htmlspecialchars($bus['bus_name']) ?></td>
            </tr>
            <tr>
                <td>Bus Type</td>
                <td><?= htmlspecialchars($bus['bus_type']) ?></td>
            </tr>
            <tr>
                <td>Total Seats</td>
                <td><?= htmlspecialchars($bus['seats']) ?></td>
            </tr>
            <tr>
                <td>Facilities</td>
                <td><?= !empty($bus['facilities']) ? htmlspecialchars($bus['facilities']) : "-" ?></td>
            </tr>
            <tr>
                <td>Owner Name</td>
                <td><?= htmlspecialchars($bus['owner_name']) ?></td>
            </tr>
            <tr>
                <td>Email</td>
                <td><?= htmlspecialchars($bus['owner_email']) ?></td>
            </tr>
            <tr>
                <td>Phone</td>
                <td><?= htmlspecialchars($bus['owner_phone']) ?></td>
            </tr>
            <tr>
                <td>Status</td>
                <td>
                    <?php if ($bus['status'] == "approved"): ?>
                        <span class="approved">Approved</span>
                    <?php elseif ($bus['status'] == "pending"): ?>
                        <span class="pending">Pending</span>
                    <?php else: ?>
                        <span class="rejected">Rejected</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td>Reject Reason</td>
                <td><?= !empty($bus['reject_reason']) ? nl2br(htmlspecialchars($bus['reject_reason'])) : "-" ?></td>
            </tr>
        </table>
        <a class="back" href="all_bus.php">Back</a>
    </div>
</body>

</html>