<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once "../db.php";
if (
    !isset($_SESSION['user_id'], $_SESSION['role']) ||
    $_SESSION['role'] !== 'driver'
) {
    header("Location: ../login.php");
    exit;
}
$driver_id = (int) $_SESSION['user_id'];
$bookings = [];
$message = "";
$stmt = $conn->prepare("
    SELECT bk.booking_id, bk.booking_group_id, bk.user_id, bk.schedule_id, bk.bus_name, bk.bus_number, bk.route, bk.travel_date, s.departure_time AS travel_time, bk.seat_number, bk.amount, bk.status, bk.created_at, bk.ticket_status, bk.scanned_at, bk.scanned_by, u.name AS passenger_name, u.email AS passenger_email, u.phone AS passenger_phone FROM bus_driver bd INNER JOIN bus b ON bd.bus_id = b.bus_id INNER JOIN bookings bk ON bk.bus_number = b.bus_number INNER JOIN schedules s ON bk.schedule_id = s.schedule_id INNER JOIN users u ON bk.user_id = u.user_id WHERE bd.driver_id = ? ORDER BY bk.travel_date DESC, s.departure_time ASC, bk.created_at DESC");
$stmt->bind_param("i", $driver_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $bookings[] = $row;
}
$stmt->close();
$total_bookings = count($bookings);
function e($value)
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Passenger Bookings</title>
    <link rel="stylesheet" href="booking.css">
</head>

<body>
    <?php include "driver_header.php"; ?>
    <div class="container">
        <h2 class="page-title">Passenger Bookings</h2>
        <div class="summary">
            <h3>Total Bookings: <?= $total_bookings ?></h3>
            <p>View passenger details, booking status and ticket check-in status.</p>
        </div>
        <div class="table-wrapper">
            <?php if (!empty($bookings)): ?>
                <p><strong>Bus:</strong> <?= e($bookings[0]['bus_name']) ?></p>
                <p><strong>Bus Number:</strong> <?= e($bookings[0]['bus_number']) ?></p>
                <p><strong>Route:</strong> <?= e($bookings[0]['route']) ?></p>
                <p><strong>Travel Date:</strong> <?= e($bookings[0]['travel_date']) ?></p>
                <p><strong>Bus Departure Time:</strong> <?= !empty($bookings[0]['travel_time']) ? date('h:i A', strtotime($bookings[0]['travel_time'])) : '-' ?> </p>
            <?php endif; ?>
            <table>
                <thead>
                    <tr>
                        <th>S.No</th>
                        <th>Passenger</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Seat</th>
                        <th>Amount</th>
                        <th>Booking Status</th>
                        <th>Ticket Status</th>
                        <th>Scanned At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($bookings)): ?>
                        <tr>
                            <td colspan="13" class="empty">No passenger bookings found for your assigned buses.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($bookings as $index => $booking): ?>
                            <?php
                            $status = strtolower($booking['status'] ?? '');
                            $ticket_status = strtolower($booking['ticket_status'] ?? '');
                            if ($status === 'pending') {
                                $booking_label = 'Pending';
                                $booking_class = 'pending';
                            } elseif ($status === 'confirmed') {
                                $booking_label = 'Confirmed';
                                $booking_class = 'confirmed';
                            } elseif ($status === 'cancelled') {
                                $booking_label = 'Cancelled';
                                $booking_class = 'cancelled';
                            } else {
                                $booking_label = ucfirst($status ?: 'Unknown');
                                $booking_class = 'pending';
                            }
                            if ($status === 'cancelled' || $ticket_status === 'cancelled') {
                                $ticket_label = 'Cancelled';
                                $ticket_class = 'cancelled';
                            } elseif ($status === 'pending') {
                                $ticket_label = 'Waiting';
                                $ticket_class = 'waiting';
                            } elseif ($status === 'confirmed' && $ticket_status === 'used') {
                                $ticket_label = 'Checked In';
                                $ticket_class = 'used';
                            } elseif ($status === 'confirmed' && $ticket_status === 'active') {
                                $ticket_label = 'Not Checked In';
                                $ticket_class = 'waiting';
                            } else {
                                $ticket_label = ucfirst($ticket_status ?: 'Unknown');
                                $ticket_class = 'pending';
                            }
                            ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td><?= e($booking['passenger_name']) ?></td>
                                <td><?= e($booking['passenger_email']) ?></td>
                                <td><?= e($booking['passenger_phone']) ?></td>
                                <td><?= e($booking['seat_number']) ?></td>
                                <td>Rs. <?= number_format((float)$booking['amount'], 2) ?></td>
                                <td><span class="badge <?= e($booking_class) ?>"> <?= e($booking_label) ?></span></td>
                                <td> <span class="badge <?= e($ticket_class) ?>"> <?= e($ticket_label) ?> </span></td>
                                <td> <?= !empty($booking['scanned_at']) ? e($booking['scanned_at']) : '-' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>

</html>