<?php
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'owner') {
    header("Location: ../login.php");
    exit();
}
require_once "../db.php";
$owner_id = (int)$_SESSION['user_id'];
$message = "";
$message_type = "";

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'deleted') {
        $message = "Bus deleted successfully.";
        $message_type = "success";
    } elseif ($_GET['msg'] === 'error') {
        $message = "Something went wrong.";
        $message_type = "error";
    }
}

$search = isset($_GET['search']) ? trim($_GET['search']) : "";

$sql = "
SELECT
    b.bus_id,
    b.bus_number,
    b.bus_name,
    b.bus_type,
    b.seats,
    b.bus_image,
    b.status,
    b.owner_id,
    COUNT(bd.bus_driver_id) AS driver_count
FROM bus b
LEFT JOIN bus_driver bd ON b.bus_id = bd.bus_id
WHERE b.owner_id = ?
";

if ($search !== "") {
    $sql .= " AND (b.bus_number LIKE ? OR b.bus_name LIKE ? OR b.bus_type LIKE ?)";
}

$sql .= " GROUP BY b.bus_id ORDER BY b.bus_id DESC";

$stmt = $conn->prepare($sql);

if ($search !== "") {
    $like = "%" . $search . "%";
    $stmt->bind_param("isss", $owner_id, $like, $like, $like);
} else {
    $stmt->bind_param("i", $owner_id);
}

$stmt->execute();
$result = $stmt->get_result();
$buses = [];

while ($row = $result->fetch_assoc()) {
    $buses[] = $row;
}

$stmt->close();

$totalBus = count($buses);
$approvedBus = 0;
$pendingBus = 0;
$rejectedBus = 0;

foreach ($buses as $bus) {
    if ($bus['status'] === 'approved') {
        $approvedBus++;
    } elseif ($bus['status'] === 'pending') {
        $pendingBus++;
    } elseif ($bus['status'] === 'rejected') {
        $rejectedBus++;
    }
}

$driverData = [];

