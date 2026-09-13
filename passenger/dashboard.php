<?php
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'passenger') {
    header("Location: ../login.php");
    exit();
}
require_once "../db.php";
$user_id = (int)$_SESSION['user_id'];
$stmt = $conn->prepare("SELECT name,profile_image,verification_status FROM users WHERE user_id=? AND role='passenger' LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$user) {
    session_destroy();
    header("Location: ../login.php");
    exit();
}
$passenger_name = $user['name'] ?? 'Passenger';
$verification_status = $user['verification_status'] ?? 'pending';
$profile_image = !empty($user['profile_image']) ? $user['profile_image'] : 'default.png';
$today = date('Y-m-d');
$max_date = date('Y-m-d', strtotime('+7 days'));
$routes = [];
$result = $conn->query("SELECT route_id,city_name FROM routes ORDER BY city_name ASC");
while ($result && $row = $result->fetch_assoc()) {
    $routes[] = $row;
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
// foreach ($popular_routes as &$route) {
//     $route['schedule_id'] = 0;
//     $route['available_seats'] = (int)$route['seats'];
//     $stmt = $conn->prepare("
//         SELECT schedule_id,available_seats
//         FROM schedules
//         WHERE bus_id=?
//         AND LOWER(TRIM(from_city))=LOWER(TRIM(?))
//         AND LOWER(TRIM(to_city))=LOWER(TRIM(?))
//         AND DATE(departure_date)=?
//         AND TIME(departure_time)=TIME(?)
//         AND status='active'
//         LIMIT 1
//     ");
//     if ($stmt) {
//         $stmt->bind_param(
//             "issss",
//             $route['bus_id'],
//             $route['from_city'],
//             $route['to_city'],
//             $route['departure_date'],
//             $route['departure_time']
//         );
//         $stmt->execute();
//         $schedule = $stmt->get_result()->fetch_assoc();
//         $stmt->close();
//         if ($schedule) {
//             $route['schedule_id'] = (int)$schedule['schedule_id'];
//             $route['available_seats'] = min(
//                 (int)$route['seats'],
//                 max(0, (int)$schedule['available_seats'])
//             );
//         }
//     }
// }
// unset($route);
foreach ($popular_routes as &$route) {
    $route['schedule_id'] = 0;
    $route['available_seats'] = (int)$route['seats'];

    $stmt = $conn->prepare("
        SELECT schedule_id, available_seats
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

            $booking_stmt = $conn->prepare("
                SELECT COUNT(*) AS booked_seats
                FROM bookings
                WHERE schedule_id=?
               
            ");

            $booking_stmt->bind_param("i", $route['schedule_id']);
            $booking_stmt->execute();
            $booking = $booking_stmt->get_result()->fetch_assoc();
            $booking_stmt->close();

            $booked_seats = (int)($booking['booked_seats'] ?? 0);
            $total_seats = (int)$route['seats'];

            $route['available_seats'] = max(
                0,
                min(
                    (int)$schedule['available_seats'],
                    $total_seats - $booked_seats
                )
            );
        }
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
</head>

<body>
    <div class="main">
        <nav>
            <div>
                <a href="#" class="active"><i class="fa fa-home"></i>&nbsp; Home</a>
                <a href="#contactSection"><i class="fa fa-phone"></i>&nbsp;Contact</a>
                <a href="#aboutSection"><i class="fa fa-info-circle"></i>&nbsp;About</a>
            </div>
            <!-- <div>
                <a href="#" id="home" class="active">Home</a>
                <a href="#contactSection" id="contact">Contact</a>
                <a href="#aboutSection" id="about">About</a>
            </div> -->
            <div class="profile-dropdown">
                <div class="profile-button">
                    <h3>Welcome- <span class="profile-name"><?= htmlspecialchars($passenger_name) ?></span></h3>
                    <span class="status <?= htmlspecialchars(strtolower($verification_status)) ?>"><?= htmlspecialchars(ucfirst($verification_status)) ?></span>
                    <img src="../uploads/profile/<?= htmlspecialchars($profile_image) ?>" alt="Profile" class="profile-image" onclick="toggleProfileMenu(event)" onerror="this.onerror=null;this.src='../images/default.png';">
                </div>
                <div class="profile-menu" id="profileMenu">
                    <a href="profile.php"><i class="fa fa-user-circle"></i> &nbsp; My Profile</a>
                    <a href="booking_history.php"> <i class="fa fa-ticket"></i> &nbsp; My Bookings</a>
                    <a href="booking_history.php"> <i class="fa fa-history"></i> &nbsp; Booking History</a>
                    <a href="../changepassword.php"> <i class="fa fa-key"></i> &nbsp; Change Password</a>
                    <hr>
                    <a href="../logout.php" class="logout-link"> <i class="fa fa-sign-out"></i> &nbsp; Logout</a>
                </div>
            </div>
        </nav>
    </div>
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
                                <span><i class="fa fa-arrow-right"></i></span>
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
                                </span>
                                <span>
                                    <?= date("H:i A", strtotime($route['departure_time'])) ?>
                                </span>
                                <!-- <span>
                                    <?= $total_seats ?> Total Seats
                                </span> -->
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
    <footer class="last">
        <div class="last-main">
            <div class="last-link">
                <h3>Quick Link</h3>
                <!-- <a href="#">Home</a>
                <a href="#">Gallery</a>
                <a href="../policy.php">Policy</a>
                <a href="profile.php">Profile</a>
                <a href="booking_history.php">Booking History</a> -->
                <a href="#"><i class="fa fa-home"></i>&nbsp; Home</a>
                <a href="#"><i class="fa fa-picture-o"></i>&nbsp; Gallery</a>
                <a href="../policy.php"><i class="fa fa-file-text"></i>&nbsp; Policy</a>
                <a href="profile.php"><i class="fa fa-user"></i>&nbsp; Profile</a>
                <a href="booking_history.php"><i class="fa fa-history"></i>&nbsp; Booking History</a>
            </div>
            <div class="last-contact" id="contactSection">
                <h3>Contact</h3>
                <!-- <p>Email: <a href="mailto:tikaramj519@gmail.com">tikaramj519@gmail.com</a></p>
                <p>Phone: <a href="tel:+9779840792553">+9779840792553</a></p>
                <p>Whatsapp: <a href="https://wa.me/9779840792553" target="_blank">+9779840792553</a></p> -->
                <p>Email: <a href="mailto:tikaramj519@gmail.com"><i class="fa fa-envelope"></i>&nbsp; tikaramj519@gmail.com</a></p>
                <p>Phone: <a href="tel:+9779840792553"><i class="fa fa-phone"></i>&nbsp; +9779840792553</a></p>
                <p>Whatsapp: <a href="https://wa.me/9779840792553" target="_blank"><i class="fa fa-whatsapp"></i>&nbsp; +9779840792553</a></p>
            </div>
            <div class="last-about" id="aboutSection">
                <h3>About</h3>
                <ul>
                    <!-- <li>Online Bus Ticket Booking System</li>
                    <li>Easy Bus Search</li>
                    <li>24/7 Customer Support</li>
                    <li>Safe Online Booking</li>
                    <li>No Cancel Ticket</li> -->
                    <li><i class="fa fa-bus"></i>&nbsp; Online Bus Ticket Booking System</li>
                    <li><i class="fa fa-search"></i>&nbsp; Easy Bus Search</li>
                    <li><i class="fa fa-headphones"></i>&nbsp; 24/7 Customer Support</li>
                    <li><i class="fa fa-shield"></i>&nbsp; Safe Online Booking</li>
                    <li><i class="fa fa-ban"></i>&nbsp; No Cancel Ticket</li>
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

        function toggleProfileMenu(event) {
            event.stopPropagation();
            document.getElementById("profileMenu").classList.toggle("show");
        }
        document.addEventListener("click", function(event) {
            const profile = document.querySelector(".profile-dropdown");
            const menu = document.getElementById("profileMenu");
            if (!profile.contains(event.target)) menu.classList.remove("show");
        });
        document.querySelectorAll("nav a").forEach(link => {
            link.addEventListener("click", function() {
                document.querySelectorAll("nav a").forEach(item => item.classList.remove("active"));
                link.classList.add("active");
            });
        });
    </script>
</body>

</html>