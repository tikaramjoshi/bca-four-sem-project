<?php
session_start();
require_once __DIR__ . '/../db.php';
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit;
}
if (!isset($conn) || !$conn instanceof mysqli) exit('Database connection is not available.');
$search = trim($_GET['search'] ?? '');
$records_per_page = 10;
$current_page = max(1, (int)($_GET['page'] ?? 1));
$count_sql = "SELECT COUNT(*) AS total FROM booking_history bh LEFT JOIN users u ON bh.user_id=u.user_id";
$count_params = [];
$count_types = '';
if ($search !== '') {
    $count_sql .= " WHERE CAST(bh.booking_id AS CHAR) LIKE ? OR bh.booking_group_id LIKE ? OR u.name LIKE ? OR u.email LIKE ? OR bh.bus_name LIKE ? OR bh.bus_number LIKE ? OR bh.route LIKE ? OR bh.seat_number LIKE ?";
    $value = '%' . $search . '%';
    $count_params = [$value, $value, $value, $value, $value, $value, $value, $value];
    $count_types = 'ssssssss';
}
$count_stmt = $conn->prepare($count_sql);
if (!$count_stmt) exit('Unable to prepare count query.');
if ($count_params) $count_stmt->bind_param($count_types, ...$count_params);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_records = (int)($count_result->fetch_assoc()['total'] ?? 0);
$count_stmt->close();
$total_pages = max(1, (int)ceil($total_records / $records_per_page));
if ($current_page > $total_pages) $current_page = $total_pages;
$offset = ($current_page - 1) * $records_per_page;
$sql = "SELECT bh.history_id,bh.booking_id,bh.booking_group_id,bh.user_id,bh.schedule_id,bh.bus_name,bh.bus_number,bh.route,bh.travel_date,bh.seat_number,bh.amount,bh.status,bh.created_at,bh.deleted_at,u.name,u.email,u.phone FROM booking_history bh LEFT JOIN users u ON bh.user_id=u.user_id";
$params = [];
$types = '';
if ($search !== '') {
    $sql .= " WHERE CAST(bh.booking_id AS CHAR) LIKE ? OR bh.booking_group_id LIKE ? OR u.name LIKE ? OR u.email LIKE ? OR bh.bus_name LIKE ? OR bh.bus_number LIKE ? OR bh.route LIKE ? OR bh.seat_number LIKE ?";
    $value = '%' . $search . '%';
    $params = [$value, $value, $value, $value, $value, $value, $value, $value];
    $types = 'ssssssss';
}
$sql .= " ORDER BY bh.history_id DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
if (!$stmt) exit('Unable to prepare booking history query.');
if ($params) {
    $types .= 'ii';
    $params[] = $records_per_page;
    $params[] = $offset;
    $stmt->bind_param($types, ...$params);
} else {
    $stmt->bind_param('ii', $records_per_page, $offset);
}
$stmt->execute();
$history = $stmt->get_result();
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Booking History</title>
    <link rel="stylesheet" href="side.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="booking.css">
    <style>
        .back {
            background: #64748b
        }

        .back:hover {
            background: #475569
        }

        .deleted {
            background: #e5e7eb;
            color: #374151;
            padding: 5px 9px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold
        }
    </style>
</head>

<body>
    <?php include "admin_header.php"; ?>
    <div class="content">
        <main class="wrap">
            <div class="top">
                <div>
                    <h1>Booking History</h1>
                    <p>Deleted bookings are stored here.</p>
                </div>
                <a href="bookings.php" class="button back"> <i class="fa fa-arrow-left"></i> Back </a>
            </div>
            <section class="card">
                <form class="search" method="get">
                    <input name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search passenger, bus, route, seat or booking ID">
                    <button type="submit" class="button"><i class="fa fa-search"></i> Search</button>
                    <?php if ($search !== ''): ?>
                        <a href="booking_history.php" class="button back">Clear</a>
                    <?php endif; ?>
                </form>
                <?php if ($history->num_rows): ?>
                    <div class="table">
                        <table>
                            <thead>
                                <tr>
                                    <th>History ID</th>
                                    <th>Booking ID</th>
                                    <th>Group ID</th>
                                    <th>Passenger</th>
                                    <th>Bus</th>
                                    <th>Route</th>
                                    <th>Travel Date</th>
                                    <th>Seat</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Deleted At</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($booking = $history->fetch_assoc()): ?>
                                    <?php $status = strtolower($booking['status'] ?? 'pending'); ?>
                                    <tr>
                                        <td>#<?= (int)$booking['history_id'] ?></td>
                                        <td>#<?= (int)$booking['booking_id'] ?></td>
                                        <td><?= htmlspecialchars($booking['booking_group_id'] ?? 'N/A') ?></td>
                                        <td><strong><?= htmlspecialchars($booking['name'] ?? 'Unknown') ?></strong><br><small><?= htmlspecialchars($booking['email'] ?? '') ?></small></td>
                                        <td><?= htmlspecialchars(($booking['bus_number'] ?? '') . ' - ' . ($booking['bus_name'] ?? '')) ?></td>
                                        <td><?= htmlspecialchars($booking['route'] ?? 'N/A') ?></td>
                                        <td><?= !empty($booking['travel_date']) ? htmlspecialchars(date('d M Y', strtotime($booking['travel_date']))) : 'N/A' ?></td>
                                        <td><span class="seat"><?= htmlspecialchars($booking['seat_number'] ?? 'N/A') ?></span></td>
                                        <td>Rs. <?= number_format((float)$booking['amount'], 2) ?></td>
                                        <td><span class="status <?= htmlspecialchars($status) ?>"><?= htmlspecialchars(ucfirst($status)) ?></span></td>
                                        <td><span class="deleted"><?= !empty($booking['deleted_at']) ? htmlspecialchars(date('d M Y h:i A', strtotime($booking['deleted_at']))) : 'N/A' ?></span></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php include "pagination.php"; ?>
                <?php else: ?>
                    <div class="empty">
                        <i class="fa fa-history" style="font-size:40px;margin-bottom:15px"></i>
                        <h3>No booking history found.</h3>
                        <p>Deleted bookings will appear here.</p>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>
</body>

</html>