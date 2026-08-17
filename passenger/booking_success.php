<?php
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'passenger') {
    header("Location: ../login.php");
    exit;
}
if (empty($_SESSION['booking_success'])) {
    header("Location: dashboard.php");
    exit;
}
$booking = $_SESSION['booking_success'];
$busName = $booking['bus_name'] ?? '';
$route = $booking['route'] ?? '';
$seats = $booking['seats'] ?? '';
$total = $booking['total'] ?? 0;
unset($_SESSION['booking_success']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Booking Successful</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f4f7fb;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .box {
            width: 450px;
            max-width: 92%;
            background: #fff;
            padding: 35px;
            border-radius: 12px;
            box-shadow: 0 5px 20px #0002;
            text-align: center;
        }

        .success {
            width: 65px;
            height: 65px;
            margin: 0 auto 15px;
            border-radius: 50%;
            background: #198754;
            color: #fff;
            font-size: 38px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        h2 {
            color: #198754;
            margin-bottom: 8px;
        }

        .message {
            color: #666;
            margin-bottom: 25px;
        }

        .details {
            text-align: left;
            background: #f7f9fc;
            border-radius: 8px;
            padding: 18px;
        }

        .row {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            padding: 10px 0;
            border-bottom: 1px solid #ddd;
        }

        .row:last-child {
            border-bottom: 0;
        }

        .label {
            color: #666;
        }

        .value {
            font-weight: bold;
            text-align: right;
        }

        .total {
            color: #198754;
            font-size: 18px;
        }

        .buttons {
            display: flex;
            gap: 10px;
            margin-top: 25px;
        }

        .btn {
            flex: 1;
            padding: 12px;
            border-radius: 6px;
            text-decoration: none;
            color: #fff;
            font-weight: bold;
        }

        .home {
            background: #1560bd;
        }

        .history {
            background: #198754;
        }

        .btn:hover {
            opacity: .9;
        }
    </style>
</head>

<body>
    <div class="box">
        <div class="success"> <i class="fa fa-check"></i></div>
        <h2>Booking Successful!</h2>
        <p class="message">Your bus ticket has been booked successfully.</p>
        <div class="details">
            <div class="row">
                <span class="label">Bus</span>
                <span class="value"><?= htmlspecialchars($busName) ?></span>
            </div>
            <div class="row">
                <span class="label">Route</span>
                <span class="value"><?= htmlspecialchars($route) ?></span>
            </div>
            <div class="row">
                <span class="label">Travel Date</span>
                <span class="value">
                    <?= !empty($booking['travel_date']) ? date("d M Y", strtotime($booking['travel_date'])) : '' ?>
                </span>
            </div>
            <div class="row">
                <span class="label">Seats</span>
                <span class="value"><?= htmlspecialchars($seats) ?></span>
            </div>
            <div class="row">
                <span class="label">Total</span>
                <span class="value total">NPR <?= number_format((float)$total, 2) ?></span>
            </div>
        </div>
        <div class="buttons">
            <a href="dashboard.php" class="btn home">Home</a>
            <a href="booking_history.php" class="btn history">My Bookings</a>
        </div>
    </div>
</body>

</html>