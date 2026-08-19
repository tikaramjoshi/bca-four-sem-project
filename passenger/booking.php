<?php
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'passenger') {
    header('Location: ../login.php');
    exit;
}
require_once __DIR__ . '/../db.php';
if (!$conn instanceof mysqli) {
    exit('Database connection is not available.');
}
$userId = (int)$_SESSION['user_id'];
if (isset($_POST['confirm_booking'])) {
    if (empty($_SESSION['pending_booking'])) {
        header('Location: dashboard.php');
        exit;
    }
    $booking = $_SESSION['pending_booking'];
    $scheduleId = (int)$booking['schedule_id'];
    $busId = (int)$booking['bus_id'];
    $seats = $booking['seats'];
    $busName = $booking['bus_name'];
    $busNumber = $booking['bus_number'];
    $route = $booking['route'];
    $travelDate = $booking['travel_date'];
    $amount = (float)$booking['amount'];
    if (!is_array($seats) || count($seats) < 1 || count($seats) > 4) {
        $_SESSION['booking_error'] = 'Please select 1 to 4 seats.';
        header("Location: seat_selection.php?schedule_id=$scheduleId&bus_id=$busId");
        exit;
    }
    $stmt = $conn->prepare("SELECT verification_status FROM users WHERE user_id=? AND role='passenger' LIMIT 1");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$user || strtolower($user['verification_status'] ?? '') !== 'verified') {
        $_SESSION['booking_error'] = 'Your account is not verified. You cannot book a seat.';
        header("Location: seat_selection.php?schedule_id=$scheduleId&bus_id=$busId");
        exit;
    }
    $stmt = $conn->prepare("SELECT schedule_id,bus_id,from_city,to_city,departure_date,departure_time,ticket_price,available_seats,status FROM schedules WHERE schedule_id=? AND bus_id=? AND status='active' LIMIT 1");
    $stmt->bind_param('ii', $scheduleId, $busId);
    $stmt->execute();
    $schedule = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$schedule) {
        $_SESSION['booking_error'] = 'This schedule is no longer available.';
        header("Location: seat_selection.php?schedule_id=$scheduleId&bus_id=$busId");
        exit;
    }
    $conn->begin_transaction();
    try {
        $checkStmt = $conn->prepare("SELECT booking_id,status FROM bookings WHERE schedule_id=? AND seat_number=? AND status IN ('pending','confirmed','paid') LIMIT 1 FOR UPDATE");
        if (!$checkStmt) {
            throw new Exception('Unable to check seat availability.');
        }
        foreach ($seats as $seat) {
            $seat = (string)$seat;
            $checkStmt->bind_param('is', $scheduleId, $seat);
            $checkStmt->execute();
            $seatResult = $checkStmt->get_result();
            if ($seatResult->num_rows > 0) {
                throw new Exception("Seat $seat has already been booked. Please select another seat.");
            }
        }
        $checkStmt->close();
        $countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM bookings WHERE schedule_id=? AND status IN ('pending','confirmed','paid') FOR UPDATE");
        if (!$countStmt) {
            throw new Exception('Unable to check available seats.');
        }
        $countStmt->bind_param('i', $scheduleId);
        $countStmt->execute();
        $countRow = $countStmt->get_result()->fetch_assoc();
        $countStmt->close();
        $stmt = $conn->prepare("SELECT b.seats FROM bus b WHERE b.bus_id=? AND b.status='approved' LIMIT 1");
        if (!$stmt) {
            throw new Exception('Unable to check bus information.');
        }
        $stmt->bind_param('i', $busId);
        $stmt->execute();
        $busData = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$busData) {
            throw new Exception('Bus is no longer available.');
        }
        $totalBusSeats = (int)$busData['seats'];
        $currentBooked = (int)($countRow['total'] ?? 0);
        $requestedSeats = count($seats);
        if (($currentBooked + $requestedSeats) > $totalBusSeats) {
            throw new Exception('Not enough available seats.');
        }
        $groupStmt = $conn->prepare("SELECT booking_group_id FROM bookings WHERE booking_group_id IS NOT NULL AND booking_group_id<>'' ORDER BY booking_id DESC LIMIT 1 FOR UPDATE");
        if (!$groupStmt) {
            throw new Exception('Unable to generate booking group.');
        }
        $groupStmt->execute();
        $lastGroupRow = $groupStmt->get_result()->fetch_assoc();
        $groupStmt->close();
        $nextGroupNumber = 1;
        if (!empty($lastGroupRow['booking_group_id']) && preg_match('/^BOOKING-(\d+)$/', $lastGroupRow['booking_group_id'], $matches)) {
            $nextGroupNumber = ((int)$matches[1]) + 1;
        }
        $bookingGroupId = 'BOOKING-' . str_pad($nextGroupNumber, 2, '0', STR_PAD_LEFT);
        $bookingStatus = 'pending';
        $insertStmt = $conn->prepare("INSERT INTO bookings (booking_group_id,user_id,schedule_id,bus_name,bus_number,route,travel_date,seat_number,amount,status) VALUES (?,?,?,?,?,?,?,?,?,?)");
        if (!$insertStmt) {
            throw new Exception('Unable to prepare booking.');
        }
        foreach ($seats as $seat) {
            $seat = (string)$seat;
            $insertStmt->bind_param('sissssssds', $bookingGroupId, $userId, $scheduleId, $busName, $busNumber, $route, $travelDate, $seat, $amount, $bookingStatus);
            if (!$insertStmt->execute()) {
                throw new Exception('Unable to complete booking.');
            }
        }
        $insertStmt->close();
        $newBookedCount = $currentBooked + $requestedSeats;
        $newAvailableSeats = max(0, $totalBusSeats - $newBookedCount);
        $updateStmt = $conn->prepare("UPDATE schedules SET available_seats=? WHERE schedule_id=?");
        if (!$updateStmt) {
            throw new Exception('Unable to update available seats.');
        }
        $updateStmt->bind_param('ii', $newAvailableSeats, $scheduleId);
        if (!$updateStmt->execute()) {
            throw new Exception('Unable to update available seats.');
        }
        $updateStmt->close();
        $conn->commit();
        unset($_SESSION['pending_booking']);
        $_SESSION['booking_success'] = [
            'booking_group_id' => $bookingGroupId,
            'bus_name' => $busName,
            'bus_number' => $busNumber,
            'route' => $route,
            'travel_date' => $travelDate,
            'seats' => implode(', ', $seats),
            'total' => $amount * count($seats)
        ];
        header('Location: booking_success.php');
        exit;
    } catch (Throwable $error) {
        $conn->rollback();
        $_SESSION['booking_error'] = $error->getMessage();
        header("Location: seat_selection.php?schedule_id=$scheduleId&bus_id=$busId");
        exit;
    }
}
$scheduleId = filter_input(INPUT_POST, 'schedule_id', FILTER_VALIDATE_INT);
if (!$scheduleId) {
    $scheduleId = filter_input(INPUT_GET, 'schedule_id', FILTER_VALIDATE_INT);
}
$busId = filter_input(INPUT_POST, 'bus_id', FILTER_VALIDATE_INT);
if (!$busId) {
    $busId = filter_input(INPUT_GET, 'bus_id', FILTER_VALIDATE_INT);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['confirm_booking'])) {
    $seats = $_POST['seat_numbers'] ?? [];
    $seats = array_values(array_unique(array_filter($seats, function ($seat) {
        return preg_match('/^[1-9][0-9]*$/', (string)$seat);
    })));
    if (!$scheduleId || !$busId || !$seats || count($seats) > 4) {
        exit('Please select 1 to 4 seats.');
    }
    $stmt = $conn->prepare("SELECT s.schedule_id,s.bus_id,s.from_city,s.to_city,s.departure_date,s.departure_time,s.ticket_price,s.available_seats,s.status,b.bus_name,b.bus_number,b.bus_type,b.seats FROM schedules s INNER JOIN bus b ON s.bus_id=b.bus_id WHERE s.schedule_id=? AND s.bus_id=? AND s.status='active' AND b.status='approved' LIMIT 1");
    $stmt->bind_param('ii', $scheduleId, $busId);
    $stmt->execute();
    $trip = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$trip) {
        exit('Schedule not found or bus is not available.');
    }
    $stmt = $conn->prepare("SELECT COUNT(*) AS booked_count FROM bookings WHERE schedule_id=? AND status IN ('pending','confirmed','paid')");
    $stmt->bind_param('i', $scheduleId);
    $stmt->execute();
    $bookedData = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $bookedCount = (int)($bookedData['booked_count'] ?? 0);
    $totalSeats = (int)$trip['seats'];
    $availableSeats = max(0, $totalSeats - $bookedCount);
    if ($availableSeats < count($seats)) {
        exit('Not enough available seats.');
    }
    $checkStmt = $conn->prepare("SELECT seat_number FROM bookings WHERE schedule_id=? AND seat_number=? AND status IN ('pending','confirmed','paid') LIMIT 1");
    foreach ($seats as $seat) {
        $seat = (string)$seat;
        $checkStmt->bind_param('is', $scheduleId, $seat);
        $checkStmt->execute();
        if ($checkStmt->get_result()->num_rows > 0) {
            $checkStmt->close();
            exit("Seat $seat has already been booked. Please go back and select another seat.");
        }
    }
    $checkStmt->close();
    $from = trim($trip['from_city']);
    $to = trim($trip['to_city']);
    $route = $from . ' to ' . $to;
    $travelDate = $trip['departure_date'];
    $busName = $trip['bus_name'];
    $busNumber = $trip['bus_number'];
    $amount = (float)$trip['ticket_price'];
    if (!$scheduleId || !$busId || !$busNumber || !$travelDate) {
        exit('Schedule details are incomplete.');
    }
    $_SESSION['pending_booking'] = [
        'schedule_id' => $scheduleId,
        'bus_id' => $busId,
        'seats' => $seats,
        'bus_name' => $busName,
        'bus_number' => $busNumber,
        'route' => $route,
        'travel_date' => $travelDate,
        'amount' => $amount
    ];
}
if (empty($_SESSION['pending_booking'])) {
    header('Location: dashboard.php');
    exit;
}
$booking = $_SESSION['pending_booking'];
$scheduleId = (int)$booking['schedule_id'];
$busId = (int)$booking['bus_id'];
$seats = $booking['seats'];
$busName = $booking['bus_name'];
$busNumber = $booking['bus_number'];
$route = $booking['route'];
$travelDate = $booking['travel_date'];
$amount = (float)$booking['amount'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Confirm Booking</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0
        }

        body {
            min-height: 100vh;
            display: grid;
            place-items: center;
            background: #f4f6f8;
            font-family: Arial, sans-serif;
            color: #263238
        }

        .box {
            width: 420px;
            max-width: calc(100% - 30px);
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 3px 15px #0002;
            text-align: center
        }

        h2 {
            margin-bottom: 22px;
            color: #1560bd
        }

        .info {
            text-align: left;
            background: #f7f9fc;
            padding: 15px;
            border-radius: 8px
        }

        .info p {
            margin: 9px 0;
            color: #475569
        }

        .info strong {
            color: #263238
        }

        .total {
            margin-top: 15px;
            padding: 13px;
            background: #e8f5e9;
            color: #198754;
            border-radius: 7px;
            font-size: 18px;
            font-weight: bold
        }

        .buttons {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 25px
        }

        button,
        a {
            border: 0;
            padding: 11px 20px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: bold;
            cursor: pointer
        }

        .confirm {
            background: #0f766e;
            color: #fff
        }

        .confirm:hover {
            background: #0b5f59
        }

        .cancel {
            background: #64748b;
            color: #fff
        }

        .cancel:hover {
            background: #475569
        }

        .message {
            background: #fee2e2;
            color: #991b1b;
            padding: 14px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 600
        }
    </style>