foreach ($buses as $bus) {
    $bus_id = (int)$bus['bus_id'];

    $driverStmt = $conn->prepare("
        SELECT
            u.user_id,
            u.name,
            u.phone,
            u.email,
            u.profile_image
        FROM bus_driver bd
        INNER JOIN users u ON bd.driver_id = u.user_id
        WHERE bd.bus_id = ?
        ORDER BY bd.bus_driver_id ASC
    ");

    $driverStmt->bind_param("i", $bus_id);
    $driverStmt->execute();
    $driverResult = $driverStmt->get_result();
    $driverData[$bus_id] = [];

    while ($driver = $driverResult->fetch_assoc()) {
        $driverData[$bus_id][] = $driver;
    }

    $driverStmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bus</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="my_bus.css">
</head>

<body>
    <div class="page">
        <div class="page-header">
            <div>
                <h1>My Bus</h1>
                <p>View and manage all buses registered by you.</p>
            </div>

            <div class="header-buttons">
                <a href="register_bus.php" class="add-btn">
                    <i class="fa fa-plus"></i> Add Bus
                </a>
                <a href="dashboard.php" class="back-btn">Home</a>
            </div>
        </div>

        <?php if ($message !== ""): ?>
            <div class="message <?= htmlspecialchars($message_type) ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="stats">
            <div class="stat-card">
                <div class="stat-title">Total Bus</div>
                <div class="stat-value"><?= $totalBus ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-title">Approved Bus</div>
                <div class="stat-value"><?= $approvedBus ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-title">Pending Bus</div>
                <div class="stat-value"><?= $pendingBus ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-title">Rejected Bus</div>
                <div class="stat-value"><?= $rejectedBus ?></div>
            </div>
        </div>

        <div class="search-box">
            <form method="GET" class="search-form">
                <input
                    type="text"
                    name="search"
                    class="search-input"
                    placeholder="Search bus number, bus name or bus type..."
                    value="<?= htmlspecialchars($search) ?>">

                <button type="submit" class="search-btn">
                    <i class="fa fa-search"></i> Search
                </button>

                <a href="my_bus.php" class="clear-btn">Clear</a>
            </form>
        </div>

        <div class="table-box">
            <div class="table-header">
                <h2>My Bus List</h2>
                <span><?= $totalBus ?> bus(es)</span>
            </div>

            <?php if (!empty($buses)): ?>

                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Bus Image</th>
                                <th>Bus Information</th>
                                <th>Bus Type</th>
                                <th>Seats</th>
                                <th>Status</th>
                                <th>Assigned Drivers</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($buses as $bus): ?>

                                <?php
                                $bus_id = (int)$bus['bus_id'];
                                $status = $bus['status'];
                                $driversForBus = $driverData[$bus_id] ?? [];
                                ?>

                                <tr>
                                    <td><?= $bus_id ?></td>

                                    <td>
                                        <?php if (!empty($bus['bus_image'])): ?>
                                            <img
                                                src="../uploads/bus/<?= htmlspecialchars($bus['bus_image']) ?>"
                                                class="bus-image"
                                                alt="Bus"
                                                onerror="this.onerror=null;this.src='../images/no-image.png';">
                                        <?php else: ?>
                                            <img
                                                src="../images/no-image.png"
                                                class="bus-image"
                                                alt="No Image">
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <div class="bus-number">
                                            <?= htmlspecialchars($bus['bus_number']) ?>
                                        </div>

                                        <div class="bus-name">
                                            <?= htmlspecialchars($bus['bus_name']) ?>
                                        </div>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($bus['bus_type']) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($bus['seats']) ?>
                                    </td>

                                    <td>
                                        <?php if ($status === 'approved'): ?>

                                            <span class="badge approved">
                                                Approved
                                            </span>

                                        <?php elseif ($status === 'pending'): ?>

                                            <span class="badge pending">
                                                Pending
                                            </span>

                                        <?php elseif ($status === 'rejected'): ?>

                                            <span class="badge rejected">
                                                Rejected
                                            </span>

                                        <?php else: ?>

                                            <span class="badge">
                                                <?= htmlspecialchars($status) ?>
                                            </span>

                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <?php if (!empty($driversForBus)): ?>

                                            <div class="driver-list">

                                                <div>
                                                    <span class="driver-count">
                                                        <?= count($driversForBus) ?>
                                                        Driver<?= count($driversForBus) > 1 ? 's' : '' ?>
                                                    </span>
                                                </div>

                                                <?php foreach ($driversForBus as $driver): ?>

                                                    <?php
                                                    $driverImage = !empty($driver['profile_image'])
                                                        ? $driver['profile_image']
                                                        : 'default.png';
                                                    ?>

                                                    <div class="driver-item">

                                                        <img
                                                            src="../uploads/profile/<?= htmlspecialchars($driverImage) ?>"
                                                            class="driver-img"
                                                            alt="Driver"
                                                            onerror="this.onerror=null;this.src='../images/default.png';">

                                                        <div>
                                                            <div class="driver-name">
                                                                <?= htmlspecialchars($driver['name']) ?>
                                                            </div>

                                                            <div class="driver-phone">
                                                                <?= htmlspecialchars($driver['phone']) ?>
                                                            </div>
                                                        </div>

                                                    </div>

                                                <?php endforeach; ?>

                                            </div>

                                        <?php else: ?>

                                            <span class="no-driver">
                                                No driver assigned
                                            </span>

                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <div class="actions">

                                            <a
                                                href="edit_bus.php?id=<?= $bus_id ?>"
                                                class="action-btn edit-btn">
                                                Edit
                                            </a>

                                            <a
                                                href="delete_bus.php?id=<?= $bus_id ?>"
                                                class="action-btn delete-btn"
                                                onclick="return confirm('Are you sure you want to delete this bus?')">
                                                Delete
                                            </a>

                                        </div>
                                    </td>
                                </tr>

                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php else: ?>

                <div class="empty">
                    <div class="empty-icon">🚌</div>

                    <h3>No Bus Found</h3>

                    <p>
                        <?= $search !== ""
                            ? "No bus matched your search."
                            : "You have not registered any bus yet."
                        ?>
                    </p>

                    <?php if ($search === ""): ?>
                        <br>
                        <a href="register_bus.php" class="add-btn">
                            Add Your First Bus
                        </a>
                    <?php endif; ?>
                </div>

            <?php endif; ?>
        </div>
    </div>
</body>

</html>