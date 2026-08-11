<?php
session_start();
require_once "../db.php";

if (!isset($_SESSION['user_id'], $_SESSION['role']) || $_SESSION['role'] !== 'driver') {
    header("Location: ../login.php");
    exit;
}

$driver_id = (int)$_SESSION['user_id'];

$stmt = $conn->prepare("
    SELECT user_id, name, email, phone, profile_image, verification_status
    FROM users
    WHERE user_id = ? AND role = 'driver'
    LIMIT 1
");
$stmt->bind_param("i", $driver_id);
$stmt->execute();
$result = $stmt->get_result();
$driver = $result->fetch_assoc();

if (!$driver) {
    session_destroy();
    header("Location: ../login.php");
    exit;
}

$profile_image = !empty($driver['profile_image']) ? $driver['profile_image'] : 'default.png';

$bus = null;

$stmt = $conn->prepare("
    SELECT b.bus_id, b.bus_number, b.bus_name, b.bus_type, b.seats, b.status
    FROM bus_driver bd
    INNER JOIN bus b ON bd.bus_id = b.bus_id
    WHERE bd.driver_id = ? AND b.status = 'approved'
    LIMIT 1
");
$stmt->bind_param("i", $driver_id);
$stmt->execute();
$bus_result = $stmt->get_result();
$bus = $bus_result->fetch_assoc();

$today = date("Y-m-d");
$today_schedule = [];

if ($bus) {
    $stmt = $conn->prepare("
        SELECT schedule_id, bus_id, from_city, to_city, departure_date,
               departure_time, ticket_price, available_seats, status
        FROM schedule
        WHERE bus_id = ?
          AND departure_date = ?
          AND status != 'cancelled'
        ORDER BY departure_time ASC
    ");
    $stmt->bind_param("is", $bus['bus_id'], $today);
    $stmt->execute();
    $schedule_result = $stmt->get_result();

    while ($row = $schedule_result->fetch_assoc()) {
        $today_schedule[] = $row;
    }
}

$total_bookings = 0;
$today_bookings = 0;

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

if (!empty($today_schedule)) {
    $notifications[] = [
        "title" => "Today's Trip",
        "message" => "You have " . count($today_schedule) . " scheduled trip(s) today.",
        "time" => "Today"
    ];
} else {
    $notifications[] = [
        "title" => "No Trip",
        "message" => "You have no scheduled trip for today.",
        "time" => "Today"
    ];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver Dashboard</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, Helvetica, sans-serif;
        }

        body {
            background: #f4f6f9;
            color: #222;
        }

        .header {
            height: 70px;
            background: #1560bd;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 35px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, .12);
        }

        .logo {
            font-size: 23px;
            font-weight: 700;
        }

        .driver-profile {
            position: relative;
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            height: 70px;
        }

        .driver-info {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            line-height: 1.2;
        }

        .driver-info strong {
            font-size: 14px;
        }

        .driver-status {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 11px;
            margin-top: 4px;
            color: #d9ffe6;
        }

        .driver-status i {
            width: 7px;
            height: 7px;
            background: #28a745;
            border-radius: 50%;
            display: inline-block;
        }

        .profile-image {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #fff;
        }

        .dropdown-arrow {
            font-size: 10px;
            transition: .2s;
        }

        .driver-profile.active .dropdown-arrow {
            transform: rotate(180deg);
        }

        .profile-menu {
            position: absolute;
            top: 63px;
            right: 0;
            width: 285px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, .18);
            overflow: hidden;
            display: none;
            z-index: 1000;
            color: #222;
        }

        .driver-profile.active .profile-menu {
            display: block;
        }

        .menu-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 18px;
            background: #f7f9fc;
        }

        .menu-profile img {
            width: 55px;
            height: 55px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #1560bd;
        }

        .menu-profile>div {
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        .menu-profile strong {
            font-size: 15px;
            margin-bottom: 4px;
        }

        .menu-profile small {
            color: #777;
            margin-bottom: 7px;
        }

        .verified,
        .unverified {
            width: fit-content;
            padding: 4px 9px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .verified {
            background: #d1e7dd;
            color: #0f5132;
        }

        .unverified {
            background: #f8d7da;
            color: #842029;
        }

        .menu-divider {
            height: 1px;
            background: #eee;
        }

        .profile-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 13px 18px;
            color: #333;
            text-decoration: none;
            font-size: 14px;
            transition: .2s;
        }

        .profile-menu a:hover {
            background: #f1f5fb;
            color: #1560bd;
        }

        .profile-menu a span {
            flex: 1;
        }

        .profile-menu .logout-link {
            color: #dc3545;
        }

        .profile-menu .logout-link:hover {
            background: #fff1f2;
            color: #b02a37;
        }

        .container {
            width: 94%;
            max-width: 1250px;
            margin: 30px auto;
        }

        .welcome {
            background: #fff;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 3px 12px rgba(0, 0, 0, .06);
        }

        .welcome h1 {
            font-size: 25px;
            margin-bottom: 8px;
        }

        .welcome p {
            color: #666;
        }

        .status-row {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 15px;
        }

        .status-dot {
            width: 11px;
            height: 11px;
            border-radius: 50%;
            background: #198754;
        }

        .status-text {
            font-weight: bold;
            color: #198754;
        }

        .cards {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 25px;
        }

        .card {
            background: #fff;
            padding: 22px;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(0, 0, 0, .06);
        }

        .card-title {
            color: #777;
            font-size: 14px;
            margin-bottom: 10px;
        }

        .card-value {
            font-size: 27px;
            font-weight: bold;
            color: #1560bd;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 1.4fr 1fr;
            gap: 25px;
        }

        .box {
            background: #fff;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(0, 0, 0, .06);
            margin-bottom: 25px;
        }

        .box h2 {
            font-size: 19px;
            margin-bottom: 20px;
            color: #333;
        }

        .bus-details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .bus-item {
            background: #f7f9fc;
            padding: 15px;
            border-radius: 8px;
        }

        .bus-item small {
            color: #777;
            display: block;
            margin-bottom: 5px;
        }

        .bus-item strong {
            color: #222;
        }

        .not-assigned {
            padding: 18px;
            background: #fff3cd;
            color: #856404;
            border-radius: 8px;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 13px 12px;
            border-bottom: 1px solid #e5e5e5;
            text-align: left;
            white-space: nowrap;
        }

        th {
            background: #f5f7fa;
            color: #555;
            font-size: 14px;
        }

        td {
            font-size: 14px;
        }

        .route {
            font-weight: 600;
            color: #1560bd;
        }

        .notification {
            padding: 14px;
            background: #f7f9fc;
            border-left: 4px solid #1560bd;
            border-radius: 6px;
            margin-bottom: 12px;
        }

        .notification strong {
            display: block;
            margin-bottom: 5px;
        }

        .notification p {
            color: #666;
            font-size: 14px;
        }

        .notification small {
            color: #999;
            display: block;
            margin-top: 5px;
        }

        .empty {
            color: #777;
            padding: 10px 0;
        }

        .verification-box {
            padding: 18px;
            border-radius: 10px;
            background: #f7f9fc;
        }

        .verification-box h3 {
            margin-bottom: 8px;
            font-size: 16px;
        }

        .verification-box p {
            color: #777;
            font-size: 14px;
            line-height: 1.6;
        }

        @media(max-width:1000px) {
            .cards {
                grid-template-columns: repeat(2, 1fr);
            }

            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        @media(max-width:650px) {
            .header {
                padding: 0 15px;
            }

            .logo {
                font-size: 18px;
            }

            .driver-info {
                display: none;
            }

            .container {
                width: 94%;
                margin: 20px auto;
            }

            .cards {
                grid-template-columns: 1fr;
            }

            .bus-details {
                grid-template-columns: 1fr;
            }

            .welcome h1 {
                font-size: 21px;
            }

            .profile-menu {
                right: -5px;
                width: 260px;
            }
        }
    </style>
</head>

<body>
    <header class="header">
        <div class="logo">Driver Dashboard</div>
        <div class="driver-profile" onclick="toggleProfileMenu()">
            <div class="driver-info">
                <strong><?= htmlspecialchars($driver['name']) ?></strong>
                <span class="driver-status">
                    <i></i>
                    <?= htmlspecialchars($driver_status) ?>
                </span>
            </div>
            <img
                src="../uploads/profile/<?= htmlspecialchars($profile_image) ?>"
                class="profile-image"
                alt="Driver Profile"
                onerror="this.onerror=null;this.src='../images/default.png';">
            <span class="dropdown-arrow">▼</span>
            <div class="profile-menu" id="profileMenu">
                <div class="menu-profile">
                    <img
                        src="../uploads/profile/<?= htmlspecialchars($profile_image) ?>"
                        alt="Profile"
                        onerror="this.onerror=null;this.src='../images/default.png';">
                    <div>
                        <strong><?= htmlspecialchars($driver['name']) ?></strong>
                        <small>Driver</small>
                        <?php if (
                            isset($driver['verification_status']) &&
                            $driver['verification_status'] === 'verified'
                        ): ?>
                            <span class="verified">✓ Verified</span>
                        <?php else: ?>
                            <span class="unverified">✗ Unverified</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="menu-divider"></div>
                <a href="profile.php">👤 <span>My Profile</span></a>
                <a href="driver_verification.php">✓ <span>Verification</span></a>
                <a href="my_bus.php">🚌 <span>My Bus</span></a>
                <a href="trips.php">📅 <span>My Trips</span></a>
                <a href="notifications.php">🔔 <span>Notifications</span></a>
                <div class="menu-divider"></div>
                <a href="../logout.php" class="logout-link">🚪 <span>Logout</span></a>
            </div>
        </div>
    </header>
    <div class="container">
        <div class="welcome">
            <h1>Welcome, <?= htmlspecialchars($driver['name']) ?> 👋</h1>
            <p>Manage your assigned bus, trips and passenger information.</p>
            <div class="status-row">
                <span class="status-dot"></span>
                <span class="status-text"><?= htmlspecialchars($driver_status) ?></span>
            </div>
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
                <div class="card-value"><?= count($today_schedule) ?></div>
            </div>
            <div class="card">
                <div class="card-title">Today's Passengers</div>
                <div class="card-value"><?= $today_bookings ?></div>
            </div>
            <div class="card">
                <div class="card-title">Total Bookings</div>
                <div class="card-value"><?= $total_bookings ?></div>
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
                                <strong><?= htmlspecialchars($bus['bus_number']) ?></strong>
                            </div>
                            <div class="bus-item">
                                <small>Bus Name</small>
                                <strong><?= htmlspecialchars($bus['bus_name']) ?></strong>
                            </div>
                            <div class="bus-item">
                                <small>Bus Type</small>
                                <strong><?= htmlspecialchars($bus['bus_type']) ?></strong>
                            </div>
                            <div class="bus-item">
                                <small>Total Seats</small>
                                <strong><?= htmlspecialchars($bus['seats']) ?></strong>
                            </div>
                            <div class="bus-item">
                                <small>Bus Status</small>
                                <strong><?= ucfirst(htmlspecialchars($bus['status'])) ?></strong>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="not-assigned">No bus has been assigned to you yet.</div>
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
                                            <td class="route">
                                                <?= htmlspecialchars($trip['from_city']) ?>
                                                →
                                                <?= htmlspecialchars($trip['to_city']) ?>
                                            </td>
                                            <td><?= date("h:i A", strtotime($trip['departure_time'])) ?></td>
                                            <td><?= date("d M Y", strtotime($trip['departure_date'])) ?></td>
                                            <td>Rs. <?= number_format((float)$trip['ticket_price'], 2) ?></td>
                                            <td><?= htmlspecialchars($trip['available_seats']) ?></td>
                                            <td><?= ucfirst(htmlspecialchars($trip['status'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty">No trips scheduled for today.</div>
                    <?php endif; ?>
                </div>
                <div class="box">
                    <h2>Driver Information</h2>
                    <div class="bus-details">
                        <div class="bus-item">
                            <small>Name</small>
                            <strong><?= htmlspecialchars($driver['name']) ?></strong>
                        </div>
                        <div class="bus-item">
                            <small>Phone</small>
                            <strong><?= htmlspecialchars($driver['phone']) ?></strong>
                        </div>
                        <div class="bus-item">
                            <small>Email</small>
                            <strong><?= htmlspecialchars($driver['email']) ?></strong>
                        </div>
                        <div class="bus-item">
                            <small>Verification</small>
                            <?php if (
                                isset($driver['verification_status']) &&
                                $driver['verification_status'] === 'verified'
                            ): ?>
                                <span class="verified">Verified ✓</span>
                            <?php else: ?>
                                <span class="unverified">Unverified</span>
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
                        <span class="status-text"><?= htmlspecialchars($driver_status) ?></span>
                    </div>
                    <p style="margin-top:15px;color:#777;font-size:14px;line-height:1.6;">
                        Your current driver status is based on today's scheduled departure time.
                    </p>
                </div>
                <div class="box">
                    <h2>Verification Status</h2>
                    <div class="verification-box">
                        <?php if (
                            isset($driver['verification_status']) &&
                            $driver['verification_status'] === 'verified'
                        ): ?>
                            <h3 style="color:#198754;">✓ Driver Verified</h3>
                            <p>Your driver account has been verified. You can operate your assigned bus and trips.</p>
                        <?php else: ?>
                            <h3 style="color:#dc3545;">✗ Driver Unverified</h3>
                            <p>Your driver account has not been verified yet. Please complete the verification process.</p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="box">
                    <h2>Notifications</h2>
                    <?php if (!empty($notifications)): ?>
                        <?php foreach ($notifications as $notification): ?>
                            <div class="notification">
                                <strong><?= htmlspecialchars($notification['title']) ?></strong>
                                <p><?= htmlspecialchars($notification['message']) ?></p>
                                <small><?= htmlspecialchars($notification['time']) ?></small>
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
                            <strong><?= $today_bookings ?></strong>
                        </div>
                        <div class="bus-item">
                            <small>Total Bookings</small>
                            <strong><?= $total_bookings ?></strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
        function toggleProfileMenu() {
            const profile = document.querySelector(".driver-profile");
            profile.classList.toggle("active");
        }
        document.addEventListener("click", function(event) {
            const profile = document.querySelector(".driver-profile");
            if (profile && !profile.contains(event.target)) {
                profile.classList.remove("active");
            }
        });
    </script>
</body>

</html>