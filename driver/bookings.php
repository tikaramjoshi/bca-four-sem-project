<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once "../db.php";

if (!isset($_SESSION['user_id'], $_SESSION['role']) || $_SESSION['role'] !== 'driver') {
    header("Location: ../login.php");
    exit;
}

$driver_id = (int)$_SESSION['user_id'];
$bookings = [];
$stmt = $conn->prepare("
SELECT
bk.booking_id,
bk.booking_group_id,
bk.user_id,
bk.schedule_id,
bk.bus_name,
bk.bus_number,
bk.route,
bk.travel_date,
bk.seat_number,
bk.amount,
bk.status,
bk.created_at,
bk.ticket_status,
bk.scanned_at,
bk.scanned_by,
u.name AS passenger_name,
u.email AS passenger_email,
u.phone AS passenger_phone
FROM bookings bk
INNER JOIN users u ON bk.user_id=u.user_id
INNER JOIN bus_driver bd ON bk.bus_number=(
    SELECT b.bus_number FROM bus b WHERE b.bus_id=bd.bus_id LIMIT 1
)
WHERE bd.driver_id=?
AND bk.status='approved'
ORDER BY bk.travel_date DESC,bk.created_at DESC
");
$stmt->bind_param("i", $driver_id);
$stmt->execute();
$result = $stmt->get_result();

$bookings = [];
while ($row = $result->fetch_assoc()) {
    $bookings[] = $row;
}
$stmt->close();

$driver_bookings = count($bookings);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Passenger Bookings</title>
    <link rel="stylesheet" href="dashboard.css">
    <link rel="stylesheet" href="../include/message/mesage.css">
    <style>
        .booking-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            gap: 15px;
        }

        .booking-count {
            background: #eef2ff;
            color: #4413e5;
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: 600;
        }

        .booking-table {
            width: 100%;
            border-collapse: collapse;
        }

        .booking-table th {
            background: #f5f5f5;
            padding: 14px;
            text-align: left;
            font-size: 14px;
            white-space: nowrap;
        }

        .booking-table td {
            padding: 14px;
            border-bottom: 1px solid #eee;
            font-size: 14px;
        }

        .booking-table tr:hover {
            background: #fafafa;
        }

        .passenger-name {
            font-weight: 600;
        }

        .passenger-info {
            line-height: 1.6;
        }

        .route {
            font-weight: 600;
        }

        .route span {
            color: #777;
            padding: 0 5px;
        }

        .approved {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 15px;
            background: #d1e7dd;
            color: #146c43;
            font-size: 12px;
            font-weight: 600;
        }

        .empty-bookings {
            text-align: center;
            padding: 50px 20px;
            color: #777;
        }

        .empty-bookings h3 {
            margin-bottom: 8px;
            color: #555;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        .back-btn {
            display: inline-block;
            padding: 9px 16px;
            background: #4413e5;
            color: #fff;
            text-decoration: none;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        .back-btn:hover {
            color: #fff;
        }
    </style>
</head>

<body>
    <div class="container">

        <div class="booking-header">
            <div>
                <h1>Passenger Bookings</h1>
                <p>Approved tickets for passengers travelling on your assigned bus.</p>
            </div>
            <div class="booking-count">
                <?= $driver_bookings ?> Booking<?= $driver_bookings != 1 ? 's' : '' ?>
            </div>
        </div>

        <a href="dashboard.php" class="back-btn">Back</a>

        <div class="box">

            <?php if (!empty($bookings)): ?>

                <div class="table-wrapper">

                    <table class="booking-table">

                        <thead>
                            <tr>
                                <th>Booking ID</th>
                                <th>Passenger</th>
                                <th>Route</th>
                                <th>Travel Date</th>
                                <th>Departure</th>
                                <th>Bus</th>
                                <th>Ticket Price</th>
                                <th>Status</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($bookings as $booking): ?>

                                <tr>

                                    <td>
                                        #<?= htmlspecialchars($booking['booking_id']) ?>
                                    </td>

                                    <td>
                                        <div class="passenger-info">
                                            <div class="passenger-name">
                                                <?= htmlspecialchars($booking['passenger_name']) ?>
                                            </div>
                                            <div>
                                                <?= htmlspecialchars($booking['passenger_phone']) ?>
                                            </div>
                                            <div>
                                                <?= htmlspecialchars($booking['passenger_email']) ?>
                                            </div>
                                        </div>
                                    </td>

                                    <td>
                                        <div class="route">
                                            <?= htmlspecialchars($booking['from_city']) ?>
                                            <span>→</span>
                                            <?= htmlspecialchars($booking['to_city']) ?>
                                        </div>
                                    </td>

                                    <td>
                                        <?= date("d M Y", strtotime($booking['departure_date'])) ?>
                                    </td>

                                    <td>
                                        <?= date("h:i A", strtotime($booking['departure_time'])) ?>
                                    </td>

                                    <td>
                                        <div>
                                            <strong><?= htmlspecialchars($booking['bus_number']) ?></strong>
                                        </div>
                                        <div>
                                            <?= htmlspecialchars($booking['bus_name']) ?>
                                        </div>
                                    </td>

                                    <td>
                                        Rs. <?= number_format((float)$booking['ticket_price'], 2) ?>
                                    </td>

                                    <td>
                                        <span class="approved">
                                            <?= ucfirst(htmlspecialchars($booking['status'])) ?>
                                        </span>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <div class="empty-bookings">
                    <h3>No Approved Bookings</h3>
                    <p>No approved passenger ticket is available for your assigned bus.</p>
                </div>

            <?php endif; ?>

        </div>

    </div>

    <?php include "../include/message/code.php"; ?>

    <script>
        function toggleProfileMenu() {
            document.querySelector(".driver-profile").classList.toggle("active");
        }

        document.addEventListener("click", function(e) {
            const profile = document.querySelector(".driver-profile");
            if (profile && !profile.contains(e.target)) {
                profile.classList.remove("active");
            }
        });
    </script>

</body>

</html>