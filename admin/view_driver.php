<?php
session_start();
require_once "../db.php";
if (!isset($_SESSION['user_id'], $_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}
if (empty($_GET['id'])) {
    header("Location: drivers.php");
    exit;
}
$id = (int)$_GET['id'];
$stmt = $conn->prepare("SELECT u.user_id,u.name,u.email,u.phone,u.profile_image,u.verification_status,u.created_at,b.bus_id,b.bus_number,b.bus_name,b.bus_type,b.seats,b.status AS bus_status FROM users u LEFT JOIN bus_driver bd ON u.user_id=bd.driver_id LEFT JOIN bus b ON bd.bus_id=b.bus_id WHERE u.user_id=? AND u.role='driver' LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$driver = $stmt->get_result()->fetch_assoc();
if (!$driver) die("Driver Not Found");

$image = $driver['profile_image'] ?: 'default.png';
$today = date("Y-m-d");
$today_trips = [];
$total_trips = 0;
$total_bookings = 0;
$today_bookings = 0;

if ($driver['bus_id']) {
    $stmt = $conn->prepare("SELECT schedule_id,from_city,to_city,travel_date,departure_time,arrival_time FROM schedules WHERE bus_id=? ORDER BY travel_date DESC,departure_time DESC");
    $stmt->bind_param("i", $driver['bus_id']);
    $stmt->execute();
    $res = $stmt->get_result();
    $total_trips = $res->num_rows;
    while ($r = $res->fetch_assoc()) if ($r['travel_date'] === $today) $today_trips[] = $r;

    $stmt = $conn->prepare("SELECT COUNT(*) total FROM bookings bk INNER JOIN schedules s ON bk.schedule_id=s.schedule_id WHERE s.bus_id=?");
    $stmt->bind_param("i", $driver['bus_id']);
    $stmt->execute();
    $total_bookings = (int)$stmt->get_result()->fetch_assoc()['total'];

    $stmt = $conn->prepare("SELECT COUNT(*) total FROM bookings bk INNER JOIN schedules s ON bk.schedule_id=s.schedule_id WHERE s.bus_id=? AND s.travel_date=?");
    $stmt->bind_param("is", $driver['bus_id'], $today);
    $stmt->execute();
    $today_bookings = (int)$stmt->get_result()->fetch_assoc()['total'];
}

$status = "Available";
foreach ($today_trips as $trip) {
    if (date("H:i:s") >= $trip['departure_time'] && date("H:i:s") <= $trip['arrival_time']) {
        $status = "On Trip";
        break;
    }
}
$verified = $driver['verification_status'] === 'verified';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Driver Details</title>
    <link rel="stylesheet" href="view_driver.css">
</head>

<body>
    <div class="page">
        <div class="top">
            <div>
                <h1>Driver Details</h1>
                <p>Complete information about the selected driver.</p>
            </div>
            <a href="drivers.php" class="back">← Back to Drivers</a>
        </div>

        <div class="profile">
            <img src="../uploads/profile/<?= htmlspecialchars($image) ?>" onerror="this.src='../images/default.png'">
            <div class="info">
                <h2><?= htmlspecialchars($driver['name']) ?></h2>
                <p><?= htmlspecialchars($driver['email']) ?></p>
                <span class="badge <?= $verified ? 'verified' : 'unverified' ?>"><?= $verified ? 'Verified ✓' : 'Unverified' ?></span>
                <span class="badge <?= $status === 'On Trip' ? 'trip' : 'available' ?>">● <?= $status ?></span>
                <div class="actions">
                    <?php if (!$verified): ?>
                        <a href="drivers.php?action=verify&id=<?= $id ?>" class="verify" onclick="return confirm('Verify this driver?')">✓ Verify Driver</a>
                    <?php else: ?>
                        <a href="drivers.php?action=reject&id=<?= $id ?>" class="reject" onclick="return confirm('Reject this driver verification?')">Reject Verification</a>
                    <?php endif; ?>
                    <a href="drivers.php?action=delete&id=<?= $id ?>" class="delete" onclick="return confirm('Are you sure you want to delete this driver?')">Delete Driver</a>
                </div>
            </div>
        </div>

        <div class="stats">
            <div><small>Driver ID</small><strong>#<?= $driver['user_id'] ?></strong></div>
            <div><small>Today's Trips</small><strong><?= count($today_trips) ?></strong></div>
            <div><small>Today's Passengers</small><strong><?= $today_bookings ?></strong></div>
            <div><small>Total Bookings</small><strong><?= $total_bookings ?></strong></div>
        </div>

        <div class="grid">
            <div>
                <div class="box">
                    <h2>Driver Information</h2>
                    <div class="details">
                        <div><small>Driver ID</small><strong><?= $driver['user_id'] ?></strong></div>
                        <div><small>Full Name</small><strong><?= htmlspecialchars($driver['name']) ?></strong></div>
                        <div><small>Email</small><strong><?= htmlspecialchars($driver['email']) ?></strong></div>
                        <div><small>Phone</small><strong><?= htmlspecialchars($driver['phone']) ?></strong></div>
                        <div><small>Verification</small><strong><?= $verified ? 'Verified ✓' : 'Unverified' ?></strong></div>
                        <div><small>Driver Status</small><strong><?= $status ?></strong></div>
                        <div><small>Registered Date</small><strong><?= date("d M Y", strtotime($driver['created_at'])) ?></strong></div>
                        <div><small>Account Role</small><strong>Driver</strong></div>
                    </div>
                </div>

                <div class="box">
                    <h2>Today's Trips</h2>
                    <?php if ($today_trips): ?>
                        <table>
                            <tr>
                                <th>Route</th>
                                <th>Departure</th>
                                <th>Arrival</th>
                                <th>Date</th>
                            </tr>
                            <?php foreach ($today_trips as $trip): ?>
                                <tr>
                                    <td class="route"><?= htmlspecialchars($trip['from_city']) ?> → <?= htmlspecialchars($trip['to_city']) ?></td>
                                    <td><?= date("h:i A", strtotime($trip['departure_time'])) ?></td>
                                    <td><?= date("h:i A", strtotime($trip['arrival_time'])) ?></td>
                                    <td><?= date("d M Y", strtotime($trip['travel_date'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    <?php else: ?><div class="empty">No trips scheduled for today.</div><?php endif; ?>
                </div>
            </div>

            <div>
                <div class="box">
                    <h2>Assigned Bus</h2>
                    <?php if ($driver['bus_id']): ?>
                        <div class="details">
                            <div><small>Bus Number</small><strong><?= htmlspecialchars($driver['bus_number']) ?></strong></div>
                            <div><small>Bus Name</small><strong><?= htmlspecialchars($driver['bus_name']) ?></strong></div>
                            <div><small>Bus Type</small><strong><?= htmlspecialchars($driver['bus_type']) ?></strong></div>
                            <div><small>Total Seats</small><strong><?= $driver['seats'] ?></strong></div>
                            <div><small>Bus Status</small><strong><?= ucfirst(htmlspecialchars($driver['bus_status'])) ?></strong></div>
                            <div><small>Total Trips</small><strong><?= $total_trips ?></strong></div>
                        </div>
                    <?php else: ?><div class="no-bus">No bus has been assigned to this driver.</div><?php endif; ?>
                </div>

                <div class="box">
                    <h2>Passenger & Booking Summary</h2>
                    <div class="details">
                        <div><small>Today's Passengers</small><strong><?= $today_bookings ?></strong></div>
                        <div><small>Total Bookings</small><strong><?= $total_bookings ?></strong></div>
                        <div><small>Total Scheduled Trips</small><strong><?= $total_trips ?></strong></div>
                        <div><small>Current Status</small><strong><?= $status ?></strong></div>
                    </div>
                </div>

                <div class="box danger">
                    <h2>Driver Account</h2>
                    <p>Deleting this driver will permanently remove the driver account and its related assignments.</p>
                    <a href="drivers.php?action=delete&id=<?= $id ?>" class="delete" onclick="return confirm('Are you sure you want to permanently delete this driver?')">Delete Driver Account</a>
                </div>
            </div>
        </div>
    </div>
</body>

</html>