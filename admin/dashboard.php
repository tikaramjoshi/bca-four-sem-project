<?php
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../login.php");
    exit();
}
require_once "../db.php";
$active_page = 'dashboard.php';
$totalOwners = $conn->query("SELECT COUNT(*) FROM users WHERE role='owner'")->fetch_row()[0];
$totalDrivers = $conn->query("SELECT COUNT(*) FROM users WHERE role='driver'")->fetch_row()[0];
$totalPassengers = $conn->query("SELECT COUNT(*) FROM users WHERE role='passenger'")->fetch_row()[0];
$totalBuses = $conn->query("SELECT COUNT(*) FROM bus")->fetch_row()[0];
$totalOwnerVerification = $conn->query("SELECT COUNT(*) FROM owner_verification WHERE status='pending'")->fetch_row()[0];
$totalDriverVerification = $conn->query("SELECT COUNT(*) FROM driver_verification WHERE status='pending'")->fetch_row()[0];
$totalPassengerVerification = $conn->query("SELECT COUNT(*) FROM users WHERE role='passenger' AND (verification_status IS NULL OR verification_status <> 'verified')")->fetch_row()[0];
$totalPending = $conn->query("SELECT COUNT(*) FROM bus WHERE status='pending'")->fetch_row()[0];
if (isset($_GET['action'], $_GET['id'])) {
    $id = (int)$_GET['id'];
    $action = $_GET['action'];
    if ($action === "approve") {
        $stmt = $conn->prepare("UPDATE bus SET status='approved' WHERE bus_id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: dashboard.php");
    exit();
}
require_once "admin_header.php";
?>
<div class="content">
    <div class="section-title">
        <h2>Dashboard Overview</h2>
    </div>
    <div class="cards">
        <div class="card">
            <h3>Total Owners</h3>
            <p><?= $totalOwners ?></p>
        </div>
        <div class="card">
            <h3>Total Drivers</h3>
            <p><?= $totalDrivers ?></p>
        </div>
        <div class="card">
            <h3>Total Passengers</h3>
            <p><?= $totalPassengers ?></p>
        </div>
        <div class="card">
            <h3>Total Bus</h3>
            <p><?= $totalBuses ?></p>
        </div>
    </div>
    <div class="cards pending-cards">
        <div class="card pending-owner">
            <h3>Pending Owner</h3>
            <p><?= $totalOwnerVerification ?></p>
        </div>
        <div class="card pending-driver">
            <h3>Pending Driver</h3>
            <p><?= $totalDriverVerification ?></p>
        </div>
        <div class="card pending-passenger">
            <h3>Pending Passenger</h3>
            <p><?= $totalPassengerVerification ?></p>
        </div>
        <div class="card pending-bus">
            <h3>Pending Bus</h3>
            <p><?= $totalPending ?></p>
        </div>
    </div>
    <div class="table-box notification-box">
        <h2><i class="fa fa-bell"></i> Notifications</h2>
        <?php if ($totalPending > 0): ?>
            <p><?= $totalPending ?> pending bus request(s)</p>
        <?php endif; ?>
        <?php if ($totalOwnerVerification > 0): ?>
            <p><?= $totalOwnerVerification ?> pending owner verification request(s)</p>
        <?php endif; ?>
        <?php if ($totalDriverVerification > 0): ?>
            <p><?= $totalDriverVerification ?> pending driver verification request(s)</p>
        <?php endif; ?>
        <?php if ($totalPassengerVerification > 0): ?>
            <p><?= $totalPassengerVerification ?> pending passenger verification request(s)</p>
        <?php endif; ?>
        <?php if ($totalPending == 0 && $totalOwnerVerification == 0 && $totalDriverVerification == 0 && $totalPassengerVerification == 0): ?>
            <p class="no-notification">No pending requests.</p>
        <?php endif; ?>
    </div>
    <br><br>
    <?php if ($totalPending > 0): ?>
        <div class="table-box">
            <h2>Pending Bus Requests</h2>
            <div class="table-scroll">
                <table>
                    <tr>
                        <th>Bus No</th>
                        <th>Bus Name</th>
                        <th>Owner</th>
                        <th>Type</th>
                        <th>Seats</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                    <?php
                    $stmt = $conn->prepare("SELECT bus.bus_id,bus.bus_number,bus.bus_name,bus.bus_type,bus.seats,bus.status,users.name AS owner_name FROM bus INNER JOIN users ON bus.owner_id=users.user_id WHERE bus.status='pending' ORDER BY bus.bus_id DESC");
                    $stmt->execute();
                    $result = $stmt->get_result();
                    while ($row = $result->fetch_assoc()):
                    ?>
                        <tr>
                            <td><?= htmlspecialchars($row['bus_number']) ?></td>
                            <td><?= htmlspecialchars($row['bus_name']) ?></td>
                            <td><?= htmlspecialchars($row['owner_name']) ?></td>
                            <td><?= htmlspecialchars($row['bus_type']) ?></td>
                            <td><?= htmlspecialchars($row['seats']) ?></td>
                            <td><span class="pending-text">Pending</span></td>
                            <td>
                                <a class="approve" href="?action=approve&id=<?= (int)$row['bus_id'] ?>" onclick="return confirm('Approve this bus?')">Approve</a>
                                <a class="reject" href="reject_bus.php?id=<?= (int)$row['bus_id'] ?>" onclick="return confirm('Do you want to reject this bus?')">Reject</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </table>
            </div>
        </div>
    <?php endif; ?>
    <br><br>
    <?php if ($totalOwnerVerification > 0): ?>
        <div class="table-box">
            <h2>Pending Owner Verification</h2>
            <div class="table-scroll">
                <table>
                    <tr>
                        <th>ID</th>
                        <th>Owner</th>
                        <th>Company</th>
                        <th>Photo</th>
                        <th>Certificate</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                    <?php
                    $stmt = $conn->prepare("SELECT ov.*,u.name FROM owner_verification ov INNER JOIN users u ON ov.owner_id=u.user_id WHERE ov.status='pending' ORDER BY ov.verification_id DESC");
                    $stmt->execute();
                    $result = $stmt->get_result();
                    while ($row = $result->fetch_assoc()):
                    ?>
                        <tr>
                            <td><?= htmlspecialchars($row['verification_id']) ?></td>
                            <td><?= htmlspecialchars($row['name']) ?></td>
                            <td><?= htmlspecialchars($row['company_name']) ?></td>
                            <td><a href="../uploads/<?= htmlspecialchars($row['owner_photo']) ?>" target="_blank">View Photo</a></td>
                            <td><a href="../uploads/<?= htmlspecialchars($row['company_certificate']) ?>" target="_blank">View Certificate</a></td>
                            <td><span class="pending-text">Pending</span></td>
                            <td>
                                <a href="verify_owner.php?id=<?= (int)$row['verification_id'] ?>" class="approve" onclick="return confirm('Verify this owner?')">Verify</a>
                                <a class="reject" href="reject_owner.php?id=<?= (int)$row['verification_id'] ?>" onclick="return confirm('Reject this owner?')">Reject</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </table>
            </div>
        </div>
    <?php endif; ?>
    <br><br>
    <?php
    $driverVerificationRows = [];
    $stmt = $conn->prepare("SELECT dv.verification_id,dv.driver_id,dv.license_number,dv.license_issue_date,dv.license_expiry_date,dv.profile_photo,dv.license_photo_front,dv.license_photo_back,dv.status,dv.created_at,u.name,u.email,u.phone FROM driver_verification dv INNER JOIN users u ON dv.driver_id=u.user_id WHERE dv.status='pending' ORDER BY dv.verification_id DESC");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $driverVerificationRows[] = $row;
    }
    $stmt->close();
    ?>
    <?php if (count($driverVerificationRows) > 0): ?>
        <div class="table-box">
            <h2><i class="fa fa-id-card"></i> Pending Driver Verification</h2>
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Driver</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>License No.</th>
                            <th>Expiry Date</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($driverVerificationRows as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['verification_id']) ?></td>
                                <td><?= htmlspecialchars($row['name']) ?></td>
                                <td><?= htmlspecialchars($row['email']) ?></td>
                                <td><?= htmlspecialchars($row['phone']) ?></td>
                                <td><?= htmlspecialchars($row['license_number']) ?></td>
                                <td><?= htmlspecialchars($row['license_expiry_date']) ?></td>
                                <td><span class="pending-text">Pending</span></td>
                                <td>
                                    <a href="view_driver.php?id=<?= (int)$row['driver_id'] ?>" class="approve">View</a>
                                    <a href="verify_driver.php?id=<?= (int)$row['verification_id'] ?>" class="approve" onclick="return confirm('Verify this driver?')">Verify</a>
                                    <a href="reject_driver.php?id=<?= (int)$row['verification_id'] ?>" class="reject" onclick="return confirm('Reject this driver?')">Reject</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
    <br><br>
    <?php if ($totalPassengerVerification > 0): ?>
        <div class="table-box">
            <h2>Pending Passenger Verification</h2>
            <div class="table-scroll">
                <table>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                    <?php
                    $stmt = $conn->prepare("SELECT user_id,name,email,phone,verification_status FROM users WHERE role='passenger' AND (verification_status IS NULL OR verification_status <> 'verified') ORDER BY user_id DESC");
                    $stmt->execute();
                    $result = $stmt->get_result();
                    while ($row = $result->fetch_assoc()):
                    ?>
                        <tr>
                            <td><?= htmlspecialchars($row['user_id']) ?></td>
                            <td><?= htmlspecialchars($row['name']) ?></td>
                            <td><?= htmlspecialchars($row['email']) ?></td>
                            <td><?= htmlspecialchars($row['phone']) ?></td>
                            <td><span class="pending-text">Pending</span></td>
                            <td>
                                <a class="approve" href="verify_passenger.php?id=<?= (int)$row['user_id'] ?>" onclick="return confirm('Verify this passenger?')">Verify</a>
                                <a class="reject" href="reject_passenger.php?id=<?= (int)$row['user_id'] ?>" onclick="return confirm('Reject this passenger?')">Reject</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </table>
            </div>
        </div>
    <?php endif; ?>
    <br><br>
</div>
</div>
<script>
    function toggleMenu() {
        document.getElementById("settingMenu").classList.toggle("show");
    }
    window.addEventListener("click", function(e) {
        if (!e.target.closest(".setting")) {
            document.getElementById("settingMenu").classList.remove("show");
        }
    });
</script>
</body>

</html>