<?php
session_start();
require_once __DIR__ . '/../db.php';
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'passenger') {
    header("Location: ../login.php");
    exit;
}
$userId = (int)$_SESSION['user_id'];
$stmt = $conn->prepare("SELECT verification_status FROM users WHERE user_id=? AND role='passenger' LIMIT 1");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$verification_status = $user['verification_status'] ?? '';

$scheduleId = filter_input(INPUT_GET, 'schedule_id', FILTER_VALIDATE_INT);
$busId = filter_input(INPUT_GET, 'bus_id', FILTER_VALIDATE_INT);
if (!$scheduleId || !$busId) {
    exit('Invalid schedule or bus.');
}
$stmt = $conn->prepare(" SELECT  s.schedule_id, s.bus_id, s.from_city, s.to_city, s.departure_date, s.departure_time, s.ticket_price, s.available_seats, s.status, b.bus_number, b.bus_name, b.bus_type, b.seats FROM schedules s INNER JOIN bus b ON s.bus_id = b.bus_id WHERE s.schedule_id = ? AND s.bus_id = ?   AND s.status = 'active' AND b.status = 'approved' LIMIT 1 ");
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
    <link rel="stylesheet" href="seat_selection.css">
</head>

<body>
    <main class="wrap">
        <div id="verifyPopup" class="verify-popup">
            <div class="verify-box">
                <span id="closeVerify">&times;</span>
                <h3>Verification Required</h3>
                <p>Your account is not verified. Please wait for admin verification.</p>
                <button type="button" id="verifyOk">OK</button>
            </div>
        </div>
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
        const verificationStatus = "<?= htmlspecialchars($verification_status ?? '') ?>";
        const seatForm = document.getElementById("seatForm");
        const verifyPopup = document.getElementById("verifyPopup");
        const closeVerify = document.getElementById("closeVerify");
        const verifyOk = document.getElementById("verifyOk");

        seatForm.addEventListener("submit", function(e) {
            if (verificationStatus !== "verified") {
                e.preventDefault();
                verifyPopup.style.display = "flex";
            }
        });

        closeVerify.onclick = function() {
            verifyPopup.style.display = "none";
        };

        verifyOk.onclick = function() {
            verifyPopup.style.display = "none";
        };
    </script>
</body>

</html>