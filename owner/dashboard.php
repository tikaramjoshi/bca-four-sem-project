<?php
$page_title = "Owner Dashboard";
include "header.php";

$message = "";
$message_type = "";

if (isset($_SESSION['success'])) {
    $message = $_SESSION['success'];
    $message_type = "success";
    unset($_SESSION['success']);
}

if (isset($_SESSION['error'])) {
    $message = $_SESSION['error'];
    $message_type = "error";
    unset($_SESSION['error']);
}

$search = trim($_GET['search'] ?? '');

$sql = "SELECT * FROM bus WHERE owner_id=?";
if ($search !== '') {
    $sql .= " AND (bus_number LIKE ? OR bus_name LIKE ?)";
}
$sql .= " ORDER BY bus_id DESC";

$stmt = $conn->prepare($sql);

if ($search !== '') {
    $like = "%" . $search . "%";
    $stmt->bind_param("iss", $owner_id, $like, $like);
} else {
    $stmt->bind_param("i", $owner_id);
}

$stmt->execute();
$busResult = $stmt->get_result();

$totalBusStmt = $conn->prepare("SELECT COUNT(*) FROM bus WHERE owner_id=?");
$totalBusStmt->bind_param("i", $owner_id);
$totalBusStmt->execute();
$totalBus = (int)$totalBusStmt->get_result()->fetch_row()[0];
$totalBusStmt->close();

$pendingStmt = $conn->prepare("SELECT COUNT(*) FROM bus WHERE owner_id=? AND status='pending'");
$pendingStmt->bind_param("i", $owner_id);
$pendingStmt->execute();
$pending = (int)$pendingStmt->get_result()->fetch_row()[0];
$pendingStmt->close();

$approvedStmt = $conn->prepare("SELECT COUNT(*) FROM bus WHERE owner_id=? AND status='approved'");
$approvedStmt->bind_param("i", $owner_id);
$approvedStmt->execute();
$approved = (int)$approvedStmt->get_result()->fetch_row()[0];
$approvedStmt->close();

$rejectedStmt = $conn->prepare("SELECT COUNT(*) FROM bus WHERE owner_id=? AND status='rejected'");
$rejectedStmt->bind_param("i", $owner_id);
$rejectedStmt->execute();
$rejected = (int)$rejectedStmt->get_result()->fetch_row()[0];
$rejectedStmt->close();

$driverStmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE role='driver' AND verification_status='verified'");
$driverStmt->execute();
$drivers = (int)$driverStmt->get_result()->fetch_row()[0];
$driverStmt->close();

$assignStmt = $conn->prepare("SELECT COUNT(*) FROM bus_driver bd INNER JOIN bus b ON bd.bus_id=b.bus_id WHERE b.owner_id=?");
$assignStmt->bind_param("i", $owner_id);
$assignStmt->execute();
$totalDrivers = (int)$assignStmt->get_result()->fetch_row()[0];
$assignStmt->close();
?>

<section class="stats">
    <div class="box">
        <h2><?= $totalBus ?></h2>
        <p>Total Bus</p>
    </div>
    <div class="box">
        <h2><?= $drivers ?></h2>
        <p>Drivers</p>
    </div>
    <div class="box">
        <h2><?= $pending ?></h2>
        <p>Pending</p>
    </div>
    <div class="box">
        <h2><?= $approved ?></h2>
        <p>Approved</p>
    </div>
    <div class="box">
        <h2><?= $rejected ?></h2>
        <p>Rejected</p>
    </div>
    <div class="box">
        <h2><?= $totalDrivers ?></h2>
        <p>Assign Driver</p>
    </div>
</section>

<?php if ($message !== ''): ?>
    <div class="alert <?= htmlspecialchars($message_type) ?>" id="alertBox"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<section class="search-area">
    <h2>My Bus List</h2>
    <form method="GET" class="search-box">
        <input type="text" name="search" placeholder="Search Bus Number or Bus Name" value="<?= htmlspecialchars($search) ?>">
        <button type="submit">Search</button>
    </form>
</section>

<section class="table-area">
    <table class="bus-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Image</th>
                <th>Bus Number</th>
                <th>Bus Name</th>
                <th>Bus Type</th>
                <th>Seats</th>
                <th>Status</th>
                <th>Action</th>
                <th>Reject</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($busResult->num_rows > 0): ?>
                <?php while ($row = $busResult->fetch_assoc()): ?>
                    <tr>
                        <td><?= $row['bus_id'] ?></td>
                        <td>
                            <?php if (!empty($row['bus_image'])): ?>
                                <img src="../uploads/bus/<?= htmlspecialchars($row['bus_image']) ?>" width="90" height="60" style="object-fit:cover;border-radius:6px;">
                            <?php else: ?>
                                <img src="../images/no-image.png" width="90" height="60">
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($row['bus_number']) ?></td>
                        <td><?= htmlspecialchars($row['bus_name']) ?></td>
                        <td><?= htmlspecialchars($row['bus_type']) ?></td>
                        <td><?= (int)$row['seats'] ?></td>
                        <td>
                            <?php if ($row['status'] === 'approved'): ?>
                                <span class="approved">Approved</span>
                            <?php elseif ($row['status'] === 'pending'): ?>
                                <span class="pending">Pending</span>
                            <?php else: ?>
                                <span class="rejected">Rejected</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($isVerified): ?>
                                <a href="edit_bus.php?id=<?= $row['bus_id'] ?>" class="edit-btn">Edit</a>
                                <a href="delete_bus.php?id=<?= $row['bus_id'] ?>" class="delete-btn" onclick="return confirm('Delete this bus?')">Delete</a>
                            <?php else: ?>
                                <button class="edit-btn" disabled>Locked</button>
                                <button class="delete-btn" disabled>Locked</button>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($row['status'] === 'rejected'): ?>
                                <a href="reject_discription.php?id=<?= $row['bus_id'] ?>" class="view-btn">View Reason</a>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="9">No Bus Found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</section>

<?php include "footer.php"; ?>