<?php
session_start();
require_once "../db.php";
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'driver') {
    header("Location: ../login.php");
    exit;
}
$driver_id = (int)$_SESSION['user_id'];
$stmt = $conn->prepare("
    SELECT bd.bus_driver_id,bd.assigned_at,b.bus_id,b.bus_number,b.bus_name,b.bus_type,b.seats,b.bus_image,b.status,
    u.name AS owner_name,u.phone AS owner_phone,u.email AS owner_email
    FROM bus_driver bd
    INNER JOIN bus b ON bd.bus_id=b.bus_id
    LEFT JOIN users u ON b.owner_id=u.user_id
    WHERE bd.driver_id=?
    ORDER BY bd.assigned_at DESC
");
$stmt->bind_param("i", $driver_id);
$stmt->execute();
$result = $stmt->get_result();
$buses = [];
while ($row = $result->fetch_assoc()) {
    $buses[] = $row;
}
$total_buses = count($buses);
$current_bus = $total_buses > 0 ? $buses[0] : null;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>My Bus</title>
    <link rel="stylesheet" href="dashboard.css">
    <link rel="stylesheet" href="bus.css">
    <style>
        .cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
            margin-bottom: 20px;
        }


        .bus-box {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 25px;
        }

        .bus-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 8px;
        }

        .no-image {
            width: 100%;
            height: 200px;
            background: #eee;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            color: #777;
        }

        .info {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 18px;
        }

        .value {
            margin-top: 5px;
            font-weight: bold;
        }

        .history {
            margin-top: 20px;
        }

        .current {
            background: #d9f5e5;
            color: #08763d;
        }

        .previous {
            background: #eee;
            color: #555;
        }



        @media(max-width:700px) {
            .cards {
                grid-template-columns: 1fr;
            }

            .bus-box {
                grid-template-columns: 1fr;
            }

            .info {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <?php include "dri_header.php" ?>
    <div class="container">
        <div class="top">
            <h2>My Bus</h2>
            <a href="dashboard.php" class="back">Back</a>
        </div>
        <div class="cards">
            <div class="card">
                <div class="label">Total Assigned Buses</div>
                <h3><?= $total_buses ?></h3>
            </div>
            <div class="card">
                <div class="label">Current Bus</div>
                <h3><?= $current_bus ? htmlspecialchars($current_bus['bus_number']) : 'No Bus' ?></h3>
            </div>
            <div class="card">
                <div class="label">Bus Type</div>
                <h3><?= $current_bus ? htmlspecialchars($current_bus['bus_type']) : '-' ?></h3>
            </div>
        </div>
        <div class="card">
            <h3>Current Assigned Bus</h3>
            <?php if ($current_bus): ?>
                <div class="bus-box">
                    <div>
                        <?php if (!empty($current_bus['bus_image'])): ?>
                            <img src="../uploads/bus/<?= htmlspecialchars($current_bus['bus_image']) ?>" class="bus-image">
                        <?php else: ?>
                            <div class="no-image">No Bus Image</div>
                        <?php endif; ?>
                    </div>
                    <div class="info">
                        <div>
                            <div class="label">Bus Number</div>
                            <div class="value"><?= htmlspecialchars($current_bus['bus_number']) ?></div>
                        </div>
                        <div>
                            <div class="label">Bus Name</div>
                            <div class="value"><?= htmlspecialchars($current_bus['bus_name']) ?></div>
                        </div>
                        <div>
                            <div class="label">Bus Type</div>
                            <div class="value"><?= htmlspecialchars($current_bus['bus_type']) ?></div>
                        </div>
                        <div>
                            <div class="label">Total Seats</div>
                            <div class="value"><?= htmlspecialchars($current_bus['seats']) ?></div>
                        </div>
                        <div>
                            <div class="label">Owner Name</div>
                            <div class="value"><?= htmlspecialchars($current_bus['owner_name'] ?? '-') ?></div>
                        </div>
                        <div>
                            <div class="label">Owner Phone</div>
                            <div class="value"><?= htmlspecialchars($current_bus['owner_phone'] ?? '-') ?></div>
                        </div>
                        <div>
                            <div class="label">Owner Email</div>
                            <div class="value"><?= htmlspecialchars($current_bus['owner_email'] ?? '-') ?></div>
                        </div>
                        <div>
                            <div class="label">Bus Status</div>
                            <div class="value"><?= htmlspecialchars($current_bus['status']) ?></div>
                        </div>
                        <div>
                            <div class="label">Assigned Date</div>
                            <div class="value"><?= date("d M Y", strtotime($current_bus['assigned_at'])) ?></div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="empty">
                    No bus is currently assigned to you.
                </div>
            <?php endif; ?>
        </div>
        <div class="card history">
            <h3>Bus Assignment History</h3>
            <?php if ($total_buses > 0): ?>
                <div class="table-box">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Bus Number</th>
                                <th>Bus Name</th>
                                <th>Type</th>
                                <th>Seats</th>
                                <th>Owner</th>
                                <th>Assigned Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($buses as $i => $bus): ?>
                                <tr>
                                    <td><?= $i + 1 ?></td>
                                    <td><?= htmlspecialchars($bus['bus_number']) ?></td>
                                    <td><?= htmlspecialchars($bus['bus_name']) ?></td>
                                    <td><?= htmlspecialchars($bus['bus_type']) ?></td>
                                    <td><?= htmlspecialchars($bus['seats']) ?></td>
                                    <td><?= htmlspecialchars($bus['owner_name'] ?? '-') ?></td>
                                    <td><?= date("d M Y", strtotime($bus['assigned_at'])) ?></td>
                                    <td>
                                        <?php if ($i === 0): ?>
                                            <span class="status current">Current</span>
                                        <?php else: ?>
                                            <span class="status previous">Previous</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty"> No bus assignment history found. </div>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>