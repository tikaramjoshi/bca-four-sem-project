<?php
session_start();
require_once __DIR__ . '/../db.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}
if (!isset($conn) || !$conn instanceof mysqli) exit('Database connection is not available.');
$userId = (int)$_SESSION['user_id'];
$stmt = $conn->prepare('SELECT booking_id, bus_name, bus_number, route, travel_date, seat_number, amount, status, created_at FROM bookings WHERE user_id = ? ORDER BY travel_date DESC, booking_id DESC');
$stmt->bind_param('i', $userId);
$stmt->execute();
$bookings = $stmt->get_result();
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Bookings</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f8;
            margin: 0;
            color: #263238;
        }

        .wrap {
            max-width: 1050px;
            margin: 40px auto;
            padding: 0 16px;
        }

        .card {
            background: #fff;
            border-radius: 10px;
            padding: 24px;
        }

        .top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .top h2 {
            margin: 0
        }

        .button {
            background: #0f766e;
            color: #fff;
            padding: 10px 15px;
            border-radius: 6px;
            text-decoration: none;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 760px;
        }

        th,
        td {
            text-align: left;
            padding: 13px;
            border-bottom: 1px solid #e5e7eb;
        }

        th {
            background: #f8fafc;
            color: #475569;
        }

        .status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
        }

        .pending {
            background: #fef3c7;
            color: #92400e;
        }

        .confirmed,
        .paid {
            background: #dcfce7;
            color: #166534;
        }

        .cancelled {
            background: #fee2e2;
            color: #991b1b;
        }

        .empty {
            text-align: center;
            padding: 40px;
            color: #64748b;
        }
    </style>
</head>

<body>
    <main class="wrap">
        <section class="card">
            <div class="top">
                <h2>My Bookings</h2><a class="button" href="dashboard.php">Book a Bus</a>
            </div>
            <?php if ($bookings->num_rows): ?><div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Booking ID</th>
                                <th>Bus</th>
                                <th>Route</th>
                                <th>Travel Date</th>
                                <th>Seat</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($booking = $bookings->fetch_assoc()): ?><tr>
                                    <td>#<?= (int)$booking['booking_id'] ?></td>
                                    <td><?= htmlspecialchars($booking['bus_name'] . ' (' . $booking['bus_number'] . ')') ?></td>
                                    <td><?= htmlspecialchars($booking['route']) ?></td>
                                    <td><?= htmlspecialchars(date('d M Y', strtotime($booking['travel_date']))) ?></td>
                                    <td><?= htmlspecialchars($booking['seat_number']) ?></td>
                                    <td>Rs. <?= htmlspecialchars(number_format((float)$booking['amount'], 2)) ?></td>
                                    <td><span class="status <?= htmlspecialchars(strtolower($booking['status'])) ?>"><?= htmlspecialchars(ucfirst($booking['status'])) ?></span></td>
                                </tr><?php endwhile; ?>
                        </tbody>
                    </table>
                </div><?php else: ?><p class="empty">You have not made any bookings yet.</p><?php endif; ?>
        </section>
    </main>
</body>

</html>