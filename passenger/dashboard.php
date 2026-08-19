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
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Passenger Dashboard</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .popular.route {
            width: 96%;
            max-width: 1700px;
            margin: 55px auto 60px
        }

        .popular.route h2 {
            text-align: center;
            font-size: 30px;
            color: #1560bd;
            margin: 0 0 6px
        }

        .popular-subtitle {
            text-align: center;
            color: #777;
            font-size: 15px;
            margin: 0 0 30px
        }

        .popular-list {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 20px;
            width: 100%
        }

        .route-card {
            width: 100%;
            min-width: 0;
            height: 440px;
            background: #fff;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 4px 16px rgba(0, 0, 0, .12);
            display: flex;
            flex-direction: column;
            text-align: center;
            transition: .25s
        }

        .route-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, .18)
        }

        .route-image {
            width: 100%;
            height: 175px;
            overflow: hidden;
            background: #eee;
            flex-shrink: 0
        }

        .route-image img {
            width: 100%;
            height: 100%;
            display: block;
            object-fit: cover;
            transition: .3s
        }

        .route-card:hover .route-image img {
            transform: scale(1.05)
        }

        .route-info {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 18px 12px 5px
        }

        .route-info h3 {
            width: 100%;
            margin: 0 0 10px;
            font-size: 17px;
            line-height: 1.4;
            color: #222;
            font-weight: bold
        }

        .route-info h3 span {
            color: #1560bd;
            margin: 0 4px
        }

        .bus-name {
            width: 100%;
            margin: 0 0 14px;
            color: #555;
            font-size: 13px;
            font-weight: 600
        }

        .route-details {
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 7px;
            font-size: 13px;
            color: #555
        }

        .route-details span {
            display: block;
            width: 100%
        }

        .route-details strong {
            color: #1560bd;
            font-size: 16px
        }

        .route-details .available {
            color: #28a745;
            font-weight: bold
        }

        .route-details .full {
            color: #dc3545;
            font-weight: bold
        }

        .popular-book-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: calc(100% - 30px);
            height: 42px;
            margin: auto 15px 18px;
            padding: 10px 12px;
            background: #1560bd;
            color: #fff;
            text-decoration: none;
            border: 0;
            border-radius: 7px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            flex-shrink: 0
        }

        .popular-book-btn:hover {
            background: #0fa070;
            color: #fff
        }

        .popular-book-btn.disabled {
            background: #999;
            color: #fff;
            cursor: not-allowed
        }

        .popular-book-btn.disabled:hover {
            background: #999
        }

        @media(max-width:1300px) {
            .popular-list {
                grid-template-columns: repeat(4, minmax(0, 1fr))
            }
        }

        @media(max-width:1050px) {
            .popular-list {
                grid-template-columns: repeat(3, minmax(0, 1fr))
            }
        }

        @media(max-width:750px) {
            .popular-list {
                grid-template-columns: repeat(2, minmax(0, 1fr))
            }
        }

        @media(max-width:500px) {
            .popular.route {
                width: 94%;
                margin-top: 40px
            }

            .popular-list {
                grid-template-columns: 1fr
            }

            .route-card {
                max-width: 400px;
                margin: auto
            }
        }
    </style>
</head>

<body>

    <div class="main">
        <nav>
            <div>
                <a href="#" id="home" class="active">Home</a>
                <a href="#contactSection" id="contact">Contact</a>
                <a href="#aboutSection" id="about">About</a>
            </div>
            <div class="profile-dropdown">
                <div class="profile-button">
                    <h3>Welcome- <span class="profile-name"><?= htmlspecialchars($passenger_name) ?></span></h3>
                    <span class="status <?= htmlspecialchars(strtolower($verification_status)) ?>"><?= htmlspecialchars(ucfirst($verification_status)) ?></span>
                    <img src="../uploads/profile/<?= htmlspecialchars($profile_image) ?>" alt="Profile" class="profile-image" onclick="toggleProfileMenu(event)" onerror="this.onerror=null;this.src='../images/default.png';">
                </div>
                <div class="profile-menu" id="profileMenu">
                    <a href="profile.php">My Profile</a>
                    <a href="booking_history.php">My Bookings</a>
                    <a href="booking_history.php">Booking History</a>
                    <a href="../changepassword.php">Change Password</a>
                    <hr>
                    <a href="../logout.php" class="logout-link">Logout</a>
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
                                <span>→</span>
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
                                    <?= date("H:i", strtotime($route['departure_time'])) ?>
                                </span>

                                <span>
                                    <?= $total_seats ?> Total Seats
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

    <footer class="last">
        <div class="last-main">

            <div class="last-link">
                <h3>Quick Link</h3>
                <a href="#">Home</a>
                <a href="#">Gallery</a>
                <a href="../policy.php">Policy</a>
                <a href="profile.php">Profile</a>
                <a href="booking_history.php">Booking History</a>
            </div>

            <div class="last-contact" id="contactSection">
                <h3>Contact</h3>
                <p>Email: <a href="mailto:tikaramj519@gmail.com">tikaramj519@gmail.com</a></p>
                <p>Phone: <a href="tel:+9779840792553">+9779840792553</a></p>
                <p>Whatsapp: <a href="https://wa.me/9779840792553" target="_blank">+9779840792553</a></p>
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