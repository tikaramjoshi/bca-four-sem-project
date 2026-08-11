<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != "admin") {
    header("Location: ../login.php");
    exit();
}
require_once "../db.php";
$sql = "
    SELECT
        bus.*,
        users.name AS owner_name,
        users.email AS owner_email,
        users.phone AS owner_phone
    FROM bus
    INNER JOIN users
        ON bus.owner_id = users.user_id
    WHERE bus.status = 'pending'
    ORDER BY bus.bus_id DESC
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
    <title>Pending Buses</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            margin: 0;
            padding: 0;
        }

        .box {
            width: 95%;
            max-width: 1200px;
            margin: 40px auto;
            background: #fff;
            padding: 25px;
            border-radius: 10px;
        }

        h2 {
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
            vertical-align: middle;
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

        .pending {
            color: #f39c12;
            font-weight: bold;
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

        .approve-btn {
            display: inline-block;
            padding: 8px 14px;
            background: #198754;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 14px;
        }

        .approve-btn:hover {
            background: #146c43;
        }

        .reject-btn {
            display: inline-block;
            padding: 8px 14px;
            background: #dc3545;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 14px;
        }

        .reject-btn:hover {
            background: #b02a37;
        }

        .actions {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
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
                                <td>
                                    <?= htmlspecialchars($bus['bus_id']) ?>
                                </td>
                                <td>
                                    <?php if (!empty($bus['bus_image'])): ?>
                                        <img
                                            src="../uploads/bus/<?= htmlspecialchars($bus['bus_image']) ?>"
                                            class="bus-image"
                                            alt="Bus Image">
                                    <?php else: ?>
                                        <img
                                            src="../images/no-image.png"
                                            class="bus-image"
                                            alt="No Image">
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($bus['bus_number']) ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($bus['bus_name']) ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($bus['bus_type']) ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($bus['seats']) ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($bus['owner_name']) ?>
                                </td>
                                <td>
                                    <span class="pending">
                                        Pending
                                    </span>
                                </td>
                                <td>
                                    <div class="actions">
                                        <a
                                            href="view_bus.php?id=<?= $bus['bus_id'] ?>"
                                            class="view-btn">
                                            View
                                        </a>
                                        <a
                                            href="approve_bus.php?id=<?= $bus['bus_id'] ?>"
                                            class="approve-btn"
                                            onclick="return confirm('Are you sure you want to approve this bus?');">
                                            Approve
                                        </a>
                                        <a
                                            href="reject_bus.php?id=<?= $bus['bus_id'] ?>"
                                            class="reject-btn">
                                            Reject
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" style="text-align:center; padding:25px;">
                                No pending buses found.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <a href="dashboard.php" class="back">
            ← Back to Dashboard
        </a>
    </div>
</body>

</html>