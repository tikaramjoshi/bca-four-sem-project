<?php
session_start();
require_once __DIR__ . '/../db.php';
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit;
}
if (!isset($conn) || !$conn instanceof mysqli) exit('Database connection is not available.');
$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bookingId = (int)($_POST['booking_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($bookingId > 0 && $action === 'status') {
        $status = strtolower(trim($_POST['status'] ?? ''));
        if (in_array($status, ['confirmed', 'cancelled'], true)) {
            $stmt = $conn->prepare("UPDATE bookings SET status=? WHERE booking_id=?");
            if ($stmt) {
                $stmt->bind_param('si', $status, $bookingId);
                if ($stmt->execute()) {
                    $message = $status === 'confirmed' ? 'Seat approved successfully.' : 'Seat rejected successfully.';
                } else {
                    $error = 'Unable to update booking status.';
                }
                $stmt->close();
            } else {
                $error = 'Unable to prepare status update.';
            }
        } else {
            $error = 'Invalid booking status.';
        }
    }
    if ($bookingId > 0 && $action === 'delete') {
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("SELECT booking_id,booking_group_id,user_id,schedule_id,bus_name,bus_number,route,travel_date,seat_number,amount,status,created_at FROM bookings WHERE booking_id=?");
            if (!$stmt) throw new Exception('Unable to find booking.');
            $stmt->bind_param('i', $bookingId);
            $stmt->execute();
            $result = $stmt->get_result();
            $booking = $result->fetch_assoc();
            $stmt->close();
            if (!$booking) throw new Exception('Booking not found.');
            $status = strtolower($booking['status'] ?? '');
            if (!in_array($status, ['confirmed', 'cancelled'], true)) throw new Exception('Booking must be approved or rejected before deletion.');
            $stmt = $conn->prepare("INSERT INTO booking_history (booking_id,booking_group_id,user_id,schedule_id,bus_name,bus_number,route,travel_date,seat_number,amount,status,created_at,deleted_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW())");
            if (!$stmt) throw new Exception('Unable to save booking history.');
            $stmt->bind_param('isissssssdss', $booking['booking_id'], $booking['booking_group_id'], $booking['user_id'], $booking['schedule_id'], $booking['bus_name'], $booking['bus_number'], $booking['route'], $booking['travel_date'], $booking['seat_number'], $booking['amount'], $booking['status'], $booking['created_at']);
            if (!$stmt->execute()) throw new Exception('Unable to save booking history.');
            $stmt->close();
            $stmt = $conn->prepare("DELETE FROM bookings WHERE booking_id=?");
            if (!$stmt) throw new Exception('Unable to delete booking.');
            $stmt->bind_param('i', $bookingId);
            if (!$stmt->execute()) throw new Exception('Unable to delete booking.');
            $stmt->close();
            $conn->commit();
            $message = 'Booking deleted and moved to booking history successfully.';
        } catch (Exception $e) {
            $conn->rollback();
            $error = $e->getMessage();
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
if (!$stmt) exit('Unable to prepare booking query.');
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$bookings = $stmt->get_result();
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Manage Bookings</title>
    <link rel="stylesheet" href="side.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="booking.css">
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
                <a href="booking_history.php" class="history-link"><i class="fa fa-history"></i> Booking History</a>
            </div>
            <section class="card">
                <?php if ($message): ?>
                    <div class="message"><?= htmlspecialchars($message) ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <form class="search" method="get">
                    <input name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search passenger, bus, route, seat or booking ID">
                    <button type="submit" class="button"><i class="fa fa-search"></i> Search</button>
                    <?php if ($search !== ''): ?>
                        <a href="bookings.php" class="button" style="background:#64748b">Clear</a>
                    <?php endif; ?>
                </form>
                <?php if ($bookings->num_rows): ?>
                    <div class="table">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Group ID</th>
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
                                <?php while ($booking = $bookings->fetch_assoc()): ?>
                                    <?php $status = strtolower($booking['status'] ?? 'pending'); ?>
                                    <tr>
                                        <td>#<?= (int)$booking['booking_id'] ?></td>
                                        <td><?= htmlspecialchars($booking['booking_group_id'] ?? 'N/A') ?></td>
                                        <td><strong><?= htmlspecialchars($booking['name'] ?? 'Unknown') ?></strong><br><small><?= htmlspecialchars($booking['email'] ?? '') ?></small></td>
                                        <td><?= htmlspecialchars(($booking['bus_number'] ?? '') . ' - ' . ($booking['bus_name'] ?? '')) ?></td>
                                        <td><?= htmlspecialchars($booking['route'] ?? 'N/A') ?></td>
                                        <td><?= !empty($booking['travel_date']) ? htmlspecialchars(date('d M Y', strtotime($booking['travel_date']))) : 'N/A' ?></td>
                                        <td><span class="seat"><?= htmlspecialchars($booking['seat_number'] ?? 'N/A') ?></span></td>
                                        <td>Rs. <?= number_format((float)$booking['amount'], 2) ?></td>
                                        <td><span class="status <?= htmlspecialchars($status) ?>"><?= htmlspecialchars(ucfirst($status)) ?></span></td>
                                        <td>
                                            <div class="actions">
                                                <?php if ($status === 'pending'): ?>
                                                    <form method="post">
                                                        <input type="hidden" name="booking_id" value="<?= (int)$booking['booking_id'] ?>">
                                                        <input type="hidden" name="action" value="status">
                                                        <input type="hidden" name="status" value="confirmed">
                                                        <button type="submit" class="action-button approve" onclick="return confirm('Approve seat <?= htmlspecialchars($booking['seat_number'], ENT_QUOTES) ?>?')"><i class="fa fa-check"></i> Approve</button>
                                                    </form>
                                                    <form method="post">
                                                        <input type="hidden" name="booking_id" value="<?= (int)$booking['booking_id'] ?>">
                                                        <input type="hidden" name="action" value="status">
                                                        <input type="hidden" name="status" value="cancelled">
                                                        <button type="submit" class="action-button reject" onclick="return confirm('Reject seat <?= htmlspecialchars($booking['seat_number'], ENT_QUOTES) ?>?')"><i class="fa fa-times"></i> Reject</button>
                                                    </form>
                                                <?php else: ?>
                                                    <form method="post" onsubmit="return confirm('Delete this booking and move it to booking history?')">
                                                        <input type="hidden" name="booking_id" value="<?= (int)$booking['booking_id'] ?>">
                                                        <input type="hidden" name="action" value="delete">
                                                        <button type="submit" class="action-button delete"><i class="fa fa-trash"></i> Delete</button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
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