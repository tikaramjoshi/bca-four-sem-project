<?php
include "pass_header.php";
include "../post_popup.php";
$today = date('Y-m-d');
$max_date = date('Y-m-d', strtotime('+7 days'));
$routes = [];
$result = $conn->query("SELECT route_id,city_name FROM routes ORDER BY city_name ASC");
while ($result && $row = $result->fetch_assoc()) {
    $routes[] = $row;
}
$popular_routes = [];
$result = $conn->query("
        SELECT pr.popular_id, pr.bus_id, pr.from_city, pr.to_city, pr.price, pr.image, pr.departure_date, pr.departure_time, b.bus_name, b.bus_number, b.seats FROM popular_routes pr INNER JOIN bus b ON pr.bus_id=b.bus_id WHERE pr.status='active' AND b.status='approved' AND pr.departure_date>=CURDATE() ORDER BY pr.popular_id DESC LIMIT 5 ");
while ($result && $row = $result->fetch_assoc()) {
    $popular_routes[] = $row;
}
foreach ($popular_routes as &$route) {
    $route['schedule_id'] = 0;
    $route['available_seats'] = (int)$route['seats'];
    $stmt = $conn->prepare("SELECT schedule_id,available_seats FROM schedules WHERE bus_id=? AND LOWER(TRIM(from_city))=LOWER(TRIM(?)) AND LOWER(TRIM(to_city))=LOWER(TRIM(?)) AND DATE(departure_date)=DATE(?) AND TIME(departure_time)=TIME(?) AND status='active' LIMIT 1");
    $stmt->bind_param("issss", $route['bus_id'], $route['from_city'], $route['to_city'], $route['departure_date'], $route['departure_time']);
    $stmt->execute();
    $schedule = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($schedule) {
        $route['schedule_id'] = (int)$schedule['schedule_id'];
        $booking_stmt = $conn->prepare("SELECT COUNT(*) AS booked_seats FROM bookings WHERE schedule_id=? AND status IN ('pending','confirmed','paid')");
        $booking_stmt->bind_param("i", $route['schedule_id']);
        $booking_stmt->execute();
        $booking = $booking_stmt->get_result()->fetch_assoc();
        $booking_stmt->close();
        $booked_seats = (int)($booking['booked_seats'] ?? 0);
        $route['available_seats'] = max(0, (int)$route['seats'] - $booked_seats);
    }
}
unset($route);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Passenger Dashboard</title>
    <link rel="stylesheet" href="dashboard.css">
</head>

<body>
    <div class="datetime">
        <p id="time"></p>
        <h4 id="today"></h4>
    </div>
    <div class="first">
        <select id="fromCity" required>
            <option value="">From</option>
            <?php foreach ($routes as $route): ?>
                <option value="<?= htmlspecialchars($route['city_name']) ?>"><?= htmlspecialchars(ucwords(strtolower($route['city_name']))) ?></option>
            <?php endforeach; ?>
        </select>
        <select id="toCity" required>
            <option value="">To</option>
            <?php foreach ($routes as $route): ?>
                <option value="<?= htmlspecialchars($route['city_name']) ?>"><?= htmlspecialchars(ucwords(strtolower($route['city_name']))) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="date" id="date" min="<?= $today ?>" max="<?= $max_date ?>" required>
        <button type="button" id="search">Search Bus</button>
    </div>
    <br><br><br>
    <?php if ($popular_routes): ?>
        <div class="popular route">
            <h2>Popular Routes</h2>
            <p class="popular-subtitle">Popular bus routes</p>
            <div class="popular-list">
                <?php foreach ($popular_routes as $route): ?>
                    <?php
                    $image = trim($route['image'] ?? '');
                    $image_path = ($image !== '' && file_exists("../" . $image)) ? "../" . $image : "../Bus Image/b1.jpg";
                    $schedule_id = (int)$route['schedule_id'];
                    $bus_id = (int)$route['bus_id'];
                    $total_seats = (int)$route['seats'];
                    $available_seats = (int)$route['available_seats'];
                    ?>
                    <div class="route-card">
                        <div class="route-image">
                            <img src="<?= htmlspecialchars($image_path) ?>" alt="Route Image" onerror="this.onerror=null;this.src='../Bus Image/b1.jpg';">
                        </div>
                        <div class="route-info">
                            <h3>
                                <?= htmlspecialchars(ucwords(strtolower($route['from_city']))) ?>
                                <span><i class="fa fa-long-arrow-right"></i></span>
                                <?= htmlspecialchars(ucwords(strtolower($route['to_city']))) ?>
                            </h3>
                            <p class="bus-name">
                                <?= htmlspecialchars($route['bus_name']) ?> - <?= htmlspecialchars($route['bus_number']) ?>
                            </p>
                            <div class="route-details">
                                <span>
                                    <strong>Rs. <?= number_format((float)$route['price'], 2) ?></strong>
                                </span>
                                <span>
                                    <?= date("d M Y", strtotime($route['departure_date'])) ?>
                                    <strong>:</strong>
                                    <?= date("H:i A", strtotime($route['departure_time'])) ?>
                                </span>
                                <span class="<?= $available_seats > 0 ? 'available' : 'full' ?>">
                                    <?= $available_seats ?> Available
                                </span>
                            </div>
                        </div>
                        <?php if ($verification_status !== 'verified'): ?>
                            <button type="button" class="popular-book-btn disabled" onclick="alert('Your passenger account must be verified before booking.')">Book Now</button>
                        <?php elseif ($schedule_id <= 0): ?>
                            <button type="button" class="popular-book-btn disabled" disabled>Not Available</button>
                        <?php elseif ($available_seats <= 0): ?>
                            <button type="button" class="popular-book-btn disabled" disabled>No Seats</button>
                        <?php else: ?>
                            <a href="seat_selection.php?schedule_id=<?= $schedule_id ?>&bus_id=<?= $bus_id ?>" class="popular-book-btn">Book Now</a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
    <?php include "pass_footer.php"; ?>
    <script>
        const fromCity = document.getElementById("fromCity");
        const toCity = document.getElementById("toCity");

        function updateToCity() {
            [...toCity.options].forEach(option => {
                option.disabled = option.value !== "" && option.value.toLowerCase() === fromCity.value.toLowerCase();
            });
            if (toCity.value && toCity.value.toLowerCase() === fromCity.value.toLowerCase()) toCity.value = "";
        }
        fromCity.addEventListener("change", updateToCity);
        updateToCity();
        document.getElementById("search").addEventListener("click", function() {
            const from = fromCity.value.trim();
            const to = toCity.value.trim();
            const date = document.getElementById("date").value;
            if (!from || !to || !date) {
                alert("Please select From, To and Date.");
                return;
            }
            if (from.toLowerCase() === to.toLowerCase()) {
                alert("From City and To City cannot be the same.");
                return;
            }
            window.location.href = "search_bus.php?from=" + encodeURIComponent(from) + "&to=" + encodeURIComponent(to) + "&date=" + encodeURIComponent(date);
        });

        function clock() {
            const now = new Date();
            document.getElementById("time").innerHTML = now.toLocaleTimeString();
            document.getElementById("today").innerHTML = now.toDateString();
        }
        clock();
        setInterval(clock, 1000);
    </script>
</body>

</html>