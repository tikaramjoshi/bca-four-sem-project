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
        $stmt = $conn->prepare("SELECT s.schedule_id,s.bus_id,s.from_city,s.to_city,s.departure_date,s.departure_time,s.ticket_price,s.available_seats,b.bus_number,b.bus_name,b.bus_type FROM schedules s INNER JOIN bus b ON s.bus_id=b.bus_id WHERE LOWER(TRIM(s.from_city))=LOWER(TRIM(?)) AND LOWER(TRIM(s.to_city))=LOWER(TRIM(?)) AND s.departure_date=? AND s.status='active' ORDER BY s.departure_time ASC");
        $stmt->bind_param("sss", $from, $to, $date);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $buses[] = $row;
        }
    }
}
$popular_routes = [];
$result = $conn->query("
    SELECT
        pr.popular_id,
        pr.bus_id,
        pr.from_city,
        pr.to_city,
        pr.price,
        pr.image,
        pr.departure_date,
        pr.departure_time,
        b.bus_name,
        b.bus_number,
        b.seats
    FROM popular_routes pr
    INNER JOIN bus b ON pr.bus_id=b.bus_id
    WHERE pr.status='active'
    AND b.status='approved'
    AND pr.departure_date>=CURDATE()
    ORDER BY pr.popular_id DESC
    LIMIT 5
");

while ($result && $row = $result->fetch_assoc()) {
    $popular_routes[] = $row;
}

foreach ($popular_routes as &$route) {
    $route['schedule_id'] = 0;
    $route['available_seats'] = (int)$route['seats'];

    $stmt = $conn->prepare("
        SELECT schedule_id,available_seats
        FROM schedules
        WHERE bus_id=?
        AND LOWER(TRIM(from_city))=LOWER(TRIM(?))
        AND LOWER(TRIM(to_city))=LOWER(TRIM(?))
        AND DATE(departure_date)=?
        AND TIME(departure_time)=TIME(?)
        AND status='active'
        LIMIT 1
    ");

    if ($stmt) {
        $stmt->bind_param(
            "issss",
            $route['bus_id'],
            $route['from_city'],
            $route['to_city'],
            $route['departure_date'],
            $route['departure_time']
        );

        $stmt->execute();
        $schedule = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($schedule) {
            $route['schedule_id'] = (int)$schedule['schedule_id'];
            $route['available_seats'] = min(
                (int)$route['seats'],
                max(0, (int)$schedule['available_seats'])
            );
        }
    }
}

unset($route);
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
include "./include/message/sql.php";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Online Bus Ticket Booking System</title>
    <link rel="stylesheet" href="index.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="include/message/mesage.css">

</head>

<body>
    <?php include "./include/message/code.php";  ?>
    <div class="main">
        <nav>
            <div>
                <a href="index.php" class="active"><i class="fa fa-home"></i>&nbsp; Home</a>
                <a href="#contactSection"><i class="fa fa-phone"></i>&nbsp;Contact</a>
                <a href="#aboutSection"><i class="fa fa-info-circle"></i>&nbsp;About</a>
            </div>
            <div>
                <button type="button" onclick="location.href='login.php'"><i class="fa fa-sign-in"></i>&nbsp;Login</button>
                <button type="button" onclick="location.href='register.php'"> <i class="fa fa-plus"></i>&nbsp;Register</button>
            </div>
        </nav>
    </div>
    <div class="top">
        <div class="datetime">
            <p id="time"></p>
            <h4 id="today"></h4>
        </div>
    </div>
    <form class="first" method="GET" action="index.php" onsubmit="return checkRoute()">
        <select name="from" id="fromCity" required>
            <option value="">From</option>
            <?php foreach ($routes as $route) { ?>
                <option value="<?= htmlspecialchars($route['city_name']) ?>" <?= (($_GET['from'] ?? '') == $route['city_name']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars(ucfirst(strtolower($route['city_name']))) ?>
                </option>
            <?php } ?>
        </select>
        <select name="to" id="toCity" required>
            <option value="">To</option>
            <?php foreach ($routes as $route) { ?>
                <option value="<?= htmlspecialchars($route['city_name']) ?>" <?= (($_GET['to'] ?? '') == $route['city_name']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars(ucfirst(strtolower($route['city_name']))) ?>
                </option>
            <?php } ?>
        </select>
        <input type="date" name="date" min="<?= $today ?>" max="<?= $max_date ?>" value="<?= htmlspecialchars($_GET['date'] ?? '') ?>" required>
        <button type="submit" name="search"><i class="fa fa-search"></i>&nbsp;Search Bus</button>
    </form>
    <div class="second">
        <?php if (isset($_GET['search'])) { ?>
            <div class="second-search">
                <h1>Search Result</h1>
                <?php if (count($buses) == 0) { ?>
                    <div class="no-result">
                        No bus available for the selected route and date.
                    </div>
                <?php } ?>
                <?php foreach ($buses as $bus) { ?>
                    <div class="route">
                        <img src="./images/bus.png" class="bus-image" alt="Bus" onerror="this.onerror=null;this.src='../images/bus.png';">
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

        <?php if ($popular_routes): ?>
            <div class="popular route">
                <h2>Popular Routes</h2>
                <p class="popular-subtitle">Popular bus routes</p>

                <div class="popular-list">
                    <?php foreach ($popular_routes as $route): ?>

                        <?php
                        $image = trim($route['image'] ?? '');
                        $image_path = ($image !== '' && file_exists($image))
                            ? $image
                            : "Bus Image/b1.jpg";

                        $schedule_id = (int)$route['schedule_id'];
                        $bus_id = (int)$route['bus_id'];
                        $total_seats = (int)$route['seats'];
                        $available_seats = (int)$route['available_seats'];
                        ?>
                        <div class="route-card">
                            <div class="route-image">
                                <img src="<?= htmlspecialchars($image_path) ?>" alt="Route Image" onerror="this.onerror=null;this.src='Bus Image/b1.jpg';">
                            </div>
                            <div class="route-info">
                                <h3>
                                    <?= htmlspecialchars(ucwords(strtolower($route['from_city']))) ?>
                                    <span> <i class="fa fa-arrow-right"></i> </span>
                                    <?= htmlspecialchars(ucwords(strtolower($route['to_city']))) ?>
                                </h3>

                                <p class="bus-name">
                                    <strong>Bus Name : </strong>
                                    <?= htmlspecialchars($route['bus_name']) ?>
                                    <br>
                                    <strong> Bus No : </strong>
                                    <?= htmlspecialchars($route['bus_number']) ?>
                                </p>

                                <div class="route-details">
                                    <strong> Rs. <?= number_format((float)$route['price'], 2) ?> </strong>
                                    <span> Date : <?= date("d M Y", strtotime($route['departure_date'])) ?> </span>
                                    <span> Time : <?= date("h:i A", strtotime($route['departure_time'])) ?> </span>
                                    <span class="<?= $available_seats > 0 ? 'available' : 'full' ?>"> <?= $available_seats ?> &nbsp; Seats Available </span>
                                </div>
                            </div>
                            <?php if ($schedule_id <= 0): ?>
                                <button type="button" class="popular-book-btn disabled" disabled> Not Available </button>
                            <?php elseif ($available_seats <= 0): ?>
                                <button type="button" class="popular-book-btn disabled" disabled> No Seats </button>
                            <?php else: ?>
                                <a href="passenger/seat_selection.php?schedule_id=<?= $schedule_id ?>&bus_id=<?= $bus_id ?>" class="popular-book-btn"> Book Now </a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <footer class="last">
        <div class="last-main">
            <div class="last-link">
                <h3>Quick Link</h3>
                <a href="index.php"><i class="fa fa-home"> </i> <span>Home</span></a>
                <a href="policy.php"><i class="fa fa-file-text-o"> </i> <span>Policy</span></a>
                <a href="login.php"><i class="fa fa-sign-in"> </i> <span>Login</span></a>
                <a href="register.php?role=owner"><i class="fa fa-user-plus"> </i> <span>Register Owner</span></a>
                <a href="register.php?role=driver"><i class="fa fa-id-card"> </i> <span>Register Driver</span></a>
                <a href="register.php?role=passenger"><i class="fa fa-user-plus"> </i> <span>Register Passenger</span></a>
            </div>
            <div class="last-contact" id="contactSection">
                <h3>Contact</h3>
                <p><i class="fa fa-envelope-o"></i> &nbsp; Email: <a href="mailto:tikaramj519@gmail.com">tikaramj519@gmail.com</a></p>
                <p><i class="fa fa-phone"></i> &nbsp; Phone: <a href="tel:+9779840792553">+9779840792553</a></p>
                <p><i class="fa fa-whatsapp"></i> &nbsp; Whatsapp: <a href="https://wa.me/9779840792553">+9779840792553</a></p>
            </div>
            <div class="last-about" id="aboutSection">
                <h3>About</h3>
                <ul>
                    <li><i class="fa fa-bus"></i> &nbsp; Online Bus Ticket Booking System</li>
                    <li><i class="fa fa-search"></i> &nbsp; Easy Bus Search</li>
                    <li><i class="fa fa-headphones"></i> &nbsp; 24/7 Customer Support</li>
                    <li><i class="fa fa-lock"></i> &nbsp; Safe Online Booking</li>
                    <li><i class="fa fa-ticket"></i> &nbsp; No Cancel Ticket</li>
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
        const fromCity = document.getElementById("fromCity");
        const toCity = document.getElementById("toCity");

        function updateToCity() {
            [...toCity.options].forEach(option => {
                option.disabled = option.value !== "" && option.value === fromCity.value;
            });
            if (toCity.value === fromCity.value) {
                toCity.value = "";
            }
        }

        fromCity.addEventListener("change", updateToCity);
        updateToCity();

        function checkRoute() {
            if (fromCity.value === toCity.value) {
                alert("From City and To City cannot be the same.");
                return false;
            }
            return true;
        }

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
        const posts = <?= json_encode($posts) ?>;
        let postIndex = 0;
    </script>
    <script src=" include/message/message.js" ;> </script>
</body>

</html>