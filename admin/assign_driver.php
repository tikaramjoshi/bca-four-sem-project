<?php
session_start();
require_once "../db.php";
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../login.php");
    exit;
}
$message = "";
$message_type = "";
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'assigned') {
        $message = "Driver assigned to bus successfully.";
        $message_type = "success";
    } elseif ($_GET['msg'] === 'removed') {
        $message = "Driver assignment removed successfully.";
        $message_type = "success";
    } elseif ($_GET['msg'] === 'error') {
        $message = "Something went wrong.";
        $message_type = "error";
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['assign_driver'])) {
        $bus_id = (int)($_POST['bus_id'] ?? 0);
        $driver_id = (int)($_POST['driver_id'] ?? 0);
        if ($bus_id <= 0 || $driver_id <= 0) {
            $message = "Please select a valid bus and driver.";
            $message_type = "error";
        } else {
            $stmt = $conn->prepare("SELECT bus_id FROM bus WHERE bus_id=? AND status='approved' LIMIT 1");
            $stmt->bind_param("i", $bus_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows == 0) {
                $message = "Selected bus is not approved.";
                $message_type = "error";
            } else {
                $stmt = $conn->prepare("SELECT user_id FROM users WHERE user_id=? AND role='driver' AND verification_status='verified' LIMIT 1");
                $stmt->bind_param("i", $driver_id);
                $stmt->execute();
                if ($stmt->get_result()->num_rows == 0) {
                    $message = "Only verified drivers can be assigned.";
                    $message_type = "error";
                } else {
                    $stmt = $conn->prepare("SELECT bus_id FROM bus_driver WHERE driver_id=? LIMIT 1");
                    $stmt->bind_param("i", $driver_id);
                    $stmt->execute();
                    if ($stmt->get_result()->num_rows > 0) {
                        $message = "This driver is already assigned to a bus.";
                        $message_type = "error";
                    } else {
                        $stmt = $conn->prepare("SELECT driver_id FROM bus_driver WHERE bus_id=? LIMIT 1");
                        $stmt->bind_param("i", $bus_id);
                        $stmt->execute();
                        if ($stmt->get_result()->num_rows > 0) {
                            $message = "This bus already has a driver.";
                            $message_type = "error";
                        } else {
                            $stmt = $conn->prepare("INSERT INTO bus_driver (bus_id,driver_id) VALUES (?,?)");
                            $stmt->bind_param("ii", $bus_id, $driver_id);
                            if ($stmt->execute()) {
                                header("Location: assign_driver.php?msg=assigned");
                                exit;
                            }
                            $message = "Unable to assign driver.";
                            $message_type = "error";
                        }
                    }
                }
            }
        }
    }
    if (isset($_POST['remove_assignment'])) {
        $assignment_id = (int)($_POST['assignment_id'] ?? 0);
        if ($assignment_id > 0) {
            $stmt = $conn->prepare("DELETE FROM bus_driver WHERE bus_driver_id=?");
            $stmt->bind_param("i", $assignment_id);
            if ($stmt->execute()) {
                header("Location: assign_driver.php?msg=removed");
                exit;
            }
            $message = "Unable to remove assignment.";
            $message_type = "error";
        }
    }
}
$search = trim($_GET['search'] ?? '');
$bus_id_filter = (int)($_GET['bus_id'] ?? 0);
$driver_id_filter = (int)($_GET['driver_id'] ?? 0);
$buses = [];
$stmt = $conn->prepare("SELECT b.bus_id,b.bus_number,b.bus_name,b.bus_type,b.seats,b.status,u.name AS owner_name FROM bus b LEFT JOIN users u ON b.owner_id=u.user_id WHERE b.status='approved' ORDER BY b.bus_id DESC");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) $buses[] = $row;
$drivers = [];
$stmt = $conn->prepare("SELECT u.user_id,u.name,u.email,u.phone,u.profile_image,u.verification_status FROM users u WHERE u.role='driver' AND u.verification_status='verified' AND NOT EXISTS (SELECT 1 FROM bus_driver bd WHERE bd.driver_id=u.user_id) ORDER BY u.name ASC");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) $drivers[] = $row;
$sql = "SELECT bd.bus_driver_id,b.bus_id,b.bus_number,b.bus_name,b.bus_type,b.seats,u.user_id AS driver_id,u.name AS driver_name,u.email AS driver_email,u.phone AS driver_phone,u.profile_image,u.verification_status FROM bus_driver bd INNER JOIN bus b ON bd.bus_id=b.bus_id INNER JOIN users u ON bd.driver_id=u.user_id WHERE 1=1";
$params = [];
$types = "";
if ($search !== '') {
    $sql .= " AND (b.bus_number LIKE ? OR b.bus_name LIKE ? OR u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $value = "%$search%";
    $params = [$value, $value, $value, $value, $value];
    $types = "sssss";
}
if ($bus_id_filter > 0) {
    $sql .= " AND b.bus_id=?";
    $params[] = $bus_id_filter;
    $types .= "i";
}
if ($driver_id_filter > 0) {
    $sql .= " AND u.user_id=?";
    $params[] = $driver_id_filter;
    $types .= "i";
}
$sql .= " ORDER BY bd.bus_driver_id DESC";
$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$assignments = [];
while ($row = $result->fetch_assoc()) $assignments[] = $row;
$total_buses = count($buses);
$total_available_drivers = count($drivers);
$total_assignments = count($assignments);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Assign Driver</title>
    <link rel="stylesheet" href="assign_driver.css">

</head>

