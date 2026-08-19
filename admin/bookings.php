<?php
session_start();
require_once __DIR__ . '/../db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}
if (!isset($conn) || !$conn instanceof mysqli) exit('Database connection is not available.');
$statusInfo = $conn->query("SHOW COLUMNS FROM bookings LIKE 'status'")->fetch_assoc();
$statusOptions = ['pending', 'confirmed', 'paid', 'cancelled'];
if (!empty($statusInfo['Type']) && preg_match('/^enum\\((.*)\\)$/i', $statusInfo['Type'], $matches)) {
    $statusOptions = str_getcsv($matches[1], ',', "'");
}
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bookingId = isset($_POST['booking_id']) ? (int)$_POST['booking_id'] : 0;
    $action = $_POST['action'] ?? '';
    if ($bookingId > 0 && $action === 'delete') {
        $stmt = $conn->prepare('DELETE FROM bookings WHERE booking_id = ?');
        $stmt->bind_param('i', $bookingId);
        $stmt->execute();
        $message = 'Booking deleted successfully.';
    }
    if ($bookingId > 0 && $action === 'status') {
        $status = $_POST['status'] ?? '';
        if (in_array($status, $statusOptions, true)) {
            $stmt = $conn->prepare('UPDATE bookings SET status = ? WHERE booking_id = ?');
            $stmt->bind_param('si', $status, $bookingId);
            $stmt->execute();
            $message = 'Booking status updated successfully.';
        }
    }
}
$search = trim($_GET['search'] ?? '');
$sql = 'SELECT bk.booking_id, bk.bus_name, bk.bus_number, bk.route, bk.travel_date, bk.seat_number, bk.amount, bk.status, bk.created_at, u.name, u.email, u.phone FROM bookings bk LEFT JOIN users u ON bk.user_id = u.user_id';
$params = [];
$types = '';
if ($search !== '') {
    $sql .= ' WHERE CAST(bk.booking_id AS CHAR) LIKE ? OR u.name LIKE ? OR u.email LIKE ? OR bk.bus_name LIKE ? OR bk.bus_number LIKE ? OR bk.route LIKE ?';
    $value = '%' . $search . '%';
    $params = [$value, $value, $value, $value, $value, $value];
    $types = 'ssssss';
}
$sql .= ' ORDER BY bk.booking_id DESC';
$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$bookings = $stmt->get_result();
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Bookings</title>
    <link rel="stylesheet" href="side.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f8;
            margin: 0;
            color: #263238
        }

        .wrap {
            /* width: min(1400px, 94%); */
            margin: 30px auto
        }

        .top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px
        }

        .top h1 {
            margin: 0
        }

        .button {
            background: #1560bd;
            color: #fff;
            text-decoration: none;
            border: 0;
            padding: 10px 14px;
            border-radius: 6px;
            cursor: pointer
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
            margin-bottom: 15px
        }

        .search {
            display: flex;
            gap: 8px;
            margin-bottom: 18px
        }

        .search input {
            flex: 1;
            padding: 10px;
            border: 1px solid #cbd5e1;
            border-radius: 6px
        }

        .table {
            overflow-x: auto
        }

        table {
            width: 100%;
            min-width: 1100px;
            border-collapse: collapse
        }

        th,
        td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0
        }

        th {
            background: #f8fafc
        }

        .status {
            padding: 5px 9px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold
        }

        .pending {
            background: #fef3c7;
            color: #92400e
        }

        .confirmed,
        .paid {
            background: #dcfce7;
            color: #166534
        }

        .cancelled {
            background: #fee2e2;
            color: #991b1b
        }

        .actions {
            display: flex;
            gap: 6px;
            align-items: center
        }

        .actions form {
            display: flex;
            gap: 5px
        }

        .actions select {
            padding: 6px
        }

        .delete {
            background: #dc2626
        }

        .empty {
            text-align: center;
            padding: 35px;
            color: #64748b
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
                    <p>Manage passenger bookings.</p>
                </div><a class="button" href="dashboard.php">Home</a>
            </div>
            <section class="card">
                <?php if ($message): ?><p class="message"><?= htmlspecialchars($message) ?></p><?php endif; ?>
                <form class="search" method="get"><input name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search passenger, bus, route or booking ID"><button class="button">Search</button></form>
                <?php if ($bookings->num_rows): ?><div class="table">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Passenger</th>
                                    <th>Bus</th>
                                    <th>Route</th>
                                    <th>Travel Date</th>
                                    <th>Seat</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($booking = $bookings->fetch_assoc()): ?><tr>
                                        <td>#<?= (int)$booking['booking_id'] ?></td>
                                        <td><strong><?= htmlspecialchars($booking['name'] ?? 'Unknown') ?></strong><br><small><?= htmlspecialchars($booking['email'] ?? '') ?></small></td>
                                        <td><?= htmlspecialchars($booking['bus_number'] . ' - ' . $booking['bus_name']) ?></td>
                                        <td><?= htmlspecialchars($booking['route']) ?></td>
                                        <td><?= htmlspecialchars(date('d M Y', strtotime($booking['travel_date']))) ?></td>
                                        <td><?= htmlspecialchars($booking['seat_number']) ?></td>
                                        <td>Rs. <?= htmlspecialchars(number_format((float)$booking['amount'], 2)) ?></td>
                                        <td><span class="status <?= htmlspecialchars($booking['status']) ?>"><?= htmlspecialchars(ucfirst($booking['status'])) ?></span></td>
                                        <td>
                                            <div class="actions">
                                                <form method="post"><input type="hidden" name="booking_id" value="<?= (int)$booking['booking_id'] ?>"><input type="hidden" name="action" value="status"><select name="status"><?php foreach ($statusOptions as $option): ?><option value="<?= htmlspecialchars($option) ?>" <?= $booking['status'] === $option ? ' selected' : '' ?>><?= htmlspecialchars(ucfirst($option)) ?></option><?php endforeach; ?></select><button class="button">Save</button></form>
                                                <form method="post" onsubmit="return confirm('Delete this booking?')"><input type="hidden" name="booking_id" value="<?= (int)$booking['booking_id'] ?>"><input type="hidden" name="action" value="delete"><button class="button delete">Delete</button></form>
                                            </div>
                                        </td>
                                    </tr><?php endwhile; ?>
                            </tbody>
                        </table>
                    </div><?php else: ?><p class="empty">No bookings found.</p><?php endif; ?>
            </section>
        </main>
    </div>
</body>

</html>