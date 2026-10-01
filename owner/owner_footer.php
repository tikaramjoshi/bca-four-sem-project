<?php
$isVerified = $isVerified ?? false;
?>
<footer class="last">
    <footer class="last">
        <div class="last-main">
            <div class="last-link">
                <h3><i class="fa fa-link"></i> Quick Links</h3>
                <a href="dashboard.php"><i class="fa fa-home"></i> Home</a>
                <?php
                function verifyLink($page, $isVerified)
                {
                    return $isVerified ? "href='$page'" : "href='#' onclick=\"alert('Please complete account verification first.'); return false;\"";
                }
                ?>
                <a <?= verifyLink('register_bus.php', $isVerified) ?>><i class="fa fa-bus"></i> Add Bus</a>
                <a <?= verifyLink('my_bus.php', $isVerified) ?>><i class="fa fa-bus"></i> My Bus</a>
                <a <?= verifyLink('driver.php', $isVerified) ?>><i class="fa fa-user"></i> Driver</a>
                <a <?= verifyLink('schedule.php', $isVerified) ?>><i class="fa fa-calendar"></i> Schedule</a>
                <a href="../logout.php"><i class="fa fa-sign-out-alt"></i> Logout</a>
            </div>
            <div class="last-contact" id="contactSection">
                <h3><i class="fa fa-address-book"></i> Contact</h3>
                <p><i class="fa fa-envelope"></i> Email: <a href="mailto:tikaramj519@gmail.com">tikaramj519@gmail.com</a></p>
                <p><i class="fa fa-phone"></i> Phone: <a href="tel:+9779840792553">+9779840792553</a></p>
                <p><i class="fab fa-whatsapp"></i> WhatsApp: <a href="https://wa.me/9779840792553">+9779840792553</a></p>
            </div>
            <div class="last-about" id="aboutSection">
                <h3><i class="fa fa-share-alt"></i> Follow Us</h3>
                <a href="#"><i class="fab fa-facebook"></i> Facebook</a>
                <a href="#"><i class="fab fa-instagram"></i> Instagram</a>
                <a href="#"><i class="fab fa-tiktok"></i> TikTok</a>
                <a href="#"><i class="fab fa-youtube"></i> YouTube</a>
                <h3><i class="fa fa-code"></i> Developed By</h3>
                <p><i class="fa fa-user"></i> Tikaram Joshi</p>
            </div>
        </div>
        <hr>
        <div class="copy">
            <p><i class="fa fa-copyright"></i> 2026 Online Bus Ticket Booking System || All Rights Reserved.</p>
        </div>
    </footer>
    </body>

    </html>