</head>

<body>
    <div class="box">
        <?php if (!empty($_SESSION['booking_error'])): ?>
            <div class="message"><?= htmlspecialchars($_SESSION['booking_error']) ?></div>
        <?php unset($_SESSION['booking_error']);
        endif; ?>
        <h2>Confirm Booking</h2>
        <div class="info">
            <p><strong>Bus:</strong> <?= htmlspecialchars($busName) ?></p>
            <p><strong>Bus Number:</strong> <?= htmlspecialchars($busNumber) ?></p>
            <p><strong>Route:</strong> <?= htmlspecialchars($route) ?></p>
            <p><strong>Date:</strong> <?= htmlspecialchars($travelDate) ?></p>
            <p><strong>Seats:</strong> <?= htmlspecialchars(implode(', ', $seats)) ?></p>
            <p><strong>Price:</strong> NPR <?= number_format($amount, 2) ?> per seat</p>
        </div>
        <div class="total">Total: NPR <?= number_format($amount * count($seats), 2) ?></div>
        <div class="buttons">
            <form method="post">
                <input type="hidden" name="confirm_booking" value="1">
                <button type="submit" class="confirm">Confirm Booking</button>
            </form>
            <a href="seat_selection.php?schedule_id=<?= $scheduleId ?>&bus_id=<?= $busId ?>" class="cancel">Cancel</a>
        </div>
    </div>
</body>

</html>