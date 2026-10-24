<?php
session_start();
require_once "../db.php";
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'passenger') {
    header("Location: ../login.php");
    exit;
}
$user_id = (int)$_SESSION['user_id'];
$passenger_name = $conn->query("SELECT name FROM users WHERE user_id=$user_id")->fetch_assoc()['name'] ?? 'Passenger';
$sql = "SELECT booking_group_id, MIN(booking_id) AS booking_id, bus_name, bus_number, route, travel_date, GROUP_CONCAT(CASE WHEN status IN('confirmed','paid') THEN seat_number END ORDER BY CAST(seat_number AS UNSIGNED) SEPARATOR ', ') AS confirmed_seats, GROUP_CONCAT(CASE WHEN status='pending' THEN seat_number END ORDER BY CAST(seat_number AS UNSIGNED) SEPARATOR ', ') AS pending_seats, GROUP_CONCAT(CASE WHEN status='cancelled' THEN seat_number END ORDER BY CAST(seat_number AS UNSIGNED) SEPARATOR ', ') AS cancelled_seats, COUNT(*) AS total_seats, SUM(CASE WHEN status!='cancelled' THEN amount ELSE 0 END) AS total_amount, MIN(created_at) AS booking_date, SUM(status IN('confirmed','paid')) AS confirmed_count, SUM(status='pending') AS pending_count, SUM(status='cancelled') AS cancelled_count FROM bookings WHERE user_id=? GROUP BY booking_group_id,bus_name,bus_number,route,travel_date ORDER BY MIN(booking_id) DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Booking History</title>
    <link rel="stylesheet" href="booking_history.css">
    <link rel="stylesheet" href="dashboard.css">
</head>

<body>
    <?php include "pass_header.php"; ?>
    <div class="container">
        <div class="title">
            <h1><?= htmlspecialchars($passenger_name) ?> Booking History</h1>
            <p>View all your bus bookings and ticket details</p>
        </div>
        <?php if ($result->num_rows == 0): ?>
            <div class="empty">
                <h2>No Bookings Found</h2>
                <p>You have not made any bus bookings yet.</p>
                <a href="dashboard.php" class="book">Book a Bus</a>
            </div>
        <?php endif; ?>
        <?php while ($b = $result->fetch_assoc()): ?>
            <?php
            $confirmed = (int)$b['confirmed_count'];
            $pending = (int)$b['pending_count'];
            $cancelled = (int)$b['cancelled_count'];
            $total = (int)$b['total_seats'];
            if ($pending > 0) {
                $status = 'Waiting';
                $statusClass = 'pending-b';
            } elseif ($cancelled == $total) {
                $status = 'Cancelled';
                $statusClass = 'cancelled';
            } elseif ($cancelled > 0) {
                $status = 'Completed';
                $statusClass = 'completed';
            } elseif ($confirmed == $total) {
                $status = 'Confirmed';
                $statusClass = 'confirmed';
            } else {
                $status = 'Waiting';
                $statusClass = 'pending-b';
            }
            ?>
            <div class="card">
                <div class="top">
                    <div class="id">Booking No:# <?= htmlspecialchars($b['booking_id']) ?></div>
                    <div class="badge <?= $statusClass ?>"><?= htmlspecialchars($status) ?></div>
                </div>
                <div class="grid">
                    <div class="item">
                        <label>Booking ID</label>
                        <strong><?= htmlspecialchars($b['booking_group_id']) ?></strong>
                    </div>
                    <div class="item">
                        <label>Bus</label>
                        <strong><?= htmlspecialchars($b['bus_name']) ?></strong>
                    </div>
                    <div class="item">
                        <label>Bus Number</label>
                        <strong><?= htmlspecialchars($b['bus_number']) ?></strong>
                    </div>
                    <div class="item">
                        <label>Route</label>
                        <strong><?= htmlspecialchars($b['route']) ?></strong>
                    </div>
                    <div class="item">
                        <label>Travel Date</label>
                        <strong><?= date('d M Y', strtotime($b['travel_date'])) ?></strong>
                    </div>
                    <div class="item">
                        <label>Total Seats</label>
                        <strong><?= $total ?> Seats</strong>
                    </div>
                    <div class="item">
                        <label>Confirmed Seats</label>
                        <strong class="green"><?= htmlspecialchars($b['confirmed_seats'] ?: 'None') ?></strong>
                    </div>
                    <div class="item">
                        <label>Pending Seats</label>
                        <strong class="orange"><?= htmlspecialchars($b['pending_seats'] ?: 'None') ?></strong>
                    </div>
                    <div class="item">
                        <label>Cancelled Seats</label>
                        <strong class="red"><?= htmlspecialchars($b['cancelled_seats'] ?: 'None') ?></strong>
                    </div>
                    <div class="item">
                        <label>Total Amount</label>
                        <strong>Rs. <?= number_format((float)$b['total_amount'], 2) ?></strong>
                    </div>
                    <div class="item">
                        <label>Booking Date</label>
                        <strong><?= date('d M Y h:i A', strtotime($b['booking_date'])) ?></strong>
                    </div>
                </div>
                <div class="counts">
                    <span class="count cg">Confirmed: <?= $confirmed ?></span>
                    <span class="count co">Pending: <?= $pending ?></span>
                    <span class="count cr">Cancelled: <?= $cancelled ?></span>
                </div>
                <div class="actions">
                    <a class="ticket" href="ticket.php?group_id=<?= urlencode($b['booking_group_id']) ?>">View Ticket</a>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
    <?php include "pass_footer.php" ?>
</body>

</html>