<?php
session_start();
require_once __DIR__ . '/../db.php';
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'passenger') {
    header("Location: ../login.php");
    exit;
}
$scheduleId = filter_input(INPUT_GET, 'schedule_id', FILTER_VALIDATE_INT);
$busId = filter_input(INPUT_GET, 'bus_id', FILTER_VALIDATE_INT);
if (!$scheduleId || !$busId) {
    exit('Invalid schedule or bus.');
}
$stmt = $conn->prepare("
    SELECT 
        s.schedule_id,
        s.bus_id,
        s.from_city,
        s.to_city,
        s.departure_date,
        s.departure_time,
        s.ticket_price,
        s.available_seats,
        s.status,
        b.bus_number,
        b.bus_name,
        b.bus_type,
        b.seats
    FROM schedules s
    INNER JOIN bus b ON s.bus_id = b.bus_id
    WHERE s.schedule_id = ?
    AND s.bus_id = ?  
    AND s.status = 'active'
    AND b.status = 'approved'
  
    LIMIT 1
");
$stmt->bind_param("ii", $scheduleId, $busId);
$stmt->execute();
$trip = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$trip) {
    exit('Schedule not found.');
}
$from = $trip['from_city'];
$to = $trip['to_city'];
$date = $trip['departure_date'];
$time = date("h:i A", strtotime($trip['departure_time']));
$price = $trip['ticket_price'];
$busName = $trip['bus_name'];
$busNumber = $trip['bus_number'];
$totalSeats = (int)$trip['seats'];
$bookedSeats = [];

$stmt = $conn->prepare("
    SELECT seat_number
    FROM bookings
    WHERE schedule_id = ?
    AND status IN ('pending','confirmed','paid')
");
$stmt->bind_param("i", $scheduleId);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $bookedSeats[] = (string)$row['seat_number'];
}
$stmt->close();

$bookedCount = count($bookedSeats);
$availableSeats = max(0, $totalSeats - $bookedCount);

function generateSeats($totalSeats)
{
    $rows = [];
    $seat = 1;
    while ($seat <= $totalSeats - 5) {
        $row = [];
        if ($seat <= $totalSeats - 5) {
            $row[] = $seat++;
        }
        if ($seat <= $totalSeats - 5) {
            $row[] = $seat++;
        }
        $row[] = null;
        if ($seat <= $totalSeats - 5) {
            $row[] = $seat++;
        }
        if ($seat <= $totalSeats - 5) {
            $row[] = $seat++;
        }
        $rows[] = $row;
    }
    $remaining = $totalSeats - $seat + 1;
    if ($remaining > 0) {
        $lastRow = [];
        while ($seat <= $totalSeats) {
            $lastRow[] = $seat++;
        }
        while (count($lastRow) < 5) {
            $lastRow[] = null;
        }
        $rows[] = $lastRow;
    }
    return $rows;
}
$seatRows = generateSeats($totalSeats);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Select Seat</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f4f6f8;
            color: #263238;
        }

        .wrap {
            max-width: 850px;
            margin: 35px auto;
            padding: 0 16px;
        }

        .card {
            background: #ffffff;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 3px 15px #ddd;
        }

        h2 {
            margin-bottom: 20px;
            color: #1560bd;
        }

        .trip {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            padding-bottom: 20px;
            margin-bottom: 25px;
            border-bottom: 1px solid #ddd;
        }

        .trip div {
            background: #f5f7fa;
            padding: 12px;
            border-radius: 7px;
        }

        .trip small {
            display: block;
            color: #777;
            font-size: 12px;
            margin-bottom: 5px;
        }

        .layout {
            max-width: 400px;
            margin: auto;
            border: 2px solid #334155;
            border-radius: 22px;
            padding: 20px;
            background: #fafafa;
        }

        .driver {
            text-align: right;
            color: #555;
            font-size: 14px;
            margin-bottom: 18px;
        }

        .row {
            display: grid;
            grid-template-columns: 1fr 1fr 25px 1fr 1fr;
            gap: 9px;
            margin: 9px 0;
        }

        .last-row {
            grid-template-columns: repeat(5, 1fr);
        }

        .gap {
            width: 100%;
        }

        .seat {
            border: 0;
            border-radius: 5px;
            padding: 11px 3px;
            background: #198754;
            color: #fff;
            cursor: pointer;
            font-weight: bold;
        }

        .seat:hover {
            background: #1560bd;
        }

        .seat.selected {
            background: #2312df;
            outline: 2px solid #0f766e;
        }

        .seat.booked {
            background: #dc3545;
            color: #fff;
            cursor: not-allowed;
        }

        .legend {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin: 20px 0;
            font-size: 13px;
        }

        .dot {
            display: inline-block;
            width: 13px;
            height: 13px;
            border-radius: 3px;
            margin-right: 5px;
            vertical-align: -2px;
        }

        .available {
            background: #198754;
        }

        .unavailable {
            background: #dc3545;
        }

        .selected-dot {
            background: #2312df;
        }

        .actions {
            text-align: center;
            margin-top: 20px;
        }

        .actions button {
            border: 0;
            background: #1560bd;
            color: #fff;
            padding: 12px 30px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 15px;
        }

        .actions button:disabled {
            background: #aaa;
            cursor: not-allowed;
        }

        .notice {
            text-align: center;
            color: #666;
            margin-top: 15px;
            font-size: 14px;
        }

        .back {
            display: inline-block;
            height: 30px;
            text-align: center;
            border-radius: 10px;
            padding: 5px;
            text-decoration: none;
            color: #fff;
            background-color: #0d36ed;
        }

        @media(max-width:500px) {
            .card {
                padding: 15px;
            }

            .trip {
                grid-template-columns: 1fr;
            }

            .layout {
                padding: 12px;
            }

            .row {
                gap: 5px;
                grid-template-columns: 1fr 1fr 18px 1fr 1fr;
            }

            .seat {
                padding: 9px 2px;
                font-size: 12px;
            }
        }
    </style>
