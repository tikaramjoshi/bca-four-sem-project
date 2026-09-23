<?php
session_start();
require_once "../db.php";
if (!isset($_SESSION['user_id'], $_SESSION['role']) || $_SESSION['role'] !== 'driver') {
    header("Location: ../login.php");
    exit;
}
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
    $stmt = $conn->prepare(" SELECT COUNT(*) AS total FROM bookings bk INNER JOIN schedules s ON bk.schedule_id=s.schedule_id WHERE s.bus_id=?
    ");
    $stmt->bind_param("i", $bus['bus_id']);
    $stmt->execute();
    $booking_result = $stmt->get_result()->fetch_assoc();
    $total_bookings = (int)($booking_result['total'] ?? 0);
    $stmt->close();
    $stmt = $conn->prepare(" SELECT COUNT(*) AS total FROM bookings bk INNER JOIN schedules s ON bk.schedule_id=s.schedule_id WHERE s.bus_id=? AND s.departure_date=?
    ");
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
    "message" => !empty($today_schedule)
        ? "You have " . count($today_schedule) . " scheduled trip(s) today."
        : "You have no scheduled trip for today.",
    "time" => "Today"
];
$driver_photo = !empty($verification['profile_photo'])
    ? "../uploads/driver/profile/" . $verification['profile_photo']
    : "../uploads/profile/" . $profile_image;


include "../include/message/sql.php";

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Driver Dashboard</title>
    <link rel="stylesheet" href="dashboard.css">
    <link rel="stylesheet" href="../include/message/mesage.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
</head>

<body>
    <header class="header">
        <a href="../role_request.php">Change</a>
        <div class="logo">Driver Dashboard</div>
        <div><a href="scan_ticket.php">Scan</a></div>
        <div><a href="verify_ticket.php">Verify ticket</a></div>
        <div><a href="bookings.php">Passenger Bookings</a></div>
        <div class="driver-profile" onclick="toggleProfileMenu()">
            <div class="driver-info"><strong><?= htmlspecialchars($driver['name']) ?></strong><span class="driver-status"><i></i><?= htmlspecialchars($driver_status) ?></span></div>
            <img src="../uploads/profile/<?= htmlspecialchars($profile_image) ?>" class="profile-image" alt="Driver Profile" onerror="this.onerror=null;this.src='../uploads/default.png';">
            <div class="profile-menu" id="profileMenu">
                <div class="menu-divider"></div>
                <a href="profile.php"> <i class=" fa fa-user-circle"></i> My Profile </a>
                <a href="driver_verification.php"> <i class=" fa fa-check-circle"></i> Verification </a>
                <a href="my_bus.php"> <i class=" fa fa-bus"></i> My Bus</a>
                <a href="trips.php"> <i class=" fa fa-road"></i> My Trips </a>
                <a href="notifications.php"> <i class=" fa fa-bell"></i> Notifications </a>
                <a href="../changepassword.php"> <i class=" fa fa-key"></i>Change Password</a>
                <hr>
                <div class="menu-divider"></div>
                <a href="../logout.php" class="logout-link"> <i class="fa fa-sign-out"></i>Logout</a>
            </div>
        </div>
    </header>
    <div class="container">
        <div class="welcome">
            <h1>Welcome, <?= htmlspecialchars($driver['name']) ?> </h1>
            <p>Manage your assigned bus, trips and passenger information.</p>
            <div class="status-row"><span class="status-dot"></span><span class="status-text"><?= htmlspecialchars($driver_status) ?></span></div>
        </div>
        <div class="cards">
            <div class="card">
                <div class="card-title">Assigned Bus</div>
                <div class="card-value">
                    <?= $bus ? htmlspecialchars($bus['bus_number']) : 'None' ?>
                </div>
            </div>
            <div class="card">
                <div class="card-title">Today's Trips</div>
                <div class="card-value">
                    <?= count($today_schedule) ?>
                </div>
            </div>
            <div class="card">
                <div class="card-title">Today's Passengers</div>
                <div class="card-value">
                    <?= $today_bookings ?>
                </div>
            </div>
            <div class="card">
                <div class="card-title">Total Bookings</div>
                <div class="card-value">
                    <?= $total_bookings ?>
                </div>
            </div>
        </div>
        <div class="content-grid">
            <div>
                <div class="box">
                    <h2>Assigned Bus</h2>
                    <?php if ($bus): ?>
                        <div class="bus-details">
                            <div class="bus-item">
                                <small>Bus Number</small>
                                <strong>
                                    <?= htmlspecialchars($bus['bus_number']) ?>
                                </strong>
                            </div>
                            <div class="bus-item">
                                <small>Bus Name</small>
                                <strong>
                                    <?= htmlspecialchars($bus['bus_name']) ?>
                                </strong>
                            </div>
                            <div class="bus-item">
                                <small>Bus Type</small>
                                <strong>
                                    <?= htmlspecialchars($bus['bus_type']) ?>
                                </strong>
                            </div>
                            <div class="bus-item">
                                <small>Total Seats</small>
                                <strong>
                                    <?= htmlspecialchars($bus['seats']) ?>
                                </strong>
                            </div>
                            <div class="bus-item">
                                <small>Bus Status</small>
                                <strong>
                                    <?= ucfirst(htmlspecialchars($bus['status'])) ?>
                                </strong>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="not-assigned">
                            No bus has been assigned to you yet.
                        </div>
                    <?php endif; ?>
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
                </div>
                <div class="box">
                    <h2>Driver Information</h2>
                    <div class="bus-details">
                        <div class="bus-item">
                            <small>Name</small>
                            <strong>
                                <?= htmlspecialchars($driver['name']) ?>
                            </strong>
                        </div>
                        <div class="bus-item">
                            <small>Phone</small>
                            <strong>
                                <?= htmlspecialchars($driver['phone']) ?>
                            </strong>
                        </div>
                        <div class="bus-item">
                            <small>Email</small>
                            <strong>
                                <?= htmlspecialchars($driver['email']) ?>
                            </strong>
                        </div>
                        <div class="bus-item">
                            <small>Verification</small>
                            <?php if (($driver['verification_status'] ?? '') === 'verified'): ?>
                                <span class="verified">
                                    Verified
                                </span>
                            <?php else: ?>
                                <span class="unverified">
                                    Unverified
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div>
                <div class="box">
                    <h2>Driver Status</h2>
                    <div class="status-row">
                        <span class="status-dot"></span>
                        <span class="status-text">
                            <?= htmlspecialchars($driver_status) ?>
                        </span>
                    </div>
                    <p style="margin-top:15px;color:#777;font-size:14px;line-height:1.6;">
                        Your current driver status is based on today's scheduled departure time.
                    </p>
                </div>
                <div class="box">
                    <h2>Verification Status</h2>
                    <div class="verification-box">
                        <?php if (($driver['verification_status'] ?? '') === 'verified'): ?>
                            <h3 style="color:#198754;">
                                Driver Verified
                            </h3>
                            <p>
                                Your driver account has been verified.
                                You can operate your assigned bus and trips.
                            </p>
                        <?php else: ?>
                            <h3 style="color:#dc3545;">
                                Driver Unverified
                            </h3>
                            <p>
                                Your driver account has not been verified yet.
                                Please complete the verification process.
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="box">
                    <h2>Notifications</h2>
                    <?php if (!empty($notifications)): ?>
                        <?php foreach ($notifications as $notification): ?>
                            <div class="notification">
                                <strong>
                                    <?= htmlspecialchars($notification['title']) ?>
                                </strong>
                                <p>
                                    <?= htmlspecialchars($notification['message']) ?>
                                </p>
                                <small>
                                    <?= htmlspecialchars($notification['time']) ?>
                                </small>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty">No notifications.</div>
                    <?php endif; ?>
                </div>
                <div class="box">
                    <h2>Passenger Summary</h2>
                    <div class="bus-details">
                        <div class="bus-item">
                            <small>Today's Passengers</small>
                            <strong> <?= $today_bookings ?></strong>
                        </div>
                        <div class="bus-item">
                            <small>Total Bookings</small>
                            <strong> <?= $total_bookings ?> </strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include "../include/message/code.php"; ?>
    <script>
        const posts = [];
    </script>
    <script src="../include/message/message.js"></script>
    <script>
        function toggleProfileMenu() {
            document.querySelector(".driver-profile").classList.toggle("active");
        }

        document.addEventListener("click", function(e) {
            const profile = document.querySelector(".driver-profile");
            if (profile && !profile.contains(e.target)) {
                profile.classList.remove("active");
            }
        });
    </script>
</body>

</html>