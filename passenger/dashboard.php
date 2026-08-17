<?php
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'passenger') {
    header("Location: ../login.php");
    exit();
}
require_once "../db.php";

$user_id = (int)$_SESSION['user_id'];

$stmt = $conn->prepare("
    SELECT name, profile_image, verification_status
    FROM users
    WHERE user_id = ? AND role = 'passenger'
    LIMIT 1
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
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
$route_result = mysqli_query(
    $conn,
    "SELECT route_id, city_name FROM routes ORDER BY city_name ASC"
);

if ($route_result) {
    while ($row = mysqli_fetch_assoc($route_result)) {
        $routes[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Passenger Dashboard</title>
    <link rel="stylesheet" href="dashboard.css">
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
                    <h3>
                        Welcome-
                        <span class="profile-name">
                            <?= htmlspecialchars($passenger_name) ?>
                        </span>
                    </h3>

                    <span class="status <?= htmlspecialchars(strtolower($verification_status)) ?>">
                        <?= htmlspecialchars(ucfirst($verification_status)) ?>
                    </span>

                    <img
                        src="../uploads/profile/<?= htmlspecialchars($profile_image) ?>"
                        alt="Profile"
                        class="profile-image"
                        onclick="toggleProfileMenu(event)"
                        onerror="this.onerror=null;this.src='../images/default.png';">
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
                <option value="<?= htmlspecialchars($route['city_name']) ?>">
                    <?= htmlspecialchars(ucfirst(strtolower($route['city_name']))) ?>
                </option>
            <?php endforeach; ?>

        </select>

        <select id="toCity" required>
            <option value="">To</option>

            <?php foreach ($routes as $route): ?>
                <option value="<?= htmlspecialchars($route['city_name']) ?>">
                    <?= htmlspecialchars(ucfirst(strtolower($route['city_name']))) ?>
                </option>
            <?php endforeach; ?>

        </select>

        <input
            type="date"
            id="date"
            min="<?= $today ?>"
            max="<?= $max_date ?>"
            required>

        <button type="button" id="search">
            Search Bus
        </button>

    </div>

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

                <p>
                    Email:
                    <a href="mailto:tikaramj519@gmail.com">
                        tikaramj519@gmail.com
                    </a>
                </p>

                <p>
                    Phone:
                    <a href="tel:+9779840792553">
                        +9779840792553
                    </a>
                </p>

                <p>
                    Whatsapp:
                    <a href="https://wa.me/9779840792553" target="_blank">
                        +9779840792553
                    </a>
                </p>
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

            <p>
                Our mission is to make bus ticket booking quick,
                safe and convenient for every passenger by providing
                reliable online services.
            </p>
        </div>

        <hr>

        <div class="copy">
            <p>
                &copy;2026 Online Bus Ticket Booking System |
                All rights reserved.
            </p>
        </div>

    </footer>

    <script>
        const fromCity = document.getElementById("fromCity");
        const toCity = document.getElementById("toCity");

        function updateToCity() {
            [...toCity.options].forEach(option => {
                option.disabled =
                    option.value !== "" &&
                    option.value === fromCity.value;
            });

            if (toCity.value === fromCity.value) {
                toCity.value = "";
            }
        }

        fromCity.addEventListener("change", updateToCity);

        updateToCity();

        function checkRoute() {
            if (!fromCity.value || !toCity.value) {
                alert("Please select From and To city.");
                return false;
            }

            if (fromCity.value.toLowerCase() === toCity.value.toLowerCase()) {
                alert("From City and To City cannot be the same.");
                return false;
            }

            return true;
        }

        document.getElementById("search").addEventListener("click", function() {

            const from = fromCity.value.trim();
            const to = toCity.value.trim();
            const date = document.getElementById("date").value;

            if (from === "" || to === "" || date === "") {
                alert("Please select From, To and Date.");
                return;
            }

            if (from.toLowerCase() === to.toLowerCase()) {
                alert("From City and To City cannot be the same.");
                return;
            }

            window.location.href =
                "search_bus.php?from=" +
                encodeURIComponent(from) +
                "&to=" +
                encodeURIComponent(to) +
                "&date=" +
                encodeURIComponent(date);
        });

        const time = document.getElementById("time");
        const todayText = document.getElementById("today");

        function clock() {
            const now = new Date();

            time.innerHTML = now.toLocaleTimeString();
            todayText.innerHTML = now.toDateString();
        }

        clock();

        setInterval(clock, 1000);

        function toggleProfileMenu(event) {
            event.stopPropagation();

            const menu = document.getElementById("profileMenu");

            menu.classList.toggle("show");
        }

        document.addEventListener("click", function(event) {

            const profile = document.querySelector(".profile-dropdown");
            const menu = document.getElementById("profileMenu");

            if (!profile.contains(event.target)) {
                menu.classList.remove("show");
            }
        });

        const menuLinks = document.querySelectorAll("nav a");

        menuLinks.forEach(link => {

            link.addEventListener("click", function() {

                menuLinks.forEach(item => {
                    item.classList.remove("active");
                });

                link.classList.add("active");
            });

        });
    </script>

</body>

</html>