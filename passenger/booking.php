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
    $amount = $booking['amount'];

    $stmt = $conn->prepare("
        SELECT verification_status
        FROM users
        WHERE user_id = ?
        AND role = 'passenger'
        LIMIT 1
    ");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    $status = strtolower($user['verification_status'] ?? 'pending');

    if ($status !== 'verified') {

        if ($status === 'rejected') {
            $_SESSION['booking_error'] = 'Your account status is REJECTED. You cannot book a seat.';
        } elseif ($status === 'pending') {
            $_SESSION['booking_error'] = 'Your account status is PENDING. Please wait for admin verification before booking.';
        } else {
            $_SESSION['booking_error'] = 'Your account is not verified. You cannot book a seat.';
        }

        header("Location: booking.php?schedule_id=$scheduleId&bus_id=$busId");
        exit;
    }

    $conn->begin_transaction();

    try {
        $checkStmt = $conn->prepare("
            SELECT booking_id
            FROM bookings
            WHERE bus_number = ?
            AND route = ?
            AND travel_date = ?
            AND seat_number = ?
            AND (status IS NULL OR status != 'cancelled')
            LIMIT 1
            FOR UPDATE
        ");

        $insertStmt = $conn->prepare("
            INSERT INTO bookings
            (
                user_id,
                bus_name,
                bus_number,
                route,
                travel_date,
                seat_number,
                amount,
                status
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
        ");

        foreach ($seats as $seat) {

            $checkStmt->bind_param(
                'ssss',
                $busNumber,
                $route,
                $travelDate,
                $seat
            );

            $checkStmt->execute();

            if ($checkStmt->get_result()->num_rows > 0) {
                throw new Exception(
                    "Seat $seat has already been booked. Please select another seat."
                );
            }

            $insertStmt->bind_param(
                'isssssd',
                $userId,
                $busName,
                $busNumber,
                $route,
                $travelDate,
                $seat,
                $amount
            );

            if (!$insertStmt->execute()) {
                throw new Exception('Unable to complete booking.');
            }
        }

        $conn->commit();

        unset($_SESSION['pending_booking']);

        $_SESSION['booking_success'] = [
            'bus_name' => $busName,
            'route' => $route,
            'seats' => implode(', ', $seats),
            'total' => $amount * count($seats)
        ];

        header('Location: booking_success.php');
        exit;
    } catch (Throwable $error) {

        $conn->rollback();

        $_SESSION['booking_error'] = $error->getMessage();

        header("Location: booking.php?schedule_id=$scheduleId&bus_id=$busId");
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

    $seats = array_values(array_unique(array_filter(
        $seats,
        fn($seat) => preg_match('/^[1-9][0-9]*$/', $seat)
    )));

    if (!$scheduleId || !$busId || !$seats || count($seats) > 4) {
        exit('Please select 1 to 4 seats.');
    }

    $stmt = $conn->prepare("
        SELECT s.*, b.*
        FROM schedule s
        JOIN bus b ON s.bus_id = b.bus_id
        WHERE s.schedule_id = ?
        AND s.bus_id = ?
        AND s.status = 'active'
        LIMIT 1
    ");

    $stmt->bind_param('ii', $scheduleId, $busId);
    $stmt->execute();

    $trip = $stmt->get_result()->fetch_assoc();

    if (!$trip) {
        exit('Schedule not found.');
    }

    $from = $trip['from_city'] ?? $trip['departure_city'] ?? $trip['source'] ?? '';
    $to = $trip['to_city'] ?? $trip['arrival_city'] ?? $trip['destination'] ?? '';

    $route = trim($from . ' to ' . $to);

    $travelDate = $trip['departure_date'] ?? $trip['travel_date'] ?? '';
    $busName = $trip['bus_name'] ?? $trip['name'] ?? '';
    $busNumber = $trip['bus_number'] ?? '';
    $amount = $trip['ticket_price'] ?? $trip['price'] ?? $trip['fare'] ?? 0;

    if (!$route || !$travelDate || !$busNumber) {
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
$route = $booking['route'];
$travelDate = $booking['travel_date'];
$amount = $booking['amount'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Confirm Booking</title>
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            background: #f4f6f8;
            font-family: Arial, sans-serif;
            color: #263238;
        }

        .box {
            width: 400px;
            max-width: calc(100% - 30px);
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 3px 15px #0002;
            text-align: center;
        }

        h2 {
            margin: 0 0 20px;
        }

        p {
            color: #475569;
            margin: 10px 0;
        }

        .buttons {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 25px;
        }

        button,
        a {
            border: 0;
            padding: 11px 20px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: bold;
            cursor: pointer;
        }

        .confirm {
            background: #0f766e;
            color: white;
        }

        .cancel {
            background: #64748b;
            color: white;
        }

        .message {
            background: #fee2e2;
            color: #991b1b;
            padding: 14px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 600;
        }
    </style>
</head>

<body>

    <div class="box">

        <?php if (!empty($_SESSION['booking_error'])): ?>
            <div class="message">
                <?= htmlspecialchars($_SESSION['booking_error']) ?>
            </div>
        <?php unset($_SESSION['booking_error']);
        endif; ?>

        <h2>Confirm Booking</h2>

        <p>Bus: <?= htmlspecialchars($busName) ?></p>
        <p>Route: <?= htmlspecialchars($route) ?></p>
        <p>Date: <?= htmlspecialchars($travelDate) ?></p>
        <p>Seats: <?= htmlspecialchars(implode(', ', $seats)) ?></p>
        <p>Total: NPR. <?= htmlspecialchars((string)($amount * count($seats))) ?></p>

        <div class="buttons">

            <form method="post">
                <input type="hidden" name="confirm_booking" value="1">
                <button type="submit" class="confirm">
                    Confirm Booking
                </button>
            </form>

            <a href="seat_selection.php?schedule_id=<?= $scheduleId ?>&bus_id=<?= $busId ?>" class="cancel">
                Cancel
            </a>

        </div>

    </div>

</body>

</html>