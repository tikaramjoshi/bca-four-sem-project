<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'owner') {
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
$totalBus = 0;
$approvedBus = 0;
$pendingBus = 0;
$rejectedBus = 0;
foreach ($buses as $bus) {
    $totalBus++;
    if ($bus['status'] === 'approved') {
        $approvedBus++;
    } elseif ($bus['status'] === 'pending') {
        $pendingBus++;
    } elseif ($bus['status'] === 'rejected') {
        $rejectedBus++;
    }
}
$driverData = [];
if (!empty($buses)) {
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
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bus</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, Helvetica, sans-serif
        }

        body {
            background: #f4f6f9;
            color: #222
        }

        .page {
            width: 94%;
            max-width: 1400px;
            margin: 30px auto
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 25px
        }

        .page-header h1 {
            font-size: 28px;
            margin-bottom: 6px
        }

        .page-header p {
            font-size: 14px;
            color: #777
        }

        .back-btn {
            background: #1560bd;
            color: #fff;
            text-decoration: none;
            padding: 11px 18px;
            border-radius: 7px;
            font-size: 14px
        }

        .back-btn:hover {
            background: #0d4f9c
        }

        .message {
            padding: 14px 17px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px
        }

        .message.success {
            background: #d1e7dd;
            color: #0f5132
        }

        .message.error {
            background: #f8d7da;
            color: #842029
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin-bottom: 25px
        }

        .stat-card {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 3px 12px rgba(0, 0, 0, .06)
        }

        .stat-title {
            font-size: 13px;
            color: #777;
            margin-bottom: 9px
        }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: #1560bd
        }

        .search-box {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 3px 12px rgba(0, 0, 0, .06);
            margin-bottom: 25px
        }

        .search-form {
            display: flex;
            gap: 12px
        }

        .search-input {
            height: 44px;
            flex: 1;
            border: 1px solid #ddd;
            border-radius: 7px;
            padding: 0 13px;
            font-size: 14px;
            outline: none
        }

        .search-input:focus {
            border-color: #1560bd
        }

        .search-btn {
            height: 44px;
            padding: 0 24px;
            border: 0;
            border-radius: 7px;
            background: #1560bd;
            color: #fff;
            cursor: pointer;
            font-size: 14px
        }

        .search-btn:hover {
            background: #0d4f9c
        }

        .clear-btn {
            height: 44px;
            padding: 0 22px;
            border-radius: 7px;
            background: #6c757d;
            color: #fff;
            text-decoration: none;
            display: flex;
            align-items: center
        }

        .clear-btn:hover {
            background: #565e64
        }

        .table-box {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(0, 0, 0, .06);
            overflow: hidden
        }

        .table-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center
        }

        .table-header h2 {
            font-size: 20px
        }

        .table-header span {
            font-size: 13px;
            color: #777
        }

        .table-wrapper {
            overflow-x: auto
        }

        table {
            width: 100%;
            min-width: 1250px;
            border-collapse: collapse
        }

        th {
            background: #1560bd;
            color: #fff;
            padding: 14px;
            text-align: left;
            font-size: 13px
        }

        td {
            padding: 14px;
            border-bottom: 1px solid #eee;
            font-size: 14px;
            vertical-align: middle
        }

        tr:hover td {
            background: #fafcff
        }

        .bus-image {
            width: 90px;
            height: 60px;
            object-fit: cover;
            border-radius: 7px;
            border: 1px solid #ddd
        }

        .bus-number {
            font-weight: 700;
            color: #1560bd
        }

        .bus-name {
            font-size: 12px;
            color: #777;
            margin-top: 4px
        }

        .badge {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700
        }

        .approved {
            background: #d1e7dd;
            color: #0f5132
        }

        .pending {
            background: #fff3cd;
            color: #856404
        }

        .rejected {
            background: #f8d7da;
            color: #842029
        }

        .driver-count {
            background: #cff4fc;
            color: #055160;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700
        }

        .driver-list {
            display: flex;
            flex-direction: column;
            gap: 7px;
            min-width: 180px
        }

        .driver-item {
            display: flex;
            align-items: center;
            gap: 8px
        }

        .driver-img {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            object-fit: cover;
            border: 1px solid #ddd
        }

        .driver-name {
            font-size: 13px;
            font-weight: 600
        }

        .driver-phone {
            font-size: 11px;
            color: #888
        }

        .no-driver {
            color: #999;
            font-size: 12px
        }

        .actions {
            display: flex;
            gap: 7px;
            flex-wrap: wrap
        }

        .action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 34px;
            padding: 0 11px;
            border-radius: 6px;
            text-decoration: none;
            border: 0;
            font-size: 12px;
            cursor: pointer
        }

        .edit-btn {
            background: #1560bd;
            color: #fff
        }

        .edit-btn:hover {
            background: #0d4f9c
        }

        .delete-btn {
            background: #dc3545;
            color: #fff
        }

        .delete-btn:hover {
            background: #b02a37
        }

        .view-btn {
            background: #198754;
            color: #fff
        }

        .view-btn:hover {
            background: #146c43
        }

        .empty {
            text-align: center;
            padding: 60px 20px;
            color: #777
        }

        .empty-icon {
            font-size: 45px;
            margin-bottom: 12px
        }

        .empty h3 {
            color: #444;
            margin-bottom: 7px
        }

        .empty p {
            font-size: 14px
        }

        .add-btn {
            background: #198754;
            color: #fff;
            text-decoration: none;
            padding: 11px 18px;
            border-radius: 7px;
            font-size: 14px
        }

        .add-btn:hover {
            background: #146c43
        }

        .header-buttons {
            display: flex;
            gap: 10px;
            align-items: center
        }

        @media(max-width:1000px) {
            .stats {
                grid-template-columns: repeat(2, 1fr)
            }

            .search-form {
                flex-wrap: wrap
            }

            .search-input {
                min-width: 250px
            }
        }

        @media(max-width:650px) {
            .page {
                width: 94%;
                margin: 20px auto
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start
            }

            .header-buttons {
                width: 100%;
                flex-wrap: wrap
            }

            .stats {
                grid-template-columns: 1fr
            }

            .search-form {
                flex-direction: column
            }

            .search-input,
            .search-btn,
            .clear-btn {
                width: 100%
            }
        }
    </style>
