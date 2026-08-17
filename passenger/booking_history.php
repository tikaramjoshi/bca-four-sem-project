<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != "passenger") {
    header("Location: ../login.php");
    exit();
}
require_once "../db.php";
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT name, profile_image, verification_status FROM users WHERE user_id=? AND role='passenger'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_result = $stmt->get_result();
$user = $user_result->fetch_assoc();
$passenger_name = $user['name'] ?? 'Passenger';
$profile_image = !empty($user['profile_image']) ? $user['profile_image'] : 'default.png';
$verification_status = $user['verification_status'] ?? 'pending';
$stmt->close();
$bookings = [];
$stmt = $conn->prepare("SELECT * FROM bookings WHERE user_id=? ORDER BY booking_id DESC");
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $booking_result = $stmt->get_result();
    while ($row = $booking_result->fetch_assoc()) {
        $bookings[] = $row;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking History</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #eef4fb;
            color: #222;
            min-height: 100vh;
        }

        .main {
            background: #1560bd;
            min-height: 65px;
            width: 100%;
        }

        nav {
            min-height: 65px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 35px;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        nav a {
            color: white;
            background: rgb(100, 94, 141);
            text-decoration: none;
            padding: 12px 16px;
            border-radius: 6px;
            font-size: 15px;
        }

        nav a:hover,
        nav a.active {
            background: rgba(255, 255, 255, .18);
        }

        .profile-dropdown {
            position: relative;
        }

        .profile-button {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .profile-name {
            color: orange;
            font-weight: bold;
        }

        .profile-image {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid white;
            cursor: pointer;
            background: white;
        }

        .status {
            font-size: 12px;
            font-weight: bold;
            padding: 4px 9px;
            border-radius: 12px;
            color: white;
        }

        .status.verified {
            background: #28a745;
        }

        .status.pending {
            background: #f39c12;
        }

        .status.rejected {
            background: #dc3545;
        }

        .profile-menu {
            display: none;
            position: absolute;
            right: 0;
            top: 55px;
            width: 250px;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            z-index: 9999;
        }

        .profile-menu.show {
            display: block;
        }

        .profile-menu-header {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px;
        }

        .dropdown-profile-image {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #1560bd;
        }

        .profile-menu-header strong {
            display: block;
            color: #222;
        }

        .profile-menu-header small {
            display: block;
            color: #777;
            margin-top: 3px;
        }

        .profile-menu hr {
            border: 0;
            border-top: 1px solid #eee;
        }

        .profile-menu a {
            display: block;
            padding: 12px 16px;
            color: #333;
            background: white;
            text-decoration: none;
        }

        .profile-menu a:hover {
            background: #f1f5f9;
            color: #1560bd;
        }

        .profile-menu .logout-link {
            color: #dc3545;
        }

        .history-container {
            width: 1000px;
            max-width: 94%;
            margin: 40px auto;
        }

        .page-title {
            text-align: center;
            margin-bottom: 25px;
        }

        .page-title h1 {
            color: #1560bd;
            font-size: 30px;
            margin-bottom: 8px;
        }

        .page-title p {
            color: #777;
        }

        .booking-card {
            background: white;
            border-radius: 12px;
            padding: 22px;
            margin-bottom: 20px;
            border-left: 5px solid #1560bd;
        }

        .booking-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
            margin-bottom: 18px;
        }

        .booking-id {
            font-size: 18px;
            font-weight: bold;
            color: #1560bd;
        }

        .booking-status {
            padding: 6px 13px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .confirmed {
            background: #d4edda;
            color: #218838;
        }

        .pending-booking {
            background: #fff3cd;
            color: #856404;
        }

        .cancelled {
            background: #f8d7da;
            color: #c82333;
        }

        .booking-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px 30px;
        }

        .booking-detail label {
            display: block;
            color: #777;
            font-size: 13px;
            margin-bottom: 5px;
        }

        .booking-detail strong {
            font-size: 15px;
            color: #222;
        }

        .booking-actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 20px;
            gap: 10px;
        }

        .ticket-btn {
            background: #1560bd;
            color: white;
            text-decoration: none;
            padding: 9px 17px;
            border-radius: 6px;
            font-size: 14px;
        }

        .ticket-btn:hover {
            background: #0d47a1;
        }

        .empty-history {
            background: white;
            border-radius: 12px;
            padding: 50px 20px;
            text-align: center;
        }

        .empty-history h2 {
            color: #1560bd;
            margin-bottom: 10px;
        }

        .empty-history p {
            color: #777;
            margin-bottom: 20px;
        }

        .book-btn {
            display: inline-block;
            background: #1560bd;
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 6px;
        }

        @media(max-width:700px) {
            nav {
                padding: 0 15px;
            }

            .nav-links a {
                padding: 10px 7px;
                font-size: 13px;
            }

            .profile-name {
                display: none;
            }

            .booking-details {
                grid-template-columns: 1fr;
            }

            .booking-top {
                align-items: flex-start;
                gap: 10px;
                flex-direction: column;
            }
        }
    </style>
