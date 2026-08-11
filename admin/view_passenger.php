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
$stmt = $conn->prepare("
    SELECT user_id,name,email,phone,profile_image,verification_status,created_at
    FROM users
    WHERE user_id = ? AND role = 'passenger'
    LIMIT 1
");
$stmt->bind_param("i", $passenger_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    header("Location: passengers.php");
    exit;
}
$passenger = $result->fetch_assoc();
$booking_stmt = $conn->prepare("
    SELECT booking_id,user_id,bus_name,bus_number,route,travel_date,seat_number,amount,status,created_at
    FROM bookings
    WHERE user_id = ?
    ORDER BY booking_id DESC
");
$booking_stmt->bind_param("i", $passenger_id);
$booking_stmt->execute();
$booking_result = $booking_stmt->get_result();
$bookings = [];
while ($row = $booking_result->fetch_assoc()) {
    $bookings[] = $row;
}
$total_bookings = count($bookings);
$profile_image = trim((string)($passenger['profile_image'] ?? ''));
$image = "";
if ($profile_image !== '') {
    $image = "../uploads/profile/" . basename($profile_image);
}
$verification = $passenger['verification_status'] ?? 'pending';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Passenger Details</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, Helvetica, sans-serif
        }

        body {
            background: #f4f6f9;
            color: #222
        }

        .page {
            width: 94%;
            max-width: 1250px;
            margin: 30px auto
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 25px
        }

        .page-header h1 {
            font-size: 28px;
            margin-bottom: 6px
        }

        .page-header p {
            color: #777;
            font-size: 14px
        }

        .back-btn {
            text-decoration: none;
            background: #1560bd;
            color: #fff;
            padding: 11px 18px;
            border-radius: 7px;
            font-size: 14px
        }

        .back-btn:hover {
            background: #0d4f9c
        }

        .profile-card {
            background: #fff;
            border-radius: 14px;
            padding: 30px;
            box-shadow: 0 3px 15px rgba(0, 0, 0, .08);
            margin-bottom: 25px
        }

        .profile-top {
            display: flex;
            align-items: center;
            gap: 25px;
            padding-bottom: 25px;
            border-bottom: 1px solid #eee
        }

        .profile-avatar {
            width: 110px;
            height: 110px;
            border-radius: 50%;
            overflow: hidden;
            border: 3px solid #1560bd;
            flex-shrink: 0;
            background: #f1f3f6
        }

        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block
        }

        .profile-info h2 {
            font-size: 25px;
            margin-bottom: 7px
        }

        .profile-info p {
            color: #777;
            margin-bottom: 5px;
            font-size: 14px
        }

        .status {
            display: inline-block;
            margin-top: 8px;
            padding: 7px 13px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold
        }

        .status.verified {
            background: #d1e7dd;
            color: #0f5132
        }

        .status.pending {
            background: #fff3cd;
            color: #856404
        }

        .status.rejected {
            background: #f8d7da;
            color: #842029
        }

        .details-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-top: 25px
        }

        .detail-box {
            background: #f7f9fc;
            padding: 17px;
            border-radius: 9px
        }

        .detail-box small {
            display: block;
            color: #777;
            font-size: 13px;
            margin-bottom: 7px
        }

        .detail-box strong {
            font-size: 15px;
            word-break: break-word
        }

        .booking-card {
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 3px 15px rgba(0, 0, 0, .08);
            overflow: hidden
        }

        .booking-header {
            padding: 20px 22px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center
        }

        .booking-header h2 {
            font-size: 19px
        }

        .booking-count {
            color: #1560bd;
            font-weight: bold;
            font-size: 14px
        }

        .table-wrapper {
            overflow-x: auto
        }

        table {
            width: 100%;
            min-width: 900px;
            border-collapse: collapse
        }

        th {
            background: #f7f9fc;
            color: #555;
            font-size: 13px;
            text-align: left;
            padding: 15px;
            border-bottom: 1px solid #eee
        }

        td {
            padding: 15px;
            font-size: 14px;
            border-bottom: 1px solid #eee
        }

        tr:hover td {
            background: #fafcff
        }

        .booking-status {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold
        }

        .booking-status.pending {
            background: #fff3cd;
            color: #856404
        }

        .booking-status.confirmed {
            background: #d1e7dd;
            color: #0f5132
        }

        .booking-status.cancelled {
            background: #f8d7da;
            color: #842029
        }

        .no-bookings {
            text-align: center;
            padding: 50px 20px;
            color: #777
        }

        .no-bookings-icon {
            font-size: 42px;
            margin-bottom: 10px
        }

        @media(max-width:900px) {
            .details-grid {
                grid-template-columns: repeat(2, 1fr)
            }
        }

        @media(max-width:600px) {
            .page {
                width: 94%;
                margin: 20px auto
            }

            .page-header {
                align-items: flex-start;
                flex-direction: column
            }

            .profile-top {
                flex-direction: column;
                text-align: center
            }

            .details-grid {
                grid-template-columns: 1fr
            }

            .profile-card {
                padding: 20px
            }
        }
    </style>
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
                        <?php if ($verification === 'verified'): ?>
                            Verified
                        <?php elseif ($verification === 'rejected'): ?>
                            Rejected
                        <?php else: ?>
                            Pending
                        <?php endif; ?>
                    </span>
                </div>
            </div>
            <div class="details-grid">
                <div class="detail-box">
                    <small>Passenger ID</small>
                    <strong><?= (int)$passenger['user_id'] ?></strong>
                </div>
                <div class="detail-box">
                    <small>Full Name</small>
                    <strong><?= htmlspecialchars($passenger['name']) ?></strong>
                </div>
                <div class="detail-box">
                    <small>Email</small>
                    <strong><?= htmlspecialchars($passenger['email']) ?></strong>
                </div>
                <div class="detail-box">
                    <small>Phone</small>
                    <strong><?= htmlspecialchars($passenger['phone']) ?></strong>
                </div>
                <div class="detail-box">
                    <small>Account Role</small>
                    <strong>Passenger</strong>
                </div>
                <div class="detail-box">
                    <small>Verification</small>
                    <strong>
                        <?php if ($verification === 'verified'): ?>
                            Verified
                        <?php elseif ($verification === 'rejected'): ?>
                            Rejected
                        <?php else: ?>
                            Pending
                        <?php endif; ?>
                    </strong>
                </div>
                <div class="detail-box">
                    <small>Total Bookings</small>
                    <strong><?= $total_bookings ?> Booking(s)</strong>
                </div>
                <div class="detail-box">
                    <small>Registered Date</small>
                    <strong><?= date("d M Y", strtotime($passenger['created_at'])) ?></strong>
                </div>
                <div class="detail-box">
                    <small>Account Status</small>
                    <strong>Active</strong>
                </div>
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