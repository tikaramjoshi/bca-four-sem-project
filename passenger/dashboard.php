<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'passenger') {
    header("Location: ../login.php");
    exit();
}
require_once "../db.php";
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("
    SELECT name, profile_image, verification_status
    FROM users
    WHERE user_id = ? AND role = 'passenger'
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$passenger_name = $user['name'] ?? 'Passenger';
$verification_status = $user['verification_status'] ?? 'pending';
$profile_image = !empty($user['profile_image']) ? $user['profile_image'] : 'default.png';
$stmt->close();

$locations = ['Kathmandu', 'Pokhara', 'Chitwan', 'Butwal', 'Dhangadhi', 'Nepalgunj', 'Biratnagar', 'Janakpur'];
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
                <a href="#contactSection" id="contact">
                    Contact
                </a>
                <a href="#aboutSection" id="about">
                    About
                </a>
            </div>

            <div class="profile-dropdown">
                <div class="profile-button">
                    <h3>Welcome-<span class="profile-name"><?= htmlspecialchars($passenger_name) ?></span></h3>&nbsp;&nbsp;&nbsp;&nbsp;
                    <span class="status <?= strtolower($verification_status) ?>">
                        <?= htmlspecialchars(ucfirst($verification_status)) ?>
                    </span>
                    <img src="../uploads/profile/<?= htmlspecialchars($profile_image) ?>" alt="Profile" class="profile-image" onclick="toggleProfileMenu(event)">
                </div>
                <div class="profile-menu" id="profileMenu">
                    <div class="profile-menu-header">
                        <img src="../uploads/profile/<?= htmlspecialchars($profile_image) ?>" alt="Profile" class="dropdown-profile-image">
                        <div>
                            <strong><?= htmlspecialchars($passenger_name) ?></strong>
                            <small>Passenger</small>
                        </div>
                    </div>
                    <hr>
                    <a href="profile.php"> My Profile</a>
                    <a href="booking_history.php"> My Bookings</a>
                    <a href="booking_history.php"> Booking History</a>
                    <a href="../changepassword.php">Change Password</a>
                    <hr>
                    <a href="../logout.php" class="logout-link"> Logout</a>
                </div>
            </div>

        </nav>
    </div>
    <div class="datetime">
        <p id="time"></p>
        <h4 id="today"></h4>
    </div>
    <div class="first">
        <select id="form" required>
            <option value="">From</option>
            <?php foreach ($locations as $location): ?>
                <option value="<?= htmlspecialchars($location) ?>">
                    <?= htmlspecialchars($location) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <select id="to" required>
            <option value="">To</option>
            <?php foreach ($locations as $location): ?>
                <option value="<?= htmlspecialchars($location) ?>">
                    <?= htmlspecialchars($location) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <input
            type="date"
            id="date"
            min="<?= date('Y-m-d') ?>"
            max="<?= date('Y-m-d', strtotime('+7 days')) ?>"
            required>
        <button type="button" id="search">
            Search Bus
        </button>
    </div><br><br><br><br><br>
    <!-- <div class="second" id="box">
        <h1>Popular Route</h1>
        <div class="route">
            <img
                src="Bus Image/b1.jpg"
                alt="Bus">
            <h4>
                <span>To:</span>
                <span>From:</span>
            </h4>
            <p>
                Price: Rs.
            </p>
            <button
                type="button"
                onclick="location.href='booking.php'">
                Booking
            </button>
        </div>
    </div> -->
    <footer class="last">
        <div class="last-main">
            <div class="last-link">
                <h3>Quick Link</h3>
                <a href="#">
                    Home
                </a>
                <a href="#">
                    Gallery
                </a>
                <a href="../policy.php">
                    Policy
                </a>
                <a href="profile.php">
                    Profile
                </a>
                <a href="booking_history.php">
                    Booking History
                </a>
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
                    <a href="https:
                        target=" _blank">
                        +9779840792553
                    </a>
                </p>
            </div>
            <div class="last-about" id="aboutSection">
                <h3>About</h3>
                <ul>
                    <li>
                        Online Bus Ticket Booking System
                    </li>
                    <li>
                        Easy Bus Search
                    </li>
                    <li>
                        24/7 Customer Support
                    </li>
                    <li>
                        Safe Online Booking
                    </li>
                    <li>
                        No Cancel Ticket
                    </li>
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
        let menu = document.querySelectorAll("nav a");
        menu.forEach(link => {
            link.onclick = () => {
                menu.forEach(item =>
                    item.classList.remove("active")
                );
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
        document.getElementById("search").addEventListener(
            "click",
            function() {
                let from =
                    document.getElementById("form").value.trim();
                let to =
                    document.getElementById("to").value.trim();
                let date =
                    document.getElementById("date").value;
                if (from === "" || to === "" || date === "") {
                    alert("Please select From, To and Date.");
                    return;
                }
                if (from.toLowerCase() === to.toLowerCase()) {
                    alert("From and To locations cannot be the same.");
                    return;
                }
                window.location.href =
                    "search_bus.php?from=" +
                    encodeURIComponent(from) +
                    "&to=" +
                    encodeURIComponent(to) +
                    "&date=" +
                    encodeURIComponent(date);
            }
        );

        function toggleProfileMenu() {
            let menu = document.getElementById("profileMenu");
            menu.classList.toggle("show");
        }
        document.addEventListener("click", function(event) {
            let profileDropdown =
                document.querySelector(".profile-dropdown");
            let profileMenu =
                document.getElementById("profileMenu");
            if (!profileDropdown.contains(event.target)) {
                profileMenu.classList.remove("show");
            }
        });


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
    </script>
</body>

</html>