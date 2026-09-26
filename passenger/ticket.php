<?php
include "pass_header.php";
$group_id = trim($_GET['group_id'] ?? '');
if ($group_id === '') exit("Invalid booking group.");
$user_id = (int)$_SESSION['user_id'];
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
    <link rel="stylesheet" href="ticket.css">
    </style>
    <link rel="stylesheet" href="dashboard.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
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
                <div class="summary qr-top">
                    <h3>Ticket QR Code</h3>
                    <p style="margin-top:12px;color:#64748b;">Show this QR code to the driver for verification.</p>
                    <div style="text-align:right;">
                        <div id="ticketQR" style="display:inline-block;padding:15px;background:#fff;border:1px solid #ddd;border-radius:10px;margin-top:-102px;"></div>
                    </div>
                </div>
                <hr><br>
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
                    <button class="btn" onclick="window.print()"><i class="fa fa-print"></i> Print Ticket</button>
                    <button class="btn download" onclick="downloadTicket()"><i class="fa fa-download"></i> Download Ticket</button>
                    <a class="btn secondary" href="booking_history.php"><i class="fa fa-ticket"></i> My Bookings</a>
                </div>
            </div>
        </section>
    </main>
    <?php include "pass_footer.php"; ?>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const groupId = <?= json_encode($group_id) ?>;
            if (groupId) {
                new QRCode(document.getElementById("ticketQR"), {
                    text: groupId,
                    width: 120,
                    height: 120,
                    correctLevel: QRCode.CorrectLevel.H
                });
            }
        });

        function downloadTicket() {
            const qr = document.querySelector("#ticketQR canvas");
            if (qr) {
                const img = document.createElement("img");
                img.src = qr.toDataURL("image/png");
                img.style.width = "120px";
                img.style.height = "120px";
                document.querySelector("#ticketQR").innerHTML = "";
                document.querySelector("#ticketQR").appendChild(img);
            }
            html2canvas(document.querySelector(".ticket"), {
                scale: 2,
                useCORS: true
            }).then(canvas => {
                const link = document.createElement("a");
                link.download = "Ticket-<?= htmlspecialchars($group_id) ?>.png";
                link.href = canvas.toDataURL("image/png");
                link.click();
            });
        }
    </script>
</body>

</html>