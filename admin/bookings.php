<?php
session_start();
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit;
}

if (!isset($conn) || !$conn instanceof mysqli) {
    exit('Database connection is not available.');
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bookingId = isset($_POST['booking_id']) ? (int)$_POST['booking_id'] : 0;
    $action = $_POST['action'] ?? '';

    if ($bookingId > 0 && $action === 'status') {
        $status = strtolower(trim($_POST['status'] ?? ''));
        $allowedStatuses = ['pending', 'confirmed', 'paid', 'cancelled'];

        if (!in_array($status, $allowedStatuses, true)) {
            $error = 'Invalid booking status.';
        } else {
            $stmt = $conn->prepare("UPDATE bookings SET status=? WHERE booking_id=? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('si', $status, $bookingId);
                if ($stmt->execute()) {
                    $message = 'Seat status updated successfully.';
                } else {
                    $error = 'Unable to update seat status.';
                }
                $stmt->close();
            } else {
                $error = 'Database query preparation failed.';
            }
        }
    }

    if ($bookingId > 0 && $action === 'delete') {
        $stmt = $conn->prepare("DELETE FROM bookings WHERE booking_id=? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $bookingId);
            if ($stmt->execute()) {
                $message = 'Seat booking deleted successfully.';
            } else {
                $error = 'Unable to delete booking.';
            }
            $stmt->close();
        } else {
            $error = 'Database query preparation failed.';
        }
    }
}

$search = trim($_GET['search'] ?? '');

$sql = "SELECT bk.booking_id,bk.booking_group_id,bk.user_id,bk.schedule_id,bk.bus_name,bk.bus_number,bk.route,bk.travel_date,bk.seat_number,bk.amount,bk.status,bk.created_at,u.name,u.email,u.phone FROM bookings bk LEFT JOIN users u ON bk.user_id=u.user_id";
$params = [];
$types = '';

if ($search !== '') {
    $sql .= " WHERE CAST(bk.booking_id AS CHAR) LIKE ? OR bk.booking_group_id LIKE ? OR u.name LIKE ? OR u.email LIKE ? OR bk.bus_name LIKE ? OR bk.bus_number LIKE ? OR bk.route LIKE ? OR bk.seat_number LIKE ?";
    $value = '%' . $search . '%';
    $params = [$value, $value, $value, $value, $value, $value, $value, $value];
    $types = 'ssssssss';
}

$sql .= " ORDER BY bk.booking_id DESC";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    exit('Unable to prepare booking query.');
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

$groupedBookings = [];

