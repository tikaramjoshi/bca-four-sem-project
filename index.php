<?php
session_start();
include "db.php";
$today = date('Y-m-d');
$max_date = date('Y-m-d', strtotime('+7 days'));
$routes = [];
$route_result = mysqli_query($conn, "SELECT route_id,city_name FROM routes ORDER BY city_name ASC");
if ($route_result) {
    while ($row = mysqli_fetch_assoc($route_result)) {
        $routes[] = $row;
    }
}
if (isset($_GET['book'])) {
    $schedule_id = (int)($_GET['schedule_id'] ?? 0);
    $bus_id = (int)($_GET['bus_id'] ?? 0);
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit;
    }
    $user_id = (int)$_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT role,verification_status FROM users WHERE user_id=?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user_result = $stmt->get_result();
    $user = $user_result->fetch_assoc();
    if (!$user || $user['role'] != 'passenger') {
        echo "<script>alert('Only passenger can book tickets.');window.location.href='index.php';</script>";
        exit;
    }
    if ($user['verification_status'] != 'verified') {
        echo "<script>alert('You have not login please login first');window.location.href='index.php';</script>";
        exit;
    }
    header("Location: passenger/seat_selection.php?schedule_id=" . $schedule_id . "&bus_id=" . $bus_id);
    exit;
}
$buses = [];
if (isset($_GET['search'])) {
    $from = trim($_GET['from'] ?? '');
    $to = trim($_GET['to'] ?? '');
    $date = $_GET['date'] ?? '';
    if ($from != '' && $to != '' && $date != '') {
        $stmt = $conn->prepare("SELECT s.schedule_id,s.bus_id,s.from_city,s.to_city,s.departure_date,s.departure_time,s.ticket_price,s.available_seats,b.bus_number,b.bus_name,b.bus_type FROM schedule s INNER JOIN bus b ON s.bus_id=b.bus_id WHERE LOWER(TRIM(s.from_city))=LOWER(TRIM(?)) AND LOWER(TRIM(s.to_city))=LOWER(TRIM(?)) AND s.departure_date=? AND s.status='active' ORDER BY s.departure_time ASC");
        $stmt->bind_param("sss", $from, $to, $date);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $buses[] = $row;
        }
    }
}
if (isset($_GET['book'])) {
    $schedule_id = (int)($_GET['schedule_id'] ?? 0);
    $bus_id = (int)($_GET['bus_id'] ?? 0);
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit;
    }
    $user_id = (int)$_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT role,verification_status FROM users WHERE user_id=?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user_result = $stmt->get_result();
    $user = $user_result->fetch_assoc();
    if (!$user || $user['role'] != 'passenger') {
        echo "<script>alert('Only passenger can book tickets.');window.location.href='index.php';</script>";
        exit;
    }
    if ($user['verification_status'] != 'verified') {
        echo "<script>alert('You have not login please login first');window.location.href='index.php';</script>";
        exit;
    }
    header("Location: passenger/seat_selection.php?schedule_id=" . $schedule_id . "&bus_id=" . $bus_id);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Online Bus Ticket Booking System</title>
    <link rel="stylesheet" href="index.css">

</head>

