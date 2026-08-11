<?php
session_start();
require_once __DIR__ . '/../db.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}
if (!isset($conn) || !$conn instanceof mysqli) exit('Database connection is not available.');
$bookingId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$userId = (int)$_SESSION['user_id'];
if ($bookingId <= 0) exit('Invalid ticket.');
$stmt = $conn->prepare('SELECT booking_id, bus_name, bus_number, route, travel_date, seat_number, amount, status, created_at FROM bookings WHERE booking_id = ? AND user_id = ? LIMIT 1');
$stmt->bind_param('ii', $bookingId, $userId);
$stmt->execute();
$ticket = $stmt->get_result()->fetch_assoc();
if (!$ticket) exit('Ticket not found.');
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bus Ticket #<?= (int)$ticket['booking_id'] ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f8;
            margin: 0;
            color: #263238
        }

        .wrap {
            max-width: 680px;
            margin: 42px auto;
            padding: 0 16px
        }

        .ticket {
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 3px 14px #00000018
        }

        .head {
            background: #0f766e;
            color: #fff;
            padding: 24px
        }

        .head h1 {
            font-size: 24px;
            margin: 0 0 7px
        }

        .head p {
            margin: 0;
            opacity: .9
        }

        .content {
            padding: 24px
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 18px;
            border-bottom: 1px dashed #94a3b8;
            padding-bottom: 22px
        }

        .item small {
            display: block;
            color: #64748b;
            margin-bottom: 5px
        }

        .item strong {
            font-size: 16px
        }

        .status {
            display: inline-block;
            background: #fef3c7;
            color: #92400e;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold
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
            gap: 10px;
            margin-top: 24px
        }

        .button {
            border: 0;
            background: #0f766e;
            color: #fff;
            padding: 10px 16px;
            border-radius: 6px;
            text-decoration: none;
            cursor: pointer
        }

        .secondary {
            background: #64748b
        }

        @media print {
            body {
                background: #fff
            }

            .wrap {
                margin: 0;
                max-width: none
            }

            .ticket {
                box-shadow: none
            }

            .actions {
                display: none
            }
        }
    </style>
</head>

<body>
    <main class="wrap">
        <section class="ticket">
            <header class="head">
                <h1>Online Bus Ticket</h1>
                <p>Booking ID: #<?= (int)$ticket['booking_id'] ?></p>
            </header>
            <div class="content">
                <div class="grid">
                    <div class="item"><small>Bus</small><strong><?= htmlspecialchars($ticket['bus_name']) ?></strong></div>
                    <div class="item"><small>Bus Number</small><strong><?= htmlspecialchars($ticket['bus_number']) ?></strong></div>
                    <div class="item"><small>Route</small><strong><?= htmlspecialchars($ticket['route']) ?></strong></div>
                    <div class="item"><small>Travel Date</small><strong><?= htmlspecialchars(date('d M Y', strtotime($ticket['travel_date']))) ?></strong></div>
                    <div class="item"><small>Seat Number</small><strong><?= htmlspecialchars($ticket['seat_number']) ?></strong></div>
                    <div class="item"><small>Amount</small><strong>Rs. <?= htmlspecialchars(number_format((float)$ticket['amount'], 2)) ?></strong></div>
                    <div class="item"><small>Status</small><span class="status <?= htmlspecialchars(strtolower($ticket['status'])) ?>"><?= htmlspecialchars(ucfirst($ticket['status'])) ?></span></div>
                    <div class="item"><small>Booked On</small><strong><?= htmlspecialchars(date('d M Y h:i A', strtotime($ticket['created_at']))) ?></strong></div>
                </div>
                <div class="actions"><button class="button" onclick="window.print()">Print Ticket</button><a class="button secondary" href="my_bookings.php">My Bookings</a></div>
            </div>
        </section>
    </main>
</body>

</html>