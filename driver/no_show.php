<?php
session_start();
require_once "../db.php";

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'driver') {
    header("Location: ../login.php");
    exit;
}

$driver_id = (int)$_SESSION['user_id'];
$group_id = trim($_GET['group_id'] ?? '');

if ($group_id === '') die("Invalid Booking Group ID.");

$sql = "SELECT bk.booking_id,bk.booking_group_id,bk.bus_name,bk.bus_number,bk.route,bk.travel_date,bk.seat_number,bk.status,bk.ticket_status,u.name AS passenger_name
FROM bookings bk
INNER JOIN users u ON bk.user_id=u.user_id
INNER JOIN bus b ON bk.bus_number=b.bus_number
INNER JOIN bus_driver bd ON bd.bus_id=b.bus_id
WHERE bk.booking_group_id=? AND bd.driver_id=? LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $group_id, $driver_id);
$stmt->execute();
$result = $stmt->get_result();
$booking = $result->fetch_assoc();
$stmt->close();

if (!$booking) die("Booking not found.");

$status = strtolower($booking['status'] ?? '');
$ticket_status = strtolower($booking['ticket_status'] ?? '');

if ($status !== 'confirmed' && $status !== 'paid') die("Booking is not confirmed.");
if ($ticket_status === 'checked_in' || $ticket_status === 'completed') die("Passenger already checked in.");
if ($ticket_status === 'no_show') die("Passenger already marked as no-show.");

$stmt = $conn->prepare("UPDATE bookings SET ticket_status='no_show',scanned_at=NOW(),scanned_by=? WHERE booking_group_id=?");
$stmt->bind_param("is", $driver_id, $group_id);
$stmt->execute();
$stmt->close();
?>
<!DOCTYPE html>
<html>

<head>
    <title>No Show</title>
    <style>
        body {
            font-family: Arial;
            background: #f4f6f9;
            text-align: center;
            padding: 60px
        }

        .box {
            max-width: 500px;
            margin: auto;
            background: white;
            padding: 30px;
            border-radius: 10px
        }

        .success {
            background: #dcfce7;
            color: #166534;
            padding: 15px;
            border-radius: 8px
        }

        a {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 18px;
            background: #4413e5;
            color: white;
            text-decoration: none;
            border-radius: 6px
        }
    </style>
</head>

<body>
    <div class="box">
        <h2>No Show</h2>
        <div class="success">Passenger marked as no-show successfully.</div>
        <p><strong>Passenger:</strong> <?= htmlspecialchars($booking['passenger_name']) ?></p>
        <p><strong>Booking Group:</strong> <?= htmlspecialchars($group_id) ?></p>
        <p><strong>Bus:</strong> <?= htmlspecialchars($booking['bus_number']) ?></p>
        <p><strong>Seat:</strong> <?= htmlspecialchars($booking['seat_number']) ?></p>
        <a href="bookings.php">Back to Bookings</a>
    </div>
</body>

</html>