<body>
    <div class="main">
        <nav>
            <div>
                <a href="index.php" class="active">Home</a>
                <a href="#contactSection">Contact</a>
                <a href="#aboutSection">About</a>
            </div>
            <div>
                <button type="button" onclick="location.href='login.php'">Login</button>
                <button type="button" onclick="location.href='register.php'">Register</button>
            </div>
        </nav>
    </div>
    <div class="datetime">
        <p id="time"></p>
        <h4 id="today"></h4>
    </div>
    <form class="first" method="GET" action="index.php">
        <select name="from" required>
            <option value="">From</option>
            <?php foreach ($routes as $route) { ?>
                <option value="<?= htmlspecialchars($route['city_name']) ?>" <?= (($_GET['from'] ?? '') == $route['city_name']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars(ucfirst(strtolower($route['city_name']))) ?>
                </option>
            <?php } ?>
        </select>
        <select name="to" required>
            <option value="">To</option>
            <?php foreach ($routes as $route) { ?>
                <option value="<?= htmlspecialchars($route['city_name']) ?>" <?= (($_GET['to'] ?? '') == $route['city_name']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars(ucfirst(strtolower($route['city_name']))) ?>
                </option>
            <?php } ?>
        </select>
        <input type="date" name="date" min="<?= $today ?>" max="<?= $max_date ?>" value="<?= htmlspecialchars($_GET['date'] ?? '') ?>" required>
        <button type="submit" name="search">Search Bus</button>
    </form>
    <div class="second">
        <?php if (isset($_GET['search'])) { ?>
            <div class="second">
                <h1>Search Result</h1>
                <?php if (count($buses) == 0) { ?>
                    <div class="no-result">
                        No bus available for the selected route and date.
                    </div>
                <?php } ?>
                <?php foreach ($buses as $bus) { ?>
                    <div class="route">
                        <img src="Bus Image" alt="Bus">
                        <h4><?= htmlspecialchars($bus['bus_name']) ?></h4>
                        <p>Bus Number: <?= htmlspecialchars($bus['bus_number']) ?></p>
                        <p>Bus Type: <?= htmlspecialchars($bus['bus_type']) ?></p>
                        <p>
                            <?= htmlspecialchars(ucfirst(strtolower($bus['from_city']))) ?>
                            &nbsp; To &nbsp;
                            <?= htmlspecialchars(ucfirst(strtolower($bus['to_city']))) ?>
                        </p>
                        <p>Date: <?= htmlspecialchars($bus['departure_date']) ?></p>
                        <p>
                            Departure:
                            <?= date('h:i A', strtotime($bus['departure_time'])) ?>
                        </p>
                        <p>Price: Rs. <?= htmlspecialchars($bus['ticket_price']) ?></p>
                        <?php if ((int)$bus['available_seats'] > 0) { ?>
                            <p class="available">
                                Available Seats: <?= htmlspecialchars($bus['available_seats']) ?>
                            </p>
                            <form method="GET" action="index.php">
                                <input type="hidden" name="book" value="1">
                                <input type="hidden" name="schedule_id" value="<?= $bus['schedule_id'] ?>">
                                <input type="hidden" name="bus_id" value="<?= $bus['bus_id'] ?>">
                                <button type="submit">Book Now</button>
                            </form>
                        <?php } else { ?>
                            <p class="no-seat">No Available Seats</p>
                            <button type="button" disabled>Full</button>
                        <?php } ?>
                    </div>
                <?php } ?>
            </div>
        <?php } ?>
    </div>
    <footer class="last">
        <div class="last-main">
            <div class="last-link">
                <h3>Quick Link</h3>
                <a href="index.php">Home</a>
                <a href="policy.php">Policy</a>
                <a href="login.php">Login</a>
                <a href="register.php?role=passenger">Register Passenger</a>
                <a href="register.php?role=owner">Register Owner</a>
                <a href="register.php?role=driver">Register Driver</a>
            </div>
            <div class="last-contact" id="contactSection">
                <h3>Contact</h3>
                <p>Email: <a href="mailto:tikaramj519@://gmail.com">tikaramj519@gmail.com</a></p>
                <p>Phone:<a href="tel:+9779840792553">+9779840792553</a></p>
                <p>Whatsapp:<a href="https://wa.me/9779840792553">+9779840792553</a></p>
            </div>
            <div class="last-about" id="aboutSection">
                <h3>About</h3>
                <ul>
                    <li>Online Bus Ticket Booking System</li>
                    <li>Easy Bus Search</li>
                    <li>24/7 Customer Support</li>
                    <li>Safe Online Booking</li>
                    <li>No Cancel Ticket</li>
                </ul>
            </div>
        </div>
        <div class="last-mission">
            <h3>Our Mission</h3>
            <p>Our mission is to make bus ticket booking quick, safe and convenient for every passenger by providing reliable online services.</p>
        </div>
        <hr>
        <div class="copy">
            <p>&copy;2026 Online Bus Ticket Booking System | All rights reserved.</p>
        </div>
    </footer>
    <script>
        let menu = document.querySelectorAll("nav a");
        menu.forEach(link => {
            link.onclick = () => {
                menu.forEach(item => item.classList.remove("active"));
                link.classList.add("active");
            };
        });
        let time = document.getElementById("time");
        let today = document.getElementById("today");

        function clock() {
            let now = new Date();
            time.innerHTML = now.toLocaleTimeString();
            today.innerHTML = now.toDateString();
        }
        clock();
        setInterval(clock, 1000);
    </script>
</body>

</html>