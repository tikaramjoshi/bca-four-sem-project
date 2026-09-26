<?php
if (session_status() === PHP_SESSION_NONE) session_start();
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
$profile_image = !empty($user['profile_image']) ? basename($user['profile_image']) : 'default.png';
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
<div class="main">
    <nav>
        <div>
            <a href="dashboard.php" class="active"><i class="fa fa-home"></i>&nbsp; Home</a>
            <a href="booking_history.php"><i class="fa fa-ticket"></i>&nbsp; My Bookings</a>
            <a href="#contactSection"><i class="fa fa-phone"></i>&nbsp;Contact</a>
            <a href="#aboutSection"><i class="fa fa-info-circle"></i>&nbsp;About</a>
        </div>
        <div class="profile-dropdown">
            <div class="profile-button">
                <h3>Welcome- <span class="profile-name"><?= htmlspecialchars($passenger_name) ?></span></h3>
                <?php if ($verification_status !== 'verified'): ?>
                    <span class="status <?= htmlspecialchars(strtolower($verification_status)) ?>"><?= htmlspecialchars(ucfirst($verification_status)) ?></span>
                <?php endif; ?>
                <img src="../uploads/profile/passenger/<?= htmlspecialchars($passenger_name) ?>/<?= htmlspecialchars($profile_image) ?>" alt="Profile" class="profile-image" onclick="toggleProfileMenu(event)" onerror="this.onerror=null;this.src='../uploads/default.png';">
            </div>
            <div class="profile-menu" id="profileMenu">
                <a href="profile.php"><i class="fa fa-user-circle"></i>&nbsp; My Profile</a>
                <a href="booking_history.php"><i class="fa fa-ticket"></i>&nbsp; My Bookings</a>
                <a href="booking_history.php"><i class="fa fa-history"></i>&nbsp; Booking History</a>
                <a href="../changepassword.php"><i class="fa fa-key"></i>&nbsp; Change Password</a>
                <hr>
                <a href="../logout.php" class="logout-link"><i class="fa fa-sign-out"></i>&nbsp; Logout</a>
            </div>
        </div>
    </nav>
</div>
<script>
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