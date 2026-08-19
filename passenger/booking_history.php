<?php
session_start();
require_once "../db.php";

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'passenger') {
    header("Location: ../login.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];

$stmt = $conn->prepare("SELECT name,profile_image,verification_status FROM users WHERE user_id=? AND role='passenger' LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    session_destroy();
    header("Location: ../login.php");
    exit;
}

$name = $user['name'] ?? 'Passenger';
$image = !empty($user['profile_image']) ? $user['profile_image'] : 'default.png';
$verification = $user['verification_status'] ?? 'pending';

$sql = "SELECT
booking_group_id,
MIN(booking_id) AS booking_id,
bus_name,
bus_number,
route,
travel_date,
GROUP_CONCAT(CASE WHEN status IN('confirmed','paid') THEN seat_number END ORDER BY CAST(seat_number AS UNSIGNED) SEPARATOR ', ') AS confirmed_seats,
GROUP_CONCAT(CASE WHEN status='pending' THEN seat_number END ORDER BY CAST(seat_number AS UNSIGNED) SEPARATOR ', ') AS pending_seats,
GROUP_CONCAT(CASE WHEN status='cancelled' THEN seat_number END ORDER BY CAST(seat_number AS UNSIGNED) SEPARATOR ', ') AS cancelled_seats,
COUNT(*) AS total_seats,
SUM(CASE WHEN status!='cancelled' THEN amount ELSE 0 END) AS total_amount,
MIN(created_at) AS booking_date,
SUM(status IN('confirmed','paid')) AS confirmed_count,
SUM(status='pending') AS pending_count,
SUM(status='cancelled') AS cancelled_count
FROM bookings
WHERE user_id=?
GROUP BY booking_group_id,bus_name,bus_number,route,travel_date
ORDER BY MIN(booking_id) DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Booking History</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box
        }

        body {
            font-family: Arial;
            background: #eef4fb;
            color: #222
        }

        .main {
            background: #1560bd;
            height: 65px
        }

        nav {
            height: 65px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 35px
        }

        nav a {
            color: #fff;
            background: #645e8d;
            text-decoration: none;
            padding: 12px 16px;
            border-radius: 6px
        }

        .profile {
            display: flex;
            align-items: center;
            gap: 10px;
            color: orange;
            font-weight: bold
        }

        .profile img {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid white
        }

        .verify {
            padding: 5px 9px;
            border-radius: 15px;
            color: white;
            font-size: 12px
        }

        .verified {
            background: #28a745
        }

        .pending {
            background: #f39c12
        }

        .rejected {
            background: #dc3545
        }

        .container {
            max-width: 1000px;
            width: 94%;
            margin: 40px auto
        }

        .title {
            text-align: center;
            margin-bottom: 25px
        }

        .title h1 {
            color: #1560bd;
            margin-bottom: 8px
        }

        .title p {
            color: #777
        }

        .card {
            background: #fff;
            border-left: 5px solid #1560bd;
            border-radius: 12px;
            padding: 22px;
            margin-bottom: 20px;
            box-shadow: 0 3px 12px #0001
        }

        .top {
            display: flex;
            justify-content: space-between;
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
            margin-bottom: 18px
        }

        .id {
            font-size: 18px;
            font-weight: bold;
            color: #1560bd
        }

        .badge {
            padding: 6px 13px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold
        }

        .confirmed {
            background: #d4edda;
            color: #218838
        }

        .pending-b {
            background: #fff3cd;
            color: #856404
        }

        .cancelled {
            background: #f8d7da;
            color: #c82333
        }

        .completed {
            background: #dbeafe;
            color: #1d4ed8
        }

        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px 30px
        }

        .item label {
            display: block;
            color: #777;
            font-size: 13px;
            margin-bottom: 5px
        }

        .item strong {
            font-size: 15px
        }

        .green {
            color: #218838 !important
        }

        .orange {
            color: #d68910 !important
        }

        .red {
            color: #dc3545 !important
        }

        .blue {
            color: #1d4ed8 !important
        }

        .counts {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            border-top: 1px solid #eee;
            margin-top: 20px;
            padding-top: 18px
        }

        .count {
            padding: 8px 13px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: bold
        }

        .cg {
            background: #d4edda;
            color: #218838
        }

        .co {
            background: #fff3cd;
            color: #856404
        }

        .cr {
            background: #f8d7da;
            color: #c82333
        }

        .actions {
            text-align: right;
            margin-top: 20px
        }

        .ticket {
            background: #1560bd;
            color: #fff;
            text-decoration: none;
            padding: 9px 17px;
            border-radius: 6px
        }

        .empty {
            text-align: center;
            background: #fff;
            padding: 50px;
            border-radius: 12px
        }

        .book {
            display: inline-block;
            background: #1560bd;
            color: #fff;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 6px;
            margin-top: 15px
        }

        @media(max-width:700px) {
            nav {
                padding: 0 15px
            }

            .profile span:first-child {
                display: none
            }

            .grid {
                grid-template-columns: 1fr
            }

            .top {
                flex-direction: column;
                gap: 10px
            }
        }
    </style>
