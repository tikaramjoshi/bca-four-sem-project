<?php
require_once __DIR__ . '/../db.php';
$scheduleTable = 'schedule';
$busTable = 'bus';
$bookingTable = 'bookings';
$scheduleIdColumn = 'schedule_id';
$busIdColumn = 'bus_id';
$seatColumn = 'seat_number';
$bookingStatusColumn = 'status';
$activeBookingStatuses = "'pending','confirmed','paid'";
if (!isset($conn)) {
    $conn = isset($connection) ? $connection : (isset($mysqli) ? $mysqli : null);
}
if (!$conn instanceof mysqli) {
    exit('Database connection is not available.');
}
$scheduleId = filter_input(INPUT_GET, 'schedule_id', FILTER_VALIDATE_INT);
$busId = filter_input(INPUT_GET, 'bus_id', FILTER_VALIDATE_INT);
if (!$scheduleId || !$busId) {
    exit('Invalid schedule or bus.');
}
$scheduleSql = "SELECT s.*, b.* FROM `$scheduleTable` s JOIN `$busTable` b ON s.`$busIdColumn` = b.`$busIdColumn` WHERE s.`$scheduleIdColumn` = ? AND s.`$busIdColumn` = ? LIMIT 1";
$scheduleStmt = $conn->prepare($scheduleSql);
$scheduleStmt->bind_param('ii', $scheduleId, $busId);
$scheduleStmt->execute();
$trip = $scheduleStmt->get_result()->fetch_assoc();
if (!$trip) {
    exit('Schedule not found.');
}
$from = $trip['from_city'] ?? $trip['departure_city'] ?? $trip['source'] ?? '';
$to = $trip['to_city'] ?? $trip['arrival_city'] ?? $trip['destination'] ?? '';
$date = $trip['travel_date'] ?? $trip['departure_date'] ?? $trip['date'] ?? '';
$time = $trip['departure_time'] ?? $trip['time'] ?? '';
$time = $time ? date('h:i A', strtotime($time)) : '';
$price = $trip['ticket_price'] ?? $trip['price'] ?? $trip['fare'] ?? 0;
$busName = $trip['bus_name'] ?? $trip['name'] ?? ('Bus ' . $busId);
$route = $trip['route'] ?? trim($from . ' to ' . $to);
$busNumber = $trip['bus_number'] ?? '';
$bookedSql = "SELECT `$seatColumn` FROM `$bookingTable` WHERE `bus_number` = ? AND `route` = ? AND `travel_date` = ? AND (`$bookingStatusColumn` IS NULL OR `$bookingStatusColumn` != 'cancelled')";
$bookedStmt = $conn->prepare($bookedSql);
$bookedStmt->bind_param('sss', $busNumber, $route, $date);
$bookedStmt->execute();
$bookedSeats = array_flip(array_column($bookedStmt->get_result()->fetch_all(MYSQLI_ASSOC), $seatColumn));
function seatRows($busId)
{
    $rows = [];
    $seat = 1;
    if ($busId == 3) {
        for ($i = 0; $i < 10; $i++) {
            $rows[] = [$seat++, $seat++, null, $seat++, $seat++];
        }
        $rows[] = [$seat++, $seat++, null, $seat++, null];
        $rows[] = [$seat++, $seat++, $seat++, $seat++, $seat++];
        return $rows;
    }
    if ($busId == 2) {
        for ($i = 0; $i < 2; $i++) {
            $rows[] = [$seat++, null, $seat++, $seat++];
        }
    }
    $total = $busId == 2 ? 42 : 40;
    while ($seat <= $total) {
        $rows[] = [$seat++, $seat++, null, $seat++, $seat++];
    }
    return $rows;
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Select Seat</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f8;
            margin: 0;
            color: #263238;
        }

        .wrap {
            max-width: 850px;
            margin: 35px auto;
            padding: 0 16px;
        }

        .card {
            background: #fff;
            border-radius: 10px;
            padding: 24px;
        }

        .trip {
            display: flex;
            flex-wrap: wrap;
            gap: 18px;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 18px;
            margin-bottom: 24px;
        }

        .trip div {
            min-width: 120px;
        }

        .trip small {
            display: block;
            color: #6b7280;
            margin-bottom: 4px;
        }

        .layout {
            max-width: 390px;
            margin: auto;
            border: 2px solid #334155;
            border-radius: 22px;
            padding: 20px;
        }

        .driver {
            text-align: right;
            margin: 0 0 18px;
            color: #475569;
            font-size: 14px;
        }

        .row {
            display: grid;
            grid-template-columns: repeat(2, 1fr) 24px repeat(2, 1fr);
            gap: 9px;
            margin: 9px 0;
        }

        .seat {
            border: 0;
            border-radius: 5px;
            padding: 11px 2px;
            background: #118215;
            color: #ffffff;
            cursor: pointer;
            font-weight: bold;
        }

        .seat:hover {
            background: #1130f8;
            color: white;
        }

        .seat.booked {
            background: #ce4f3993;
            color: #d0d6deac;
            cursor: not-allowed;
        }

        .gap {
            grid-column: 3;
        }

        .actions {
            display: flex;
            justify-content: center;
            gap: 12px;
            margin-top: 22px;
        }

        .actions button {
            background: #0f766e;
            border: 0;
            color: white;
            padding: 11px 20px;
            border-radius: 6px;
            cursor: pointer;
        }

        .actions button:disabled {
            background: #246cd0;
            cursor: not-allowed
        }

        .notice {
            text-align: center;
            margin-top: 15px;
            color: #475569;
        }

        .legend {
            display: flex;
            justify-content: center;
            gap: 16px;
            font-size: 13px;
            margin: 16px 0;
        }

        .dot {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 3px;
            margin-right: 5px;
            vertical-align: -1px;
        }

        .available {
            background: #59ea76;
        }

        .unavailable {
            background: #e61414;
        }

        .row {
            display: grid;
            grid-template-columns: 1fr 1fr 22px 1fr 1fr;
            gap: 9px;
            margin: 9px 0;
        }

        .last-row {
            grid-template-columns: repeat(5, 1fr);
        }
    </style>