<body>
    <div class="page">
        <div class="page-header">
            <div>
                <h1>Driver Assignment</h1>
                <p>Assign verified drivers to approved buses.</p>
            </div>
            <a href="dashboard.php" class="back-btn">← Dashboard</a>
        </div>
        <?php if ($message): ?><div class="message <?= htmlspecialchars($message_type) ?>"><?= htmlspecialchars($message) ?></div><?php endif; ?>
        <div class="stats">
            <div class="stat-card">
                <div class="stat-title">Approved Buses</div>
                <div class="stat-value"><?= $total_buses ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-title">Available Verified Drivers</div>
                <div class="stat-value"><?= $total_available_drivers ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-title">Current Assignments</div>
                <div class="stat-value"><?= $total_assignments ?></div>
            </div>
        </div>
        <div class="assign-box">
            <div class="box-title">
                <h2>Assign Driver to Bus</h2>
                <p>Only approved buses and verified available drivers are shown.</p>
            </div>
            <?php if ($buses && $drivers): ?>
                <form method="POST" onsubmit="return confirmAssignment()">
                    <div class="form-grid">
                        <div class="form-group"><label>Select Bus</label><select name="bus_id" required>
                                <option value="">Select approved bus</option><?php foreach ($buses as $bus): ?><option value="<?= $bus['bus_id'] ?>"><?= htmlspecialchars($bus['bus_number']) ?> - <?= htmlspecialchars($bus['bus_name']) ?> | <?= htmlspecialchars($bus['bus_type']) ?></option><?php endforeach; ?>
                            </select></div>
                        <div class="form-group"><label>Select Verified Driver</label><select name="driver_id" required>
                                <option value="">Select driver</option><?php foreach ($drivers as $driver): ?><option value="<?= $driver['user_id'] ?>"><?= htmlspecialchars($driver['name']) ?> - <?= htmlspecialchars($driver['phone']) ?></option><?php endforeach; ?>
                            </select></div>
                        <button type="submit" name="assign_driver" class="assign-btn">✓ Assign Driver</button>
                    </div>
                </form>
            <?php elseif (!$buses): ?>
                <div class="empty">
                    <div class="empty-icon">🚌</div>
                    <h3>No Approved Buses</h3>
                    <p>Approve a bus before assigning a driver.</p>
                </div>
            <?php else: ?>
                <div class="empty">
                    <div class="empty-icon">👤</div>
                    <h3>No Available Drivers</h3>
                    <p>You need a verified driver who is not already assigned.</p>
                </div>
            <?php endif; ?>
        </div>
        <div class="filter-box">
            <form method="GET" class="filter-form">
                <input type="text" name="search" class="filter-input" placeholder="Search driver, bus number, email or phone..." value="<?= htmlspecialchars($search) ?>">
                <select name="bus_id" class="filter-select">
                    <option value="0">All Buses</option><?php foreach ($buses as $bus): ?><option value="<?= $bus['bus_id'] ?>" <?= $bus_id_filter == $bus['bus_id'] ? 'selected' : '' ?>><?= htmlspecialchars($bus['bus_number']) ?></option><?php endforeach; ?>
                </select>
                <select name="driver_id" class="filter-select">
                    <option value="0">All Assigned Drivers</option>
                    <?php $assigned_driver_list = [];
                    foreach ($assignments as $assignment) $assigned_driver_list[$assignment['driver_id']] = $assignment['driver_name'];
                    foreach ($assigned_driver_list as $id => $name): ?>
                        <option value="<?= $id ?>" <?= $driver_id_filter == $id ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="filter-btn">Search</button>
                <a href="assign_driver.php" class="clear-btn">Clear</a>
            </form>
        </div>
        <div class="table-box">
            <div class="table-header">
                <h2>Current Driver Assignments</h2><span><?= $total_assignments ?> assignment(s)</span>
            </div>
            <?php if ($assignments): ?>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Driver</th>
                                <th>Phone</th>
                                <th>Assigned Bus</th>
                                <th>Bus Type</th>
                                <th>Verification</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($assignments as $assignment): ?>
                                <?php $image = $assignment['profile_image'] ?: 'default.png'; ?>
                                <tr>
                                    <td>
                                        <div class="driver-info"><img src="../uploads/profile/<?= htmlspecialchars($image) ?>" class="driver-image" alt="Driver" onerror="this.src='../images/default.png'">
                                            <div>
                                                <div class="driver-name"><?= htmlspecialchars($assignment['driver_name']) ?></div>
                                                <div class="driver-email"><?= htmlspecialchars($assignment['driver_email']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($assignment['driver_phone']) ?></td>
                                    <td>
                                        <div class="bus-number"><?= htmlspecialchars($assignment['bus_number']) ?></div>
                                        <div class="bus-name"><?= htmlspecialchars($assignment['bus_name']) ?></div>
                                    </td>
                                    <td><?= htmlspecialchars($assignment['bus_type']) ?></td>
                                    <td><span class="badge verified">✓ Verified</span></td>
                                    <td><span class="badge assigned">● Assigned</span></td>
                                    <td>
                                        <div class="actions"><a href="view_driver.php?id=<?= $assignment['driver_id'] ?>" class="action-btn view-btn">View Driver</a>
                                            <form method="POST" onsubmit="return confirmRemove()"><input type="hidden" name="assignment_id" value="<?= $assignment['bus_driver_id'] ?>"><button type="submit" name="remove_assignment" class="action-btn remove-btn">Remove</button></form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty">
                    <div class="empty-icon">🔗</div>
                    <h3>No Driver Assignments</h3>
                    <p>No driver has been assigned to a bus yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <script>
        function confirmAssignment() {
            return confirm("Are you sure you want to assign this driver to this bus?")
        }

        function confirmRemove() {
            return confirm("Are you sure you want to remove this driver assignment?")
        }
    </script>
</body>

</html>