<?php
session_start();
require_once "../db.php";
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'driver') {
    header("Location: ../login.php");
    exit;
}
$driver_id = (int)$_SESSION['user_id'];
$current_bus = null;
$stmt = $conn->prepare("SELECT b.bus_id,b.bus_number,b.bus_name,b.bus_type,b.seats FROM bus_driver bd INNER JOIN bus b ON bd.bus_id=b.bus_id WHERE bd.driver_id=? ORDER BY bd.assigned_at DESC LIMIT 1");
$stmt->bind_param("i", $driver_id);
$stmt->execute();
$bus_result = $stmt->get_result();
if ($bus_result->num_rows > 0) {
    $current_bus = $bus_result->fetch_assoc();
}
$total_trips = 0;
$completed_trips = 0;
$total_bookings = 0;
$total_passengers = 0;
$trips = [];
if ($current_bus) {
    $bus_id = (int)$current_bus['bus_id'];
    $stmt = $conn->prepare("SELECT s.schedule_id,s.from_city,s.to_city,s.departure_date,s.departure_time,s.ticket_price,s.available_seats,s.status,b.bus_number,COUNT(CASE WHEN bk.status!='cancelled' THEN 1 END) AS total_bookings,COUNT(CASE WHEN bk.status!='cancelled' THEN 1 END) AS passenger_count FROM schedules s INNER JOIN bus b ON s.bus_id=b.bus_id LEFT JOIN bookings bk ON s.schedule_id=bk.schedule_id WHERE s.bus_id=? GROUP BY s.schedule_id ORDER BY s.departure_date DESC,s.departure_time DESC");
    $stmt->bind_param("i", $bus_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $trips[] = $row;
        $total_trips++;
        $trip_datetime = $row['departure_date'] . " " . $row['departure_time'];
        if (strtotime($trip_datetime) < time()) {
            $completed_trips++;
        }
        $total_bookings += (int)$row['total_bookings'];
        $total_passengers += (int)$row['passenger_count'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>My Trips</title>
    <link rel="stylesheet" href="dashboard.css">
    <link rel="stylesheet" href="bus.css">
    <style>
        .label {
            color: #777;
            font-size: 14px
        }

        .card h3 {
            margin: 8px 0 0
        }

        .bus-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px
        }

        .bus-title h3 {
            margin: 0
        }

        .bus-number {
            background: #eef0ff;
            color: #4413e5;
            padding: 7px 12px;
            border-radius: 6px;
            font-weight: bold
        }

        .table-box {
            overflow-x: auto
        }

        table {
            width: 100%;
            border-collapse: collapse
        }

        .completed {
            background: #d9f5e5;
            color: #08763d
        }

        .upcoming {
            background: #fff0c2;
            color: #856404
        }

        .cancelled {
            background: #f8d7da;
            color: #842029
        }

        .route {
            font-weight: bold
        }

        .booking {
            font-weight: bold
        }

        @media(max-width:900px) {
            .cards {
                grid-template-columns: repeat(2, 1fr)
            }
        }

        @media(max-width:600px) {
            .cards {
                grid-template-columns: 1fr
            }

            .container {
                width: 95%
            }
        }
    </style>
</head>

<body>
    <?php include "dri_header.php" ?>
    <div class="container">
        <div class="top">
            <h2>My Trips</h2>
            <a href="dashboard.php" class="back">Back</a>
        </div>
        <div class="cards">
            <div class="card">
                <div class="label">Total Trips</div>
                <h3><?= $total_trips ?></h3>
            </div>
            <div class="card">
                <div class="label">Completed Trips</div>
                <h3><?= $completed_trips ?></h3>
            </div>
            <div class="card">
                <div class="label">Total Booking</div>
                <h3><?= $total_bookings ?></h3>
            </div>
            <div class="card">
                <div class="label">Total Passengers</div>
                <h3><?= $total_passengers ?></h3>
            </div>
        </div>
        <div class="card">
            <?php if ($current_bus): ?>
                <div class="bus-title">
                    <h3>Current Bus Trips</h3>
                    <span class="bus-number"><?= htmlspecialchars($current_bus['bus_number']) ?></span>
                </div>
                <div class="table-box">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>From → To</th>
                                <th>Date</th>
                                <th>Departure</th>
                                <th>Arrival</th>
                                <th>Bus Number</th>
                                <th>Booking</th>
                                <th>Passengers</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($trips) > 0): ?>
                                <?php foreach ($trips as $i => $trip): ?>
                                    <?php
                                    $trip_datetime = $trip['departure_date'] . " " . $trip['departure_time'];
                                    $is_completed = strtotime($trip_datetime) < time();
                                    ?>
                                    <tr>
                                        <td><?= $i + 1 ?></td>
                                        <td class="route"><?= htmlspecialchars($trip['from_city']) ?> → <?= htmlspecialchars($trip['to_city']) ?></td>
                                        <td><?= date("d M Y", strtotime($trip['departure_date'])) ?></td>
                                        <td><?= date("h:i A", strtotime($trip['departure_time'])) ?></td>
                                        <td>-</td>
                                        <td><?= htmlspecialchars($trip['bus_number']) ?></td>
                                        <td class="booking"><?= (int)$trip['total_bookings'] ?></td>
                                        <td class="booking"><?= (int)$trip['passenger_count'] ?></td>
                                        <td>
                                            <?php if ($trip['status'] === 'cancelled'): ?>
                                                <span class="status cancelled">Cancelled</span>
                                            <?php elseif ($is_completed): ?>
                                                <span class="status completed">Completed</span>
                                            <?php else: ?>
                                                <span class="status upcoming">Upcoming</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="empty">No trips found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty">
                    No bus is currently assigned to you.
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>