while ($row = $result->fetch_assoc()) {
    $groupId = trim($row['booking_group_id'] ?? '');

    if ($groupId === '') {
        $groupId = 'BOOKING-' . str_pad((int)$row['booking_id'], 2, '0', STR_PAD_LEFT);
    }

    $groupedBookings[$groupId][] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Bookings</title>
    <link rel="stylesheet" href="side.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        * {
            box-sizing: border-box
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f6f8;
            color: #263238
        }

        .wrap {
            width: 100%;
            margin: 30px auto
        }

        .top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px
        }

        .top h1 {
            margin: 0;
            color: #1560bd
        }

        .top p {
            margin-top: 6px;
            color: #64748b
        }

        .card {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 12px #00000014;
            padding: 20px
        }

        .message {
            background: #dcfce7;
            color: #166534;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 15px;
            font-weight: bold
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 15px;
            font-weight: bold
        }

        .search {
            display: flex;
            gap: 8px;
            margin-bottom: 20px
        }

        .search input {
            flex: 1;
            padding: 11px 13px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 14px
        }

        .button {
            background: #1560bd;
            color: #fff;
            text-decoration: none;
            border: 0;
            padding: 10px 15px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold
        }

        .button:hover {
            background: #0d47a1
        }

        .group {
            border: 1px solid #dbe3ec;
            border-radius: 10px;
            margin-bottom: 25px;
            overflow: hidden
        }

        .group-header {
            background: #1560bd;
            color: #fff;
            padding: 15px 18px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap
        }

        .group-id {
            font-size: 18px;
            font-weight: bold
        }

        .group-info {
            font-size: 13px;
            opacity: .95;
            margin-top: 5px
        }

        .group-body {
            padding: 18px
        }

        .trip-info {
            display: grid;
            grid-template-columns: repeat(4, minmax(150px, 1fr));
            gap: 12px;
            margin-bottom: 18px
        }

        .trip-box {
            background: #f8fafc;
            border-radius: 7px;
            padding: 12px
        }

        .trip-box small {
            display: block;
            color: #64748b;
            margin-bottom: 5px
        }

        .trip-box strong {
            color: #222
        }

        .table-wrap {
            overflow-x: auto
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 900px
        }

        th,
        td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0
        }

        th {
            background: #f8fafc;
            color: #334155;
            font-size: 13px
        }

        td {
            font-size: 14px
        }

        .seat-number {
            display: inline-flex;
            width: 38px;
            height: 38px;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            background: #1560bd;
            color: #fff;
            font-weight: bold
        }

        .status {
            display: inline-block;
            padding: 6px 11px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold
        }

        .pending {
            background: #fef3c7;
            color: #92400e
        }

        .confirmed {
            background: #dcfce7;
            color: #166534
        }

        .paid {
            background: #dbeafe;
            color: #1e40af
        }

        .cancelled {
            background: #fee2e2;
            color: #991b1b
        }

        .actions {
            display: flex;
            align-items: center;
            gap: 7px;
            flex-wrap: wrap
        }

        .actions form {
            margin: 0
        }

        .action-button {
            border: 0;
            color: #fff;
            padding: 7px 10px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
            font-weight: bold
        }

        .approve {
            background: #198754
        }

        .approve:hover {
            background: #146c43
        }

        .reject {
            background: #dc3545
        }

        .reject:hover {
            background: #bb2d3b
        }

        .delete {
            background: #64748b
        }

        .delete:hover {
            background: #475569
        }

        .count {
            background: #fff;
            color: #1560bd;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            margin-left: 4px
        }

        .empty {
            text-align: center;
            padding: 50px;
            color: #64748b
        }

        @media(max-width:900px) {
            .trip-info {
                grid-template-columns: repeat(2, 1fr)
            }
        }

        @media(max-width:600px) {
            .trip-info {
                grid-template-columns: 1fr
            }

            .search {
                flex-direction: column
            }

            .group-header {
                align-items: flex-start;
                flex-direction: column
            }
        }
    </style>
</head>