</head>

<body>
    <div class="main">
        <nav>
            <div class="nav-links">
                <a href="dashboard.php">Home</a>
            </div>
            <div class="profile-dropdown">
                <div class="profile-button">
                    <span class="profile-name"><?= htmlspecialchars($passenger_name) ?></span>
                    <img src="../uploads/profile/<?= htmlspecialchars($profile_image) ?>" alt="Profile" class="profile-image" onclick="toggleProfileMenu(event)">
                </div>
                <div class="profile-menu" id="profileMenu">
                    <a href="profile.php"> My Profile</a>
                    <a href="booking_history.php"> My Bookings</a>
                    <a href="settings.php"> Settings</a>
                    <hr>
                    <a href="../logout.php" class="logout-link"> Logout</a>
                </div>
            </div>
        </nav>
    </div>
    <div class="history-container">
        <div class="page-title">
            <h1><span class="profile-name"><?= htmlspecialchars($passenger_name) ?></span>
                Booking History</h1>
            <p>View all your bus bookings and ticket details</p>
        </div>
        <?php if (empty($bookings)): ?>
            <div class="empty-history">
                <h2>No Bookings Found</h2>
                <p>You have not made any bus bookings yet.</p>
                <a href="dashboard.php" class="book-btn">Book a Bus</a>
            </div>
        <?php else: ?>
            <?php foreach ($bookings as $booking): ?>
                <?php
                $booking_status = strtolower($booking['status'] ?? 'pending');
                $status_class = $booking_status === 'confirmed' ? 'confirmed' : ($booking_status === 'cancelled' ? 'cancelled' : 'pending-booking');
                ?>
                <div class="booking-card">
                    <div class="booking-top">
                        <div class="booking-id">Booking #<?= htmlspecialchars($booking['booking_id'] ?? '') ?></div>
                        <div class="booking-status <?= $status_class ?>"><?= htmlspecialchars(ucfirst($booking_status)) ?></div>
                    </div>
                    <div class="booking-details">
                        <div class="booking-detail">
                            <label>Bus Name</label>
                            <strong><?= htmlspecialchars($booking['bus_name'] ?? 'N/A') ?></strong>
                        </div>
                        <div class="booking-detail">
                            <label>Bus Number</label>
                            <strong><?= htmlspecialchars($booking['bus_number'] ?? 'N/A') ?></strong>
                        </div>
                        <div class="booking-detail">
                            <label>Route</label>
                            <strong><?= htmlspecialchars($booking['route'] ?? 'N/A') ?></strong>
                        </div>
                        <div class="booking-detail">
                            <label>Travel Date</label>
                            <strong><?= htmlspecialchars($booking['travel_date'] ?? 'N/A') ?></strong>
                        </div>
                        <div class="booking-detail">
                            <label>Seat Number</label>
                            <strong><?= htmlspecialchars($booking['seat_number'] ?? 'N/A') ?></strong>
                        </div>
                        <div class="booking-detail">
                            <label>Amount</label>
                            <strong>Rs. <?= htmlspecialchars($booking['amount'] ?? '0') ?></strong>
                        </div>
                        <div class="booking-detail">
                            <label>Booking Date</label>
                            <strong><?= htmlspecialchars($booking['created_at'] ?? 'N/A') ?></strong>
                        </div>
                    </div>
                    <div class="booking-actions">
                        <a href="ticket.php?id=<?= urlencode($booking['booking_id'] ?? '') ?>" class="ticket-btn">View Ticket</a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <script>
        function toggleProfileMenu(event) {
            event.stopPropagation();
            document.getElementById("profileMenu").classList.toggle("show");
        }
        document.addEventListener("click", function(event) {
            const dropdown = document.querySelector(".profile-dropdown");
            const menu = document.getElementById("profileMenu");
            if (!dropdown.contains(event.target)) {
                menu.classList.remove("show");
            }
        });
    </script>
</body>

</html>