</head>

<body>
    <main class="wrap">
        <section class="card">
            <a href="search_bus.php?from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>&date=<?= urlencode($date) ?>" class="back">Back to Bus List</a>
            <h2>Select Your Seat</h2>
            <div class="trip">
                <div>
                    <small>Bus</small>
                    <?= htmlspecialchars($busName) ?>
                </div>
                <div>
                    <small>Bus Number</small>
                    <?= htmlspecialchars($busNumber) ?>
                </div>
                <div>
                    <small>Route</small>
                    <?= htmlspecialchars($from) ?> <strong> - </strong> <?= htmlspecialchars($to) ?>
                </div>
                <div>
                    <small>Departure</small>
                    <?= htmlspecialchars($date) ?> <?= htmlspecialchars($time) ?>
                </div>
                <div>
                    <small>Price Per Seat</small>
                    Rs. <?= number_format((float)$price, 2) ?>
                </div>
                <div>
                    <small>Available Seats</small>
                    <strong id="availableSeats"><?= $availableSeats ?></strong>
                </div>
                <div>
                    <small>Booked Seats</small>
                    <strong id="bookedSeats"><?= $bookedCount ?></strong>
                </div>
            </div>
            <form method="post" action="booking.php" id="seatForm">
                <input type="hidden" name="schedule_id" value="<?= $scheduleId ?>">
                <input type="hidden" name="bus_id" value="<?= $busId ?>">
                <input type="hidden" name="from_city" value="<?= htmlspecialchars($from) ?>">
                <input type="hidden" name="to_city" value="<?= htmlspecialchars($to) ?>">
                <input type="hidden" name="travel_date" value="<?= htmlspecialchars($date) ?>">
                <input type="hidden" name="ticket_price" value="<?= htmlspecialchars($price) ?>">
                <div id="selectedSeats"></div>
                <div class="layout">
                    <div class="driver"> Driver</div>
                    <?php foreach ($seatRows as $row): ?>
                        <?php $lastRow = count($row) === 5 && !in_array(null, $row, true); ?>
                        <div class="row <?= $lastRow ? 'last-row' : '' ?>">
                            <?php foreach ($row as $seat): ?>
                                <?php if ($seat === null): ?>
                                    <span class="gap"></span>
                                <?php else: ?>
                                    <?php $isBooked = in_array((string)$seat, $bookedSeats, true); ?>
                                    <button
                                        type="button"
                                        class="seat <?= $isBooked ? 'booked' : '' ?>"
                                        data-seat="<?= $seat ?>"
                                        <?= $isBooked ? 'disabled' : '' ?>>
                                        <?= $seat ?>
                                    </button>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="legend">
                    <span><i class="dot available"></i>Available</span>
                    <span><i class="dot selected-dot"></i>Selected</span>
                    <span><i class="dot unavailable"></i>Booked</span>
                </div>
                <div class="actions">
                    <button type="submit" id="continueButton" disabled>
                        Continue
                    </button>
                </div>
                <p class="notice" id="selectedText">
                    Choose minimum 1 and maximum 4 seats.
                </p>
            </form>
        </section>
    </main>
    <script>
        const availableSeatsElement = document.getElementById("availableSeats");
        const initialAvailableSeats = <?= $availableSeats ?>;
        const buttons = document.querySelectorAll(".seat:not(.booked)");
        const selectedSeats = document.getElementById("selectedSeats");
        const continueButton = document.getElementById("continueButton");
        const selectedText = document.getElementById("selectedText");
        const chosen = [];
        buttons.forEach(button => {
            button.addEventListener("click", () => {
                const seat = button.dataset.seat;
                const index = chosen.indexOf(seat);
                if (index !== -1) {
                    chosen.splice(index, 1);
                    button.classList.remove("selected");
                } else {
                    if (chosen.length >= 4) {
                        alert("You can select maximum 4 seats.");
                        return;
                    }
                    chosen.push(seat);
                    button.classList.add("selected");
                }
                selectedSeats.innerHTML = chosen.map(seat => {
                    return '<input type="hidden" name="seat_numbers[]" value="' + seat + '">';
                }).join("");
                continueButton.disabled = chosen.length === 0;
                if (chosen.length) {
                    selectedText.textContent = "Selected seats: " + chosen.join(", ") + " (" + chosen.length + "/4)";
                } else {
                    selectedText.textContent = "Choose minimum 1 and maximum 4 seats.";
                }
                availableSeatsElement.textContent = initialAvailableSeats - chosen.length;
            });
        });
    </script>
</body>

</html>