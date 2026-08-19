<?php
if (!isset($isVerified)) {
    $isVerified = false;
}
?>
<footer class="last">
    <div class="last-main">
        <div class="last-link">
            <h3>Quick Links</h3>
            <a href="dashboard.php">Home</a>
            <a href="<?= $isVerified ? 'register_bus.php' : '#' ?>" <?= !$isVerified ? 'onclick="alert(\'Please complete account verification first.\');return false;"' : '' ?>>Add Bus</a>
            <a href="<?= $isVerified ? 'my_bus.php' : '#' ?>" <?= !$isVerified ? 'onclick="alert(\'Please complete account verification first.\');return false;"' : '' ?>>My Bus</a>
            <a href="<?= $isVerified ? 'driver.php' : '#' ?>" <?= !$isVerified ? 'onclick="alert(\'Please complete account verification first.\');return false;"' : '' ?>>Driver</a>
            <a href="<?= $isVerified ? 'assign_driver.php' : '#' ?>" <?= !$isVerified ? 'onclick="alert(\'Please complete account verification first.\');return false;"' : '' ?>>Assign Driver</a>
            <a href="<?= $isVerified ? 'schedule.php' : '#' ?>" <?= !$isVerified ? 'onclick="alert(\'Please complete account verification first.\');return false;"' : '' ?>>Schedule</a>
            <a href="../logout.php">Logout</a>
        </div>
        <div class="last-contact" id="contactSection">
            <h3>Contact</h3>
            <p>Email: <a href="mailto:tikaramj519@gmail.com">tikaramj519@gmail.com</a></p>
            <p>Phone: <a href="tel:+9779840792553">+9779840792553</a></p>
            <p>WhatsApp: <a href="https://wa.me/9779840792553">+9779840792553</a></p>
        </div>
        <div class="last-about" id="aboutSection">
            <h3>Follow Us</h3>
            <a href="#">Facebook</a>
            <a href="#">Instagram</a>
            <a href="#">TikTok</a>
            <a href="#">YouTube</a>
            <h3>Developed By</h3>
            <p>Tikaram Joshi</p>
        </div>
    </div>
    <hr>
    <div class="copy">
        <p>&copy; 2026 Online Bus Ticket Booking System || All Rights Reserved.</p>
    </div>
</footer>
<script>
    function toggleMenu() {
        document.getElementById("dropdownMenu").classList.toggle("show");
    }
    window.onclick = function(e) {
        if (!e.target.closest(".settings-menu")) {
            const menu = document.getElementById("dropdownMenu");
            if (menu) menu.classList.remove("show");
        }
    };
    const alertBox = document.getElementById("alertBox");
    if (alertBox) {
        setTimeout(function() {
            alertBox.style.transition = "opacity .5s";
            alertBox.style.opacity = "0";
            setTimeout(function() {
                alertBox.remove();
            }, 500);
        }, 5000);
    }
</script>
</body>

</html>