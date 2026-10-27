<?php
session_start();
require_once "../db.php";
if (!isset($_SESSION['user_id'], $_SESSION['role']) || $_SESSION['role'] !== 'driver') {
    header("Location: ../login.php");
    exit;
}
include "../post_popup.php";
$driver_id = (int)$_SESSION['user_id'];
$stmt = $conn->prepare(" SELECT user_id,name,email,phone,profile_image,verification_status FROM users WHERE user_id=? AND role='driver' LIMIT 1
");
$stmt->bind_param("i", $driver_id);
$stmt->execute();
$driver = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$driver) {
    session_destroy();
    header("Location: ../login.php");
    exit;
}
$profile_image = !empty($driver['profile_image']) ? $driver['profile_image'] : 'default.png';
$stmt = $conn->prepare(" SELECT profile_photo FROM driver_verification WHERE driver_id=? ORDER BY verification_id DESC LIMIT 1
");
$stmt->bind_param("i", $driver_id);
$stmt->execute();
$verification = $stmt->get_result()->fetch_assoc();
$stmt->close();
$stmt = $conn->prepare(" SELECT     b.bus_id,     b.bus_number,     b.bus_name,     b.bus_type,     b.seats,     b.status,     b.bus_image FROM bus_driver bd INNER JOIN bus b ON bd.bus_id=b.bus_id WHERE bd.driver_id=? AND b.status='approved' LIMIT 1
");
$stmt->bind_param("i", $driver_id);
$stmt->execute();
$bus = $stmt->get_result()->fetch_assoc();
$stmt->close();
$today = date("Y-m-d");
$today_schedule = [];
if ($bus) {
    $stmt = $conn->prepare(" SELECT     schedule_id,     bus_id,     from_city,     to_city,     departure_date,     departure_time,     ticket_price,     available_seats,     status FROM schedules WHERE bus_id=? AND departure_date=? AND status!='cancelled' ORDER BY departure_time ASC ");
    $stmt->bind_param("is", $bus['bus_id'], $today);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $today_schedule[] = $row;
    }
    $stmt->close();
}
$total_bookings = 0;
$today_bookings = 0;
if ($bus) {
    $stmt = $conn->prepare(" SELECT COUNT(*) AS total FROM bookings bk INNER JOIN schedules s ON bk.schedule_id=s.schedule_id WHERE s.bus_id=? ");
    $stmt->bind_param("i", $bus['bus_id']);
    $stmt->execute();
    $booking_result = $stmt->get_result()->fetch_assoc();
    $total_bookings = (int)($booking_result['total'] ?? 0);
    $stmt->close();
    $stmt = $conn->prepare(" SELECT COUNT(*) AS total FROM bookings bk INNER JOIN schedules s ON bk.schedule_id=s.schedule_id WHERE s.bus_id=? AND s.departure_date=?  ");
    $stmt->bind_param("is", $bus['bus_id'], $today);
    $stmt->execute();
    $today_result = $stmt->get_result()->fetch_assoc();
    $today_bookings = (int)($today_result['total'] ?? 0);
    $stmt->close();
}
$driver_status = "Available";
if (!empty($today_schedule)) {
    $current_time = date("H:i:s");
    foreach ($today_schedule as $trip) {
        if ($current_time >= $trip['departure_time']) {
            $driver_status = "On Trip";
            break;
        }
    }
}
$notifications = [];
if ($bus) {
    $notifications[] = [
        "title" => "Bus Assigned",
        "message" => "You are assigned to bus " . $bus['bus_number'],
        "time" => "Today"
    ];
}
$notifications[] = [
    "title" => !empty($today_schedule) ? "Today's Trip" : "No Trip",
    "message" => !empty($today_schedule) ? "You have " . count($today_schedule) . " scheduled trip(s) today." : "You have no scheduled trip for today.",
    "time" => "Today"
];
$driver_photo = !empty($verification['profile_photo'])
    ? "../uploads/driver/profile/" . $verification['profile_photo'] : "../uploads/profile/" . $profile_image;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Driver Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
</head>

<body>
    <?php include "driver_header.php"; ?>
    <div class="container">
        <div class="cards">
            <div class="card">
                <div class="card-title">Assigned Bus</div>
                <div class="card-value"> <?= $bus ? htmlspecialchars($bus['bus_number']) : 'None' ?> </div>
            </div>
            <div class="card">
                <div class="card-title">Today's Trips</div>
                <div class="card-value"> <?= count($today_schedule) ?> </div>
            </div>
            <div class="card">
                <div class="card-title">Today's Passengers</div>
                <div class="card-value"> <?= $today_bookings ?> </div>
            </div>
            <div class="card">
                <div class="card-title">Total Bookings</div>
                <div class="card-value"> <?= $total_bookings ?> </div>
            </div>
        </div>
        <div class="content-grid">
            <div class="box">
                <h2>Assigned Bus</h2>
                <?php if ($bus): ?>
                    <div class="bus-details">
                        <div class="bus-item"> <small>Bus Number</small>
                            <strong> <?= htmlspecialchars($bus['bus_number']) ?> </strong>
                        </div>
                        <div class="bus-item"> <small>Bus Name</small>
                            <strong> <?= htmlspecialchars($bus['bus_name']) ?> </strong>
                        </div>
                        <div class="bus-item"> <small>Bus Type</small>
                            <strong> <?= htmlspecialchars($bus['bus_type']) ?> </strong>
                        </div>
                        <div class="bus-item"> <small>Total Seats</small>
                            <strong> <?= htmlspecialchars($bus['seats']) ?> </strong>
                        </div>
                        <div class="bus-item"> <small>Bus Status</small>
                            <strong> <?= ucfirst(htmlspecialchars($bus['status'])) ?> </strong>
                        </div>
                        <div class="bus-item">
                            <small>Trip Status</small>
                            <?php
                            $bus_status = 'Upcoming';

                            if (!empty($today_schedule)) {
                                foreach ($today_schedule as $trip) {
                                    if (strtolower($trip['status']) === 'completed') {
                                        $bus_status = 'Completed';
                                        break;
                                    } elseif (date('H:i:s') >= $trip['departure_time']) {
                                        $bus_status = 'On Trip';
                                    }
                                }
                            }
                            ?>
                            <strong class="status <?= strtolower(str_replace(' ', '-', $bus_status)) ?>">
                                <?= htmlspecialchars($bus_status) ?>
                            </strong>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="not-assigned"> No bus has been assigned to you yet. </div>
                <?php endif; ?>
                <br>
                <h2>Passenger Summary</h2>
                <div class=" pass-summary">
                    <p> Today's Passengers<br> <?= $today_bookings ?> </p>
                    <p>Total Booking<br> <?= $total_bookings ?> </p>
                </div>
                <div class="box">
                    <h2>Today's Trips</h2>
                    <?php if (!empty($today_schedule)): ?>
                        <div class="table-wrapper">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Route</th>
                                        <th>Departure</th>
                                        <th>Date</th>
                                        <th>Ticket Price</th>
                                        <th>Available Seats</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($today_schedule as $trip): ?>
                                        <tr>
                                            <td class="route"> <?= htmlspecialchars($trip['from_city']) ?> <strong>-</strong> <?= htmlspecialchars($trip['to_city']) ?> </td>
                                            <td> <?= date("h:i A", strtotime($trip['departure_time'])) ?> </td>
                                            <td> <?= date("d M Y", strtotime($trip['departure_date'])) ?> </td>
                                            <td> Rs. <?= number_format((float)$trip['ticket_price'], 2) ?> </td>
                                            <td> <?= htmlspecialchars($trip['available_seats']) ?> </td>
                                            <td> <?= ucfirst(htmlspecialchars($trip['status'])) ?> </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty"> No trips scheduled for today. </div>
                    <?php endif; ?>
                    <?php if (($driver['verification_status'] ?? '') === 'verified'): ?>
                        <div class="box">
                            <div class="verification-box">
                            <?php else: ?>
                                <h3 style="color:#dc3545;"> Driver Unverified </h3>
                                <p> Your driver account has not been verified yet. Please complete the verification process. </p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</body>

</html>