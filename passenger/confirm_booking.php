<?php
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'passenger') {
    header('Location: ../login.php');
    exit;
}
require_once __DIR__ . '/../db.php';
if (!$conn instanceof mysqli) exit('Database connection is not available.');

if (empty($_SESSION['pending_booking'])) {
    header('Location: dashboard.php');
    exit;
}

$b = $_SESSION['pending_booking'];
$userId = (int)$_SESSION['user_id'];
$scheduleId = (int)$b['schedule_id'];
$busId = (int)$b['bus_id'];
$seats = $b['seats'];
$busName = $b['bus_name'];
$busNumber = $b['bus_number'];
$route = $b['route'];
$travelDate = $b['travel_date'];
$amount = $b['amount'];

$stmt = $conn->prepare("SELECT verification_status FROM users WHERE user_id=? AND role='passenger' LIMIT 1");
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$status = strtolower($user['verification_status'] ?? 'pending');

if ($status !== 'verified') {
    $message = $status === 'rejected'
        ? 'Your account status is REJECTED. You cannot book a seat.'
        : ($status === 'pending'
            ? 'Your account status is PENDING. Please wait for admin verification before booking.'
            : 'Your account is not verified. You cannot book a seat.');
?>
    <!DOCTYPE html>
    <html>

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <title>Booking Notice</title>
        <style>
            body {
                margin: 0;
                min-height: 100vh;
                display: grid;
                place-items: center;
                background: #f4f6f8;
                font-family: Arial, sans-serif;
            }

            .box {
                width: 400px;
                max-width: calc(100% - 30px);
                background: #fff;
                padding: 30px;
                border-radius: 12px;
                text-align: center;
                box-shadow: 0 3px 15px #0002;
            }

            h2 {
                color: #b91c1c;
                margin: 0 0 15px;
            }

            p {
                color: #475569;
                line-height: 1.5;
            }

            .btn {
                display: inline-block;
                background: #0f766e;
                color: #fff;
                padding: 11px 20px;
                border-radius: 6px;
                text-decoration: none;
                font-weight: bold;
                margin-top: 15px;
            }
        </style>
    </head>

    <body>
        <div class="box">
            <h2>Booking Not Allowed</h2>
            <p><?= htmlspecialchars($message) ?></p>
            <a class="btn" href="seat_selection.php?schedule_id=<?= $scheduleId ?>&bus_id=<?= $busId ?>">Back</a>
        </div>
    </body>

    </html>
<?php
    exit;
}

$conn->begin_transaction();

try {
    $check = $conn->prepare("SELECT booking_id FROM bookings WHERE bus_number=? AND route=? AND travel_date=? AND seat_number=? AND (status IS NULL OR status!='cancelled') LIMIT 1 FOR UPDATE");
    $insert = $conn->prepare("INSERT INTO bookings(user_id,bus_name,bus_number,route,travel_date,seat_number,amount,status) VALUES(?,?,?,?,?,?,?,'pending')");

    foreach ($seats as $seat) {
        $check->bind_param('ssss', $busNumber, $route, $travelDate, $seat);
        $check->execute();

        if ($check->get_result()->num_rows > 0) {
            throw new Exception("Seat $seat has already been booked. Please select another seat.");
        }

        $insert->bind_param('isssssd', $userId, $busName, $busNumber, $route, $travelDate, $seat, $amount);

        if (!$insert->execute()) {
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
    header("Location: seat_selection.php?schedule_id=$scheduleId&bus_id=$busId");
    exit;
}
?>