<?php
session_start();
require_once "../db.php";
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: passengers.php");
    exit;
}
$passenger_id = (int)$_GET['id'];
$stmt = $conn->prepare("SELECT user_id,name,email,phone,profile_image,verification_status,created_at FROM users WHERE user_id=? AND role='passenger' LIMIT 1");
$stmt->bind_param("i", $passenger_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    header("Location: passengers.php");
    exit;
}
$passenger = $result->fetch_assoc();
$booking_stmt = $conn->prepare("SELECT booking_id,user_id,bus_name,bus_number,route,travel_date,seat_number,amount,status,created_at FROM bookings WHERE user_id=? ORDER BY booking_id DESC");
$booking_stmt->bind_param("i", $passenger_id);
$booking_stmt->execute();
$booking_result = $booking_stmt->get_result();
$bookings = [];
while ($row = $booking_result->fetch_assoc()) {
    $bookings[] = $row;
}
$total_bookings = count($bookings);
$profile_image = trim((string)($passenger['profile_image'] ?? ''));
$image = $profile_image !== '' ? "../uploads/profile/" . basename($profile_image) : "";
$verification = $passenger['verification_status'] ?? 'pending';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Passenger Details</title>
    <link rel="stylesheet" href="view_passenger.css">
</head>

<body>
    <div class="page">
        <div class="page-header">
            <div>
                <h1>Passenger Details</h1>
                <p>View passenger information and booking history.</p>
            </div>
            <a href="passengers.php" class="back-btn">Back to Passengers</a>
        </div>
        <div class="profile-card">
            <div class="profile-top">
                <div class="profile-avatar">
                    <?php if ($image !== ''): ?>
                        <img src="<?= htmlspecialchars($image) ?>" alt="Passenger Profile" onerror="this.style.display='none';">
                    <?php endif; ?>
                </div>
                <div class="profile-info">
                    <h2><?= htmlspecialchars($passenger['name']) ?></h2>
                    <p><?= htmlspecialchars($passenger['email']) ?></p>
                    <p><?= htmlspecialchars($passenger['phone']) ?></p>
                    <span class="status <?= htmlspecialchars($verification) ?>">
                        <?= $verification === 'verified' ? 'Verified' : ($verification === 'rejected' ? 'Rejected' : 'Pending') ?>
                    </span>
                </div>
            </div>
            <div class="details-grid">
                <div class="detail-box"><small>Passenger ID</small><strong><?= (int)$passenger['user_id'] ?></strong></div>
                <div class="detail-box"><small>Full Name</small><strong><?= htmlspecialchars($passenger['name']) ?></strong></div>
                <div class="detail-box"><small>Email</small><strong><?= htmlspecialchars($passenger['email']) ?></strong></div>
                <div class="detail-box"><small>Phone</small><strong><?= htmlspecialchars($passenger['phone']) ?></strong></div>
                <div class="detail-box"><small>Account Role</small><strong>Passenger</strong></div>
                <div class="detail-box"><small>Verification</small><strong><?= $verification === 'verified' ? 'Verified' : ($verification === 'rejected' ? 'Rejected' : 'Pending') ?></strong></div>
                <div class="detail-box"><small>Total Bookings</small><strong><?= $total_bookings ?> Booking(s)</strong></div>
                <div class="detail-box"><small>Registered Date</small><strong><?= date("d M Y", strtotime($passenger['created_at'])) ?></strong></div>
                <div class="detail-box"><small>Account Status</small><strong>Active</strong></div>
            </div>
        </div>
        <div class="booking-card">
            <div class="booking-header">
                <h2>Booking History</h2>
                <span class="booking-count"><?= $total_bookings ?> Booking(s)</span>
            </div>
            <?php if (!empty($bookings)): ?>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Booking ID</th>
                                <th>Bus Name</th>
                                <th>Bus Number</th>
                                <th>Route</th>
                                <th>Travel Date</th>
                                <th>Seat</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bookings as $booking): ?>
                                <?php $booking_status = strtolower(trim($booking['status'] ?? 'pending')); ?>
                                <tr>
                                    <td>#<?= (int)$booking['booking_id'] ?></td>
                                    <td><?= htmlspecialchars($booking['bus_name']) ?></td>
                                    <td><?= htmlspecialchars($booking['bus_number']) ?></td>
                                    <td><?= htmlspecialchars($booking['route']) ?></td>
                                    <td><?= !empty($booking['travel_date']) ? date("d M Y", strtotime($booking['travel_date'])) : "-" ?></td>
                                    <td><?= htmlspecialchars($booking['seat_number']) ?></td>
                                    <td>Rs. <?= number_format((float)$booking['amount'], 2) ?></td>
                                    <td><span class="booking-status <?= htmlspecialchars($booking_status) ?>"><?= ucfirst(htmlspecialchars($booking_status)) ?></span></td>
                                    <td><?= !empty($booking['created_at']) ? date("d M Y", strtotime($booking['created_at'])) : "-" ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="no-bookings">
                    <div class="no-bookings-icon"></div>
                    <h3>No Bookings Found</h3>
                    <p>This passenger has not made any bookings yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>