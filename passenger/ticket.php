<?php
session_start();
require_once "../db.php";

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'passenger') {
    header("Location: ../login.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$group_id = trim($_GET['group_id'] ?? '');

if ($group_id === '') exit("Invalid booking group.");

$stmt = $conn->prepare("SELECT booking_id,booking_group_id,bus_name,bus_number,route,travel_date,seat_number,amount,status,created_at FROM bookings WHERE booking_group_id=? AND user_id=? ORDER BY CAST(seat_number AS UNSIGNED)");
$stmt->bind_param("si", $group_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

$bookings = [];
while ($row = $result->fetch_assoc()) $bookings[] = $row;
$stmt->close();

if (!$bookings) exit("Ticket not found.");

$first = $bookings[0];
$confirmed = $pending = $cancelled = [];
$confirmed_amount = $pending_amount = $cancelled_amount = 0;

foreach ($bookings as $b) {
    $seat = $b['seat_number'];
    $amount = (float)$b['amount'];
    $status = strtolower($b['status']);

    if ($status === 'confirmed' || $status === 'paid') {
        $confirmed[] = $seat;
        $confirmed_amount += $amount;
    } elseif ($status === 'pending') {
        $pending[] = $seat;
        $pending_amount += $amount;
    } elseif ($status === 'cancelled') {
        $cancelled[] = $seat;
        $cancelled_amount += $amount;
    }
}

$total = count($bookings);
$active_amount = $confirmed_amount + $pending_amount;

if (count($cancelled) === $total) {
    $group_status = 'cancelled';
} elseif (count($pending) > 0) {
    $group_status = 'waiting';
} else {
    $group_status = 'complete';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Ticket <?= htmlspecialchars($group_id) ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box
        }

        body {
            font-family: Arial;
            background: #eef4fb;
            color: #263238
        }

        .wrap {
            max-width: 850px;
            margin: 40px auto;
            padding: 15px
        }

        .ticket {
            background: #fff;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 4px 18px #0002
        }

        .head {
            background: #1560bd;
            color: #fff;
            padding: 25px
        }

        .head-top {
            display: flex;
            justify-content: space-between;
            gap: 15px
        }

        .head h1 {
            margin-bottom: 8px
        }

        .head p {
            margin: 5px 0
        }

        .group-status {
            background: #fff;
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: bold;
            height: max-content
        }

        .complete {
            color: #218838
        }

        .waiting {
            color: #856404
        }

        .cancelled {
            color: #c82333
        }

        .content {
            padding: 25px
        }

        .title {
            color: #1560bd;
            margin-bottom: 15px
        }

        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
            padding-bottom: 22px;
            border-bottom: 1px dashed #94a3b8
        }

        .item small {
            display: block;
            color: #64748b;
            margin-bottom: 5px
        }

        .item strong {
            font-size: 16px
        }

        .seats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-top: 25px
        }

        .box {
            padding: 16px;
            border-radius: 10px;
            border: 1px solid #ddd
        }

        .box h3 {
            margin-bottom: 10px
        }

        .numbers {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 8px
        }

        .confirmed-box {
            background: #f0fff4;
            border-color: #b7e4c7
        }

        .confirmed-box h3,
        .confirmed-box .numbers {
            color: #218838
        }

        .pending-box {
            background: #fffaf0;
            border-color: #f5d78e
        }

        .pending-box h3,
        .pending-box .numbers {
            color: #d68910
        }

        .cancelled-box {
            background: #fff5f5;
            border-color: #f1b0b7
        }

        .cancelled-box h3,
        .cancelled-box .numbers {
            color: #dc3545
        }

        .none {
            color: #777
        }

        .summary {
            margin-top: 25px;
            background: #f8fafc;
            padding: 18px;
            border-radius: 10px
        }

        .summary h3 {
            color: #1560bd;
            margin-bottom: 10px
        }

        .row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #e5e7eb
        }

        .green {
            color: #218838
        }

        .orange {
            color: #d68910
        }

        .red {
            color: #dc3545
        }

        .actions {
            display: flex;
            gap: 10px;
            margin-top: 25px
        }

        .btn {
            background: #1560bd;
            color: #fff;
            padding: 11px 18px;
            border: 0;
            border-radius: 6px;
            text-decoration: none;
            cursor: pointer
        }

        .secondary {
            background: #64748b
        }

        @media(max-width:700px) {

            .head-top,
            .grid,
            .seats {
                grid-template-columns: 1fr;
                display: grid
            }

            .head-top {
                gap: 15px
            }
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
                <div class="head-top">
                    <div>
                        <h1>Online Bus Ticket</h1>
                        <p>Booking Group: <strong><?= htmlspecialchars($group_id) ?></strong></p>
                        <p>Booking Date: <?= date('d M Y h:i A', strtotime($first['created_at'])) ?></p>
                    </div>
                    <div class="group-status <?= $group_status ?>"><?= ucfirst($group_status) ?></div>
                </div>
            </header>

            <div class="content">
                <h2 class="title">Journey Details</h2>

                <div class="grid">
                    <div class="item"><small>Bus Name</small><strong><?= htmlspecialchars($first['bus_name']) ?></strong></div>
                    <div class="item"><small>Bus Number</small><strong><?= htmlspecialchars($first['bus_number']) ?></strong></div>
                    <div class="item"><small>Route</small><strong><?= htmlspecialchars($first['route']) ?></strong></div>
                    <div class="item"><small>Travel Date</small><strong><?= date('d M Y', strtotime($first['travel_date'])) ?></strong></div>
                </div>

                <h2 class="title" style="margin-top:25px">Seat Status</h2>

                <div class="seats">
                    <div class="box confirmed-box">
                        <h3>Confirmed Seats</h3>
                        <?php if ($confirmed): ?>
                            <div class="numbers"><?= htmlspecialchars(implode(', ', $confirmed)) ?></div>
                            Amount: Rs. <?= number_format($confirmed_amount, 2) ?>
                        <?php else: ?>
                            <div class="none">No confirmed seats</div>
                        <?php endif; ?>
                    </div>

                    <div class="box pending-box">
                        <h3>Pending Seats</h3>
                        <?php if ($pending): ?>
                            <div class="numbers"><?= htmlspecialchars(implode(', ', $pending)) ?></div>
                            Amount: Rs. <?= number_format($pending_amount, 2) ?>
                        <?php else: ?>
                            <div class="none">No pending seats</div>
                        <?php endif; ?>
                    </div>

                    <div class="box cancelled-box">
                        <h3>Cancelled Seats</h3>
                        <?php if ($cancelled): ?>
                            <div class="numbers"><?= htmlspecialchars(implode(', ', $cancelled)) ?></div>
                            Amount: Rs. <?= number_format($cancelled_amount, 2) ?>
                        <?php else: ?>
                            <div class="none">No cancelled seats</div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="summary">
                    <h3>Booking Summary</h3>
                    <div class="row"><span>Total Selected Seats</span><strong><?= $total ?></strong></div>
                    <div class="row"><span>Confirmed Seats</span><strong class="green"><?= count($confirmed) ?></strong></div>
                    <div class="row"><span>Waiting Seats</span><strong class="orange"><?= count($pending) ?></strong></div>
                    <div class="row"><span>Cancelled Seats</span><strong class="red"><?= count($cancelled) ?></strong></div>
                    <div class="row"><span>Active Booking Amount</span><strong>Rs. <?= number_format($active_amount, 2) ?></strong></div>
                </div>

                <div class="actions">
                    <button class="btn" onclick="window.print()">Print Ticket</button>
                    <a class="btn secondary" href="booking_history.php">My Bookings</a>
                </div>
            </div>
        </section>
    </main>
</body>

</html>