<?php
session_start();
require_once "../db.php";
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'driver') {
    header("Location: ../login.php");
    exit;
}
$driver_id = (int)$_SESSION['user_id'];
$stmt = $conn->prepare("
    SELECT bd.bus_driver_id, bd.assigned_at, b.bus_id, b.bus_number, b.bus_name, b.bus_type, b.seats, b.bus_image, b.status, u.name AS owner_name, u.phone AS owner_phone, u.email AS owner_email, (     SELECT COUNT(*)     FROM bookings bk     INNER JOIN schedules s2 ON bk.schedule_id = s2.schedule_id     WHERE s2.bus_id = b.bus_id       AND bk.status IN ('approved', 'confirmed', 'paid') ) AS total_passengers, (     SELECT s.departure_date     FROM schedules s     WHERE s.bus_id = b.bus_id ORDER BY CASE WHEN s.departure_date >= CURDATE() THEN 0 ELSE 1 END, CASE WHEN s.departure_date >= CURDATE() THEN s.departure_date END ASC, s.departure_date DESC LIMIT 1 ) AS trip_date FROM bus_driver bd INNER JOIN bus b ON bd.bus_id = b.bus_id LEFT JOIN users u ON b.owner_id = u.user_id WHERE bd.driver_id = ? ORDER BY bd.assigned_at DESC
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
    <link rel="stylesheet" href="../driver/bus.css">

</head>

<body>
    <?php include "driver_header.php" ?>
    <div class="container">
        <div class="top">
            <h2>My Bus</h2>
            <a href="dashboard.php" class="back"><i class="fa fa-arrow-left"></i>&nbsp; Back</a>
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
                <div class="empty"> No bus is currently assigned to you. </div>
            <?php endif; ?>
        </div>
        <div class="card history">
            <h3>Bus Assignment History</h3>
            <?php if ($total_buses > 0): ?>
                <div class="table-box">
                    <table>
                        <thead>
                            <tr>
                                <th>S.No</th>
                                <th>Bus Number</th>
                                <th>Bus Name</th>
                                <th>Type</th>
                                <th>Seats</th>
                                <th>Owner</th>
                                <th>Assigned Date</th>
                                <th>Trip Date</th>
                                <th>Total Passengers</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($buses as $i => $bus): ?>
                                <?php
                                $trip_date = $bus['trip_date'] ?? '';
                                $today = date('Y-m-d');

                                if (empty($trip_date)) {
                                    $trip_status = 'No Trip';
                                } elseif ($trip_date > $today) {
                                    $trip_status = 'Upcoming';
                                } elseif ($trip_date == $today) {
                                    $trip_status = 'Current';
                                } else {
                                    $trip_status = 'Previous';
                                }

                                $status_class = strtolower(str_replace(' ', '-', $trip_status));
                                ?>

                                <tr>
                                    <td><?= $i + 1 ?></td>
                                    <td><?= htmlspecialchars($bus['bus_number']) ?></td>
                                    <td><?= htmlspecialchars($bus['bus_name']) ?></td>
                                    <td><?= htmlspecialchars($bus['bus_type']) ?></td>
                                    <td><?= htmlspecialchars($bus['seats']) ?></td>
                                    <td><?= htmlspecialchars($bus['owner_name'] ?? '-') ?></td>
                                    <td><?= !empty($bus['assigned_at']) ? date("d M Y", strtotime($bus['assigned_at'])) : '-' ?></td>
                                    <td><?= $trip_date !== '' ? date("d M Y", strtotime($trip_date)) : '-' ?></td>
                                    <td><?= (int)($bus['total_passengers'] ?? 0) ?></td>
                                    <td>
                                        <span class="status <?= htmlspecialchars($status_class) ?>">
                                            <?= htmlspecialchars($trip_status) ?>
                                        </span>
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