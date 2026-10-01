<?php
session_start();
require_once "../db.php";

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'driver') {
    header("Location: ../login.php");
    exit;
}

$driver_id = (int)$_SESSION['user_id'];
$group_id = trim($_GET['group_id'] ?? '');
$action = $_GET['action'] ?? '';
$first_scanned_at = null;

if ($group_id === '') {
    die("Invalid ticket. Booking Group ID is missing.");
}

$sql = "SELECT
            bk.booking_id,
            bk.booking_group_id,
            bk.user_id,
            bk.bus_name,
            bk.bus_number,
            bk.route,
            bk.travel_date,
            bk.seat_number,
            bk.amount,
            bk.status,
            bk.ticket_status,
            bk.scanned_at,
            bk.scanned_by,
            bk.created_at,
            u.name AS passenger_name,
            u.email AS passenger_email,
            u.phone AS passenger_phone
        FROM bookings bk
        INNER JOIN users u ON bk.user_id=u.user_id
        INNER JOIN bus b ON bk.bus_number=b.bus_number
        INNER JOIN bus_driver bd ON bd.bus_id=b.bus_id
        WHERE bk.booking_group_id=?
        AND bd.driver_id=?
        ORDER BY bk.booking_id ASC";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "si", $group_id, $driver_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$bookings = [];

while ($row = mysqli_fetch_assoc($result)) {
    $bookings[] = $row;
}

mysqli_stmt_close($stmt);

if (empty($bookings)) {
    $message = "This ticket does not belong to your assigned bus.";
    $message_type = "error";
} else {
    $valid_booking = false;
    $all_cancelled = true;
    $all_checked_in = true;

    foreach ($bookings as $booking) {
        $status = strtolower(trim($booking['status'] ?? ''));
        $ticket_status = strtolower(trim($booking['ticket_status'] ?? ''));

        if ($status === 'confirmed' || $status === 'paid') {
            $valid_booking = true;
        }

        if ($status !== 'cancelled') {
            $all_cancelled = false;
        }

        if ($ticket_status !== 'checked_in') {
            $all_checked_in = false;
        }
    }

    if ($all_cancelled) {
        $message = "This passenger ticket has been cancelled.";
        $message_type = "error";
    } elseif ($all_checked_in) {
        $message = "Passenger has already checked in.";
        $message_type = "warning";
        $first_scanned_at = $bookings[0]['scanned_at'] ?? null;
    } elseif ($valid_booking) {
        $status = strtolower(trim($bookings[0]['status'] ?? ''));
        $ticket_status = strtolower(trim($bookings[0]['ticket_status'] ?? ''));

        if ($status === 'cancelled') {
            $message = "This ticket has been cancelled.";
            $message_type = "error";
        } elseif ($status !== 'confirmed' && $status !== 'paid') {
            $message = "This ticket is not paid or confirmed.";
            $message_type = "warning";
        } elseif ($ticket_status === 'checked_in') {
            $message = "This passenger has already checked in.";
            $message_type = "warning";
            $first_scanned_at = $bookings[0]['scanned_at'] ?? null;
        } elseif ($action === 'checkin') {
            $update_sql = "UPDATE bookings
                           SET ticket_status='checked_in',
                               scanned_at=NOW(),
                               scanned_by=?
                           WHERE booking_group_id=?
                           AND (status='confirmed' OR status='paid')
                           AND (ticket_status='active' OR ticket_status='booked' OR ticket_status IS NULL)";

            $update_stmt = mysqli_prepare($conn, $update_sql);
            mysqli_stmt_bind_param($update_stmt, "is", $driver_id, $group_id);
            mysqli_stmt_execute($update_stmt);
            mysqli_stmt_close($update_stmt);

            $message = "Passenger successfully checked in.";
            $message_type = "success";
            $first_scanned_at = date("Y-m-d H:i:s");
        } else {
            $message = "Ticket is valid. Please verify the passenger.";
            $message_type = "success";
        }
    } else {
        $message = "This ticket is not paid or confirmed.";
        $message_type = "warning";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Verify Ticket</title>
    <style>
        body {
            font-family: Arial;
            background: #f4f6f9;
            text-align: center;
            padding-top: 60px
        }

        .box {
            max-width: 600px;
            margin: auto;
            background: white;
            padding: 35px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, .1)
        }

        h2 {
            margin-bottom: 15px
        }

        .success {
            background: #dcfce7;
            color: #166534;
            padding: 15px;
            border-radius: 8px
        }

        .warning {
            background: #fef3c7;
            color: #92400e;
            padding: 15px;
            border-radius: 8px
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
            padding: 15px;
            border-radius: 8px
        }

        .info {
            text-align: left;
            margin-top: 20px
        }

        .info p {
            padding: 8px;
            border-bottom: 1px solid #eee;
            margin: 0
        }

        .time {
            font-weight: bold;
            margin-top: 10px
        }

        a,
        button {
            display: inline-block;
            margin-top: 25px;
            padding: 12px 20px;
            background: #4413e5;
            color: white;
            text-decoration: none;
            border: 0;
            border-radius: 6px;
            font-size: 15px;
            cursor: pointer
        }

        .back {
            background: #666
        }
    </style>
</head>

<body>
    <div class="box">
        <h2><?= $message_type === 'success' ? 'Ticket Verification' : ($message_type === 'warning' ? 'Ticket Information' : 'Ticket Cancelled') ?></h2>

        <div class="<?= $message_type ?>">
            <strong><?= htmlspecialchars($message) ?></strong>

            <?php if ($first_scanned_at): ?>
                <div class="time">Check In: <?= htmlspecialchars(date("d M Y, h:i A", strtotime($first_scanned_at))) ?></div>
            <?php endif; ?>
        </div>

        <?php if (!empty($bookings)): ?>
            <div class="info">
                <p><strong>Passenger:</strong> <?= htmlspecialchars($bookings[0]['passenger_name'] ?? '') ?></p>
                <p><strong>Booking Group:</strong> <?= htmlspecialchars($group_id) ?></p>
                <p><strong>Bus:</strong> <?= htmlspecialchars($bookings[0]['bus_name'] ?? '') ?></p>
                <p><strong>Bus Number:</strong> <?= htmlspecialchars($bookings[0]['bus_number'] ?? '') ?></p>
                <p><strong>Route:</strong> <?= htmlspecialchars($bookings[0]['route'] ?? '') ?></p>
                <p><strong>Travel Date:</strong> <?= htmlspecialchars($bookings[0]['travel_date'] ?? '') ?></p>
                <p><strong>Seat:</strong> <?= htmlspecialchars($bookings[0]['seat_number'] ?? '') ?></p>
                <p><strong>Payment Status:</strong> <?= htmlspecialchars($bookings[0]['status'] ?? '') ?></p>
            </div>
        <?php endif; ?>

        <?php if ($message === "Ticket is valid. Please verify the passenger."): ?>
            <a href="verify_ticket.php?group_id=<?= urlencode($group_id) ?>&action=checkin">Verify / Check In</a>
        <?php endif; ?>

        <a href="scan_ticket.php" class="back">Back</a>
    </div>
</body>

</html>