<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != "admin") {
    header("Location: ../login.php");
    exit();
}

require_once "../db.php";
$sql = " SELECT  bus.*, users.name AS owner_name, users.email AS owner_email, users.phone AS owner_phone FROM bus INNER JOIN users  ON bus.owner_id = users.user_id ORDER BY bus.bus_id DESC
";
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
    <title>All Buses</title>
    <link rel="stylesheet" href="dashboard_admin.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            margin: 0;
            padding: 0;
        }

        /* .box {
            width: 95%;
            max-width: 1200px;
            margin: 40px auto;
            background: #fff;
            padding: 25px;
            border-radius: 10px;
        } */

        .title {
            text-align: center;
            margin-bottom: 25px;
            color: #333;
        }

        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #1560BD;
            color: white;
            padding: 12px;
            text-align: left;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid #ddd;
        }

        tr:hover {
            background: #f8f9fa;
        }

        .bus-image {
            width: 90px;
            height: 60px;
            object-fit: cover;
            border-radius: 6px;
            border: 1px solid #ddd;
        }

        .status {
            font-weight: bold;
        }

        .approved {
            color: green;
        }

        .pending {
            color: orange;
        }

        .rejected {
            color: red;
        }

        .view-btn {
            display: inline-block;
            padding: 8px 14px;
            background: #1560BD;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 14px;
        }

        .view-btn:hover {
            background: #0d4f9c;
        }

        .back {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 20px;
            background: #555;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }

        .back:hover {
            background: #333;
        }
    </style>
</head>

<body>
    <?php include "admin_header.php"; ?>
    <div class="content">
        <div class="box">
            <h2 class="title">All Bus Details</h2>
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
                                    <td> <?= htmlspecialchars($bus['bus_id']) ?></td>
                                    <td> <?php if (!empty($bus['bus_image'])): ?>
                                            <img src="../uploads/bus/<?= htmlspecialchars($bus['bus_image']) ?>" class="bus-image" alt="Bus Image"> <?php else: ?>
                                            <img src="../images/no-image.png" class="bus-image" alt="No Image"> <?php endif; ?>
                                    </td>
                                    <td> <?= htmlspecialchars($bus['bus_number']) ?>
                                    </td>
                                    <td> <?= htmlspecialchars($bus['bus_name']) ?> </td>
                                    <td> <?= htmlspecialchars($bus['bus_type']) ?> </td>
                                    <td> <?= htmlspecialchars($bus['seats']) ?> </td>
                                    <td> <?= htmlspecialchars($bus['owner_name']) ?> </td>
                                    <td>
                                        <?php if ($bus['status'] == "approved"): ?>
                                            <span class="status approved"> Approved </span>
                                        <?php elseif ($bus['status'] == "pending"): ?>
                                            <span class="status pending"> Pending </span>
                                        <?php else: ?>
                                            <span class="status rejected"> Rejected </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="view_bus.php?id=<?= $bus['bus_id'] ?>" class="view-btn"> View Details</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" style="text-align:center;"> No buses found. </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <a href="dashboard.php" class="back"> Back to Home </a>
        </div>
    </div>
</body>

</html>