</head>

<body>
    <main class="wrap">
        <section class="card">
            <h2>Select your seat</h2>
            <div class="trip">
                <div><small>Bus</small><?= htmlspecialchars($busName) ?></div>
                <div><small>Route</small><?= htmlspecialchars($route) ?></div>
                <div><small>Departure</small><?= htmlspecialchars(trim($date . ' ' . $time)) ?></div>
                <div><small>Price per seat</small>Rs. <?= htmlspecialchars((string)$price) ?></div>
            </div>
            <form method="post" action="booking.php" id="seatForm">
                <input type="hidden" name="schedule_id" value="<?= $scheduleId ?>">
                <input type="hidden" name="bus_id" value="<?= $busId ?>">
                <div id="selectedSeats"></div>
                <div class="layout">
                    <p class="driver">Driver</p>
                    <?php foreach (seatRows($busId) as $row): ?>
                        <?php $lastRow = count($row) == 5 && !in_array(null, $row); ?>
                        <div class="row<?= $lastRow ? ' last-row' : '' ?>">
                            <?php foreach ($row as $seat): ?>
                                <?php if ($seat === null): ?>
                                    <span class="gap"></span>
                                <?php else: ?>
                                    <button type="button" class="seat<?= isset($bookedSeats[$seat]) ? ' booked' : '' ?>" data-seat="<?= $seat ?>" <?= isset($bookedSeats[$seat]) ? 'disabled' : '' ?>><?= $seat ?></button>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="legend"><span><i class="dot available"></i>Available</span><span><i class="dot unavailable"></i>Booked</span></div>
                <div class="actions"><button type="submit" id="continueButton" disabled>Continue</button></div>
                <p class="notice" id="selectedText">Choose min 1 and max 4 available seats.</p>
            </form>
        </section>
    </main>
    <script>
        const buttons = document.querySelectorAll('.seat:not(.booked)'),
            selectedSeats = document.getElementById('selectedSeats'),
            continueButton = document.getElementById('continueButton'),
            selectedText = document.getElementById('selectedText'),
            chosen = [];
        buttons.forEach(button => button.addEventListener('click', () => {
            const seat = button.dataset.seat,
                index = chosen.indexOf(seat);
            if (index > -1) {
                chosen.splice(index, 1);
                button.style.background = '';
                button.style.outline = ''
            } else {
                if (chosen.length === 4) {
                    alert('You can select a maximum of 4 seats.');
                    return
                }
                chosen.push(seat);
                button.style.background = '#2312df';
                button.style.outline = '2px solid #0f766e'
            }
            selectedSeats.innerHTML = chosen.map(seat => '<input type="hidden" name="seat_numbers[]" value="' + seat + '">').join('');
            continueButton.disabled = chosen.length === 0;
            selectedText.textContent = chosen.length ? 'Selected seats: ' + chosen.join(', ') + ' (' + chosen.length + '/4)' : 'Choose up to 4 available seats.';
        }));
    </script>
</body>

</html>