</head>

<body>

    <div class="main">
        <nav>
            <a href="dashboard.php">Home</a>
            <div class="profile">
                <span><?= htmlspecialchars($name) ?></span>
                <span class="verify <?= htmlspecialchars($verification) ?>"><?= ucfirst(htmlspecialchars($verification)) ?></span>
                <img src="../uploads/profile/<?= htmlspecialchars($image) ?>" onerror="this.src='../images/default.png'">
            </div>
        </nav>
    </div>

    <div class="container">

        <div class="title">
            <h1><?= htmlspecialchars($name) ?> Booking History</h1>
            <p>View all your bus bookings and ticket details</p>
        </div>

        <?php if ($result->num_rows == 0): ?>

            <div class="empty">
                <h2>No Bookings Found</h2>
                <p>You have not made any bus bookings yet.</p>
                <a href="dashboard.php" class="book">Book a Bus</a>
            </div>

        <?php endif; ?>

        <?php while ($b = $result->fetch_assoc()): ?>

            <?php
            $confirmed = (int)$b['confirmed_count'];
            $pending = (int)$b['pending_count'];
            $cancelled = (int)$b['cancelled_count'];
            $total = (int)$b['total_seats'];

            if ($pending > 0) {
                $status = 'Waiting';
                $statusClass = 'pending-b';
            } elseif ($cancelled == $total) {
                $status = 'Cancelled';
                $statusClass = 'cancelled';
            } elseif ($cancelled > 0) {
                $status = 'Completed';
                $statusClass = 'completed';
            } elseif ($confirmed == $total) {
                $status = 'Confirmed';
                $statusClass = 'confirmed';
            } else {
                $status = 'Waiting';
                $statusClass = 'pending-b';
            }
            ?>

            <div class="card">

                <div class="top">
                    <div class="id">Booking #<?= htmlspecialchars($b['booking_id']) ?></div>
                    <div class="badge <?= $statusClass ?>"><?= htmlspecialchars($status) ?></div>
                </div>

                <div class="grid">

                    <div class="item">
                        <label>Group ID</label>
                        <strong><?= htmlspecialchars($b['booking_group_id']) ?></strong>
                    </div>

                    <div class="item">
                        <label>Bus</label>
                        <strong><?= htmlspecialchars($b['bus_name']) ?></strong>
                    </div>

                    <div class="item">
                        <label>Bus Number</label>
                        <strong><?= htmlspecialchars($b['bus_number']) ?></strong>
                    </div>

                    <div class="item">
                        <label>Route</label>
                        <strong><?= htmlspecialchars($b['route']) ?></strong>
                    </div>

                    <div class="item">
                        <label>Travel Date</label>
                        <strong><?= date('d M Y', strtotime($b['travel_date'])) ?></strong>
                    </div>

                    <div class="item">
                        <label>Confirmed Seats</label>
                        <strong class="green"><?= htmlspecialchars($b['confirmed_seats'] ?: 'None') ?></strong>
                    </div>

                    <div class="item">
                        <label>Pending Seats</label>
                        <strong class="orange"><?= htmlspecialchars($b['pending_seats'] ?: 'None') ?></strong>
                    </div>

                    <div class="item">
                        <label>Cancelled Seats</label>
                        <strong class="red"><?= htmlspecialchars($b['cancelled_seats'] ?: 'None') ?></strong>
                    </div>

                    <div class="item">
                        <label>Total Seats</label>
                        <strong><?= $total ?> Seats</strong>
                    </div>

                    <div class="item">
                        <label>Total Amount</label>
                        <strong>Rs. <?= number_format((float)$b['total_amount'], 2) ?></strong>
                    </div>

                    <div class="item">
                        <label>Booking Date</label>
                        <strong><?= date('d M Y h:i A', strtotime($b['booking_date'])) ?></strong>
                    </div>

                </div>

                <div class="counts">
                    <span class="count cg">Confirmed: <?= $confirmed ?></span>
                    <span class="count co">Pending: <?= $pending ?></span>
                    <span class="count cr">Cancelled: <?= $cancelled ?></span>
                </div>

                <div class="actions">
                    <a class="ticket" href="ticket.php?group_id=<?= urlencode($b['booking_group_id']) ?>">View Ticket</a>
                </div>

            </div>

        <?php endwhile; ?>

    </div>
</body>

</html>