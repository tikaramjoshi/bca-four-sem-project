<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'passenger') {
    header('Location: ../login.php');
    exit;
}
require_once __DIR__ . '/../db.php';
function bookingMessage($message)
{
    $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    echo "<!doctype html><html lang=\"en\"><head><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width,initial-scale=1\"><title>Booking Notice</title><style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f4f6f8;font-family:Arial,sans-serif;color:#263238}.box{width:min(420px,calc(100% - 32px));background:#fff;padding:32px;border-radius:12px;text-align:center;box-shadow:0 3px 15px #00000018}.icon{font-size:38px;margin-bottom:12px}.box h2{margin:0 0 12px;color:#b91c1c}.box p{margin:0 0 22px;line-height:1.55;color:#475569}.button{display:inline-block;background:#0f766e;color:#fff;padding:11px 20px;border-radius:6px;text-decoration:none;font-weight:bold}</style></head><body><main class=\"box\"><div class=\"icon\">!</div><h2>Booking Not Available</h2><p>$safeMessage</p><a class=\"button\" href=\"dashboard.php\">Go to Dashboard</a></main></body></html>";
    exit;
}
if (!isset($conn) || !$conn instanceof mysqli) exit('Database connection is not available.');
$sessionUserId = (int)$_SESSION['user_id'];
$verifyStmt = $conn->prepare("SELECT verification_status FROM users WHERE user_id = ? AND role = 'passenger' LIMIT 1");
$verifyStmt->bind_param('i', $sessionUserId);
$verifyStmt->execute();
$verification = $verifyStmt->get_result()->fetch_assoc();
$verificationStatus = strtolower($verification['verification_status'] ?? 'pending');
if ($verificationStatus !== 'verified') {
    bookingMessage('Your account is not verified. Please wait for admin verification before booking a seat.');
}
$scheduleId = filter_input(INPUT_POST, 'schedule_id', FILTER_VALIDATE_INT);
$busId = filter_input(INPUT_POST, 'bus_id', FILTER_VALIDATE_INT);
$seats = $_POST['seat_numbers'] ?? [];
$seats = array_values(array_unique(array_filter($seats, fn($seat) => preg_match('/^[1-9][0-9]*$/', $seat))));
$userId = $_SESSION['user_id'] ?? 0;
if (!$scheduleId || !$busId || !$userId || !$seats || count($seats) > 4) exit('Invalid booking request.');
$tripSql = "SELECT s.*, b.* FROM schedule s JOIN bus b ON s.bus_id = b.bus_id WHERE s.schedule_id = ? AND s.bus_id = ? AND s.status = 'active' LIMIT 1";
$tripStmt = $conn->prepare($tripSql);
$tripStmt->bind_param('ii', $scheduleId, $busId);
$tripStmt->execute();
$trip = $tripStmt->get_result()->fetch_assoc();
if (!$trip) exit('Schedule not found.');
$from = $trip['from_city'] ?? $trip['departure_city'] ?? $trip['source'] ?? '';
$to = $trip['to_city'] ?? $trip['arrival_city'] ?? $trip['destination'] ?? '';
$route = trim($from . ' to ' . $to);
$travelDate = $trip['departure_date'] ?? '';
$busName = $trip['bus_name'] ?? $trip['name'] ?? '';
$busNumber = $trip['bus_number'] ?? '';
$amount = $trip['ticket_price'] ?? $trip['price'] ?? $trip['fare'] ?? 0;
if (!$route || !$travelDate || !$busNumber) exit('Schedule details are incomplete.');
$conn->begin_transaction();
try {
    $checkStmt = $conn->prepare("SELECT booking_id FROM bookings WHERE bus_number = ? AND route = ? AND travel_date = ? AND seat_number = ? AND (status IS NULL OR status != 'cancelled') LIMIT 1 FOR UPDATE");
    $insertStmt = $conn->prepare("INSERT INTO bookings (user_id, bus_name, bus_number, route, travel_date, seat_number, amount, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
    foreach ($seats as $seat) {
        $checkStmt->bind_param('ssss', $busNumber, $route, $travelDate, $seat);
        $checkStmt->execute();
        if ($checkStmt->get_result()->num_rows) throw new Exception('Seat ' . $seat . ' has already been booked. Please select another seat.');
        $insertStmt->bind_param('isssssd', $userId, $busName, $busNumber, $route, $travelDate, $seat, $amount);
        $insertStmt->execute();
    }
    $conn->commit();
} catch (Throwable $error) {
    $conn->rollback();
    exit($error->getMessage());
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Booking Successful</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f8;
            margin: 0;
            color: #263238
        }

        .box {
            max-width: 500px;
            margin: 80px auto;
            background: #fff;
            padding: 30px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 12px #00000014
        }

        .success {
            color: #15803d;
            font-size: 22px
        }

        .button {
            display: inline-block;
            margin-top: 18px;
            background: #0f766e;
            color: #fff;
            padding: 11px 18px;
            border-radius: 6px;
            text-decoration: none
        }
    </style>
</head>

<body>
    <main class="box">
        <p class="success">Booking successful</p>
        <p><?= htmlspecialchars($busName) ?> · <?= htmlspecialchars($route) ?></p>
        <p>Seats: <?= htmlspecialchars(implode(', ', $seats)) ?></p>
        <p>Total: Rs. <?= htmlspecialchars((string)($amount * count($seats))) ?></p><a class="button" href="my_bookings.php">My Bookings</a>
    </main>
</body>

</html>