<body>
    <?php include "admin_header.php"; ?>
    <div class="content">
        <main class="wrap">
            <div class="top">
                <div>
                    <h1>Booking Management</h1>
                    <p>Manage passenger bookings and approve or reject seats individually.</p>
                </div>
            </div>
            <section class="card">
                <?php if ($message): ?>
                    <div class="message"><?= htmlspecialchars($message) ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <form class="search" method="get">
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search Group ID, passenger, bus, route or seat...">
                    <button type="submit" class="button"><i class="fa fa-search"></i> Search</button>
                    <?php if ($search !== ''): ?>
                        <a href="bookings.php" class="button" style="background:#64748b">Clear</a>
                    <?php endif; ?>
                </form>
                <?php if (!empty($groupedBookings)): ?>
                    <?php foreach ($groupedBookings as $groupId => $group): ?>
                        <?php
                        $first = $group[0];
                        $totalGroupSeats = count($group);
                        $confirmedCount = 0;
                        $pendingCount = 0;
                        $cancelledCount = 0;
                        foreach ($group as $seatRow) {
                            $seatStatus = strtolower($seatRow['status'] ?? 'pending');
                            if ($seatStatus === 'confirmed') {
                                $confirmedCount++;
                            } elseif ($seatStatus === 'pending') {
                                $pendingCount++;
                            } elseif ($seatStatus === 'cancelled') {
                                $cancelledCount++;
                            }
                        }
                        ?>
                        <div class="group">
                            <div class="group-header">
                                <div>
                                    <div class="group-id"><?= htmlspecialchars($groupId) ?></div>
                                    <div class="group-info">Passenger: <strong><?= htmlspecialchars($first['name'] ?? 'Unknown') ?></strong> | <?= $totalGroupSeats ?> Seat(s)</div>
                                </div>
                                <div>
                                    <span class="count">Pending: <?= $pendingCount ?></span>
                                    <span class="count">Confirmed: <?= $confirmedCount ?></span>
                                    <span class="count">Rejected: <?= $cancelledCount ?></span>
                                </div>
                            </div>
                            <div class="group-body">
                                <div class="trip-info">
                                    <div class="trip-box">
                                        <small>Passenger</small>
                                        <strong><?= htmlspecialchars($first['name'] ?? 'Unknown') ?></strong>
                                    </div>
                                    <div class="trip-box">
                                        <small>Bus</small>
                                        <strong><?= htmlspecialchars($first['bus_name'] ?? 'N/A') ?></strong>
                                    </div>
                                    <div class="trip-box">
                                        <small>Bus Number</small>
                                        <strong><?= htmlspecialchars($first['bus_number'] ?? 'N/A') ?></strong>
                                    </div>
                                    <div class="trip-box">
                                        <small>Route</small>
                                        <strong><?= htmlspecialchars($first['route'] ?? 'N/A') ?></strong>
                                    </div>
                                    <div class="trip-box">
                                        <small>Travel Date</small>
                                        <strong><?= !empty($first['travel_date']) ? htmlspecialchars(date('d M Y', strtotime($first['travel_date']))) : 'N/A' ?></strong>
                                    </div>
                                    <div class="trip-box">
                                        <small>Email</small>
                                        <strong><?= htmlspecialchars($first['email'] ?? '') ?></strong>
                                    </div>
                                    <div class="trip-box">
                                        <small>Phone</small>
                                        <strong><?= htmlspecialchars($first['phone'] ?? '') ?></strong>
                                    </div>
                                    <div class="trip-box">
                                        <small>Booking Date</small>
                                        <strong><?= !empty($first['created_at']) ? htmlspecialchars(date('d M Y h:i A', strtotime($first['created_at']))) : 'N/A' ?></strong>
                                    </div>
                                </div>
                                <div class="table-wrap">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>Booking ID</th>
                                                <th>Seat</th>
                                                <th>Amount</th>
                                                <th>Status</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($group as $booking): ?>
                                                <?php $status = strtolower($booking['status'] ?? 'pending'); ?>
                                                <tr>
                                                    <td>#<?= (int)$booking['booking_id'] ?></td>
                                                    <td><span class="seat-number"><?= htmlspecialchars($booking['seat_number']) ?></span></td>
                                                    <td>Rs. <?= number_format((float)$booking['amount'], 2) ?></td>
                                                    <td><span class="status <?= htmlspecialchars($status) ?>"><?= htmlspecialchars(ucfirst($status)) ?></span></td>
                                                    <td>
                                                        <div class="actions">
                                                            <?php if ($status !== 'confirmed' && $status !== 'cancelled'): ?>
                                                                <form method="post">
                                                                    <input type="hidden" name="booking_id" value="<?= (int)$booking['booking_id'] ?>">
                                                                    <input type="hidden" name="action" value="status">
                                                                    <input type="hidden" name="status" value="confirmed">
                                                                    <button type="submit" class="action-button approve" onclick="return confirm('Approve seat <?= htmlspecialchars($booking['seat_number']) ?>?')"><i class="fa fa-check"></i> Approve</button>
                                                                </form>
                                                            <?php endif; ?>
                                                            <?php if ($status !== 'cancelled'): ?>
                                                                <form method="post">
                                                                    <input type="hidden" name="booking_id" value="<?= (int)$booking['booking_id'] ?>">
                                                                    <input type="hidden" name="action" value="status">
                                                                    <input type="hidden" name="status" value="cancelled">
                                                                    <button type="submit" class="action-button reject" onclick="return confirm('Reject seat <?= htmlspecialchars($booking['seat_number']) ?>?')"><i class="fa fa-times"></i> Reject</button>
                                                                </form>
                                                            <?php endif; ?>
                                                            <form method="post" onsubmit="return confirm('Delete this seat booking?')">
                                                                <input type="hidden" name="booking_id" value="<?= (int)$booking['booking_id'] ?>">
                                                                <input type="hidden" name="action" value="delete">
                                                                <button type="submit" class="action-button delete"><i class="fa fa-trash"></i></button>
                                                            </form>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty">
                        <i class="fa fa-ticket" style="font-size:40px;margin-bottom:15px"></i>
                        <h3>No bookings found.</h3>
                        <p>Passenger bookings will appear here.</p>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>
</body>

</html>