</head>

<body>
    <div class="page">
        <div class="page-header">
            <div>
                <h1>My Bus</h1>
                <p>View and manage all buses registered by you.</p>
            </div>
            <div class="header-buttons">
                <a href="register_bus.php" class="add-btn"><i class="fa fa-plus"></i> Add Bus</a>
                <a href="dashboard.php" class="back-btn">← Dashboard</a>
            </div>
        </div>
        <?php if ($message !== ""): ?>
            <div class="message <?= htmlspecialchars($message_type) ?>"><?= htmlspecialchars($message) ?></div>
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
                <input type="text" name="search" class="search-input" placeholder="Search bus number, bus name or bus type..." value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="search-btn"><i class="fa fa-search"></i> Search</button>
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
                                            <img src="../uploads/<?= htmlspecialchars($bus['bus_image']) ?>" class="bus-image" alt="Bus">
                                        <?php else: ?>
                                            <img src="../images/no-image.png" class="bus-image" alt="No Image">
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="bus-number"><?= htmlspecialchars($bus['bus_number']) ?></div>
                                        <div class="bus-name"><?= htmlspecialchars($bus['bus_name']) ?></div>
                                    </td>
                                    <td><?= htmlspecialchars($bus['bus_type']) ?></td>
                                    <td><?= htmlspecialchars($bus['seats']) ?></td>
                                    <td>
                                        <?php if ($status === 'approved'): ?>
                                            <span class="badge approved">Approved</span>
                                        <?php elseif ($status === 'pending'): ?>
                                            <span class="badge pending">Pending</span>
                                        <?php else: ?>
                                            <span class="badge rejected">Rejected</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($driversForBus)): ?>
                                            <div class="driver-list">
                                                <div>
                                                    <span class="driver-count"><?= count($driversForBus) ?> Driver<?= count($driversForBus) > 1 ? 's' : '' ?></span>
                                                </div>
                                                <?php foreach ($driversForBus as $driver): ?>
                                                    <?php $driverImage = !empty($driver['profile_image']) ? $driver['profile_image'] : 'default.png'; ?>
                                                    <div class="driver-item">
                                                        <img src="../uploads/profile/<?= htmlspecialchars($driverImage) ?>" class="driver-img" alt="Driver" onerror="this.onerror=null;this.src='../images/default.png';">
                                                        <div>
                                                            <div class="driver-name"><?= htmlspecialchars($driver['name']) ?></div>
                                                            <div class="driver-phone"><?= htmlspecialchars($driver['phone']) ?></div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="no-driver">No driver assigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <a href="edit_bus.php?id=<?= $bus_id ?>" class="action-btn edit-btn">Edit</a>
                                            <a href="delete_bus.php?id=<?= $bus_id ?>" class="action-btn delete-btn" onclick="return confirm('Are you sure you want to delete this bus?')">Delete</a>
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
                    <p><?= $search !== "" ? 'No bus matched your search.' : 'You have not registered any bus yet.' ?></p>
                    <?php if ($search === ""): ?>
                        <br>
                        <a href="register_bus.php" class="add-btn">Add Your First Bus</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>