<?php
session_start();
require_once "../db.php";
if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    header("Location: ../login.php");
    exit;
}
$user_id = (int)$_SESSION['user_id'];
$role = $_SESSION['role'];
if (!in_array($role, ['owner', 'admin'], true)) {
    header("Location: ../login.php");
    exit;
}
$message = "";
$message_type = "";
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'assigned') {
        $message = "Driver assigned successfully.";
        $message_type = "success";
    } elseif ($_GET['msg'] === 'updated') {
        $message = "Driver assignment updated successfully.";
        $message_type = "success";
    } elseif ($_GET['msg'] === 'deleted') {
        $message = "Driver assignment deleted successfully.";
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
            $message = "Please select a bus and driver.";
            $message_type = "error";
        } else {
            if ($role === 'owner') {
                $stmt = $conn->prepare("SELECT bus_id FROM bus WHERE bus_id = ? AND owner_id = ? AND status = 'approved' LIMIT 1");
                $stmt->bind_param("ii", $bus_id, $user_id);
            } else {
                $stmt = $conn->prepare("SELECT bus_id FROM bus WHERE bus_id = ? AND status = 'approved' LIMIT 1");
                $stmt->bind_param("i", $bus_id);
            }
            $stmt->execute();
            $bus_result = $stmt->get_result();
            if ($bus_result->num_rows === 0) {
                $message = "You cannot assign a driver to this bus.";
                $message_type = "error";
            } else {
                $stmt = $conn->prepare("SELECT user_id FROM users WHERE user_id = ? AND role = 'driver' AND verification_status = 'verified' LIMIT 1");
                $stmt->bind_param("i", $driver_id);
                $stmt->execute();
                $driver_result = $stmt->get_result();
                if ($driver_result->num_rows === 0) {
                    $message = "Only verified drivers can be assigned.";
                    $message_type = "error";
                } else {
                    $stmt = $conn->prepare("SELECT bus_driver_id FROM bus_driver WHERE driver_id = ? LIMIT 1");
                    $stmt->bind_param("i", $driver_id);
                    $stmt->execute();
                    $existing = $stmt->get_result();
                    if ($existing->num_rows > 0) {
                        $message = "This driver is already assigned to a bus.";
                        $message_type = "error";
                    } else {
                        $stmt = $conn->prepare("INSERT INTO bus_driver (bus_id, driver_id) VALUES (?, ?)");
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
    if ($role === 'admin' && isset($_POST['update_assignment'])) {
        $assignment_id = (int)($_POST['assignment_id'] ?? 0);
        $bus_id = (int)($_POST['bus_id'] ?? 0);
        $driver_id = (int)($_POST['driver_id'] ?? 0);
        if ($assignment_id <= 0 || $bus_id <= 0 || $driver_id <= 0) {
            $message = "Invalid assignment information.";
            $message_type = "error";
        } else {
            $stmt = $conn->prepare("SELECT bus_id FROM bus WHERE bus_id = ? AND status = 'approved' LIMIT 1");
            $stmt->bind_param("i", $bus_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows === 0) {
                $message = "Selected bus is not approved.";
                $message_type = "error";
            } else {
                $stmt = $conn->prepare("SELECT user_id FROM users WHERE user_id = ? AND role = 'driver' AND verification_status = 'verified' LIMIT 1");
                $stmt->bind_param("i", $driver_id);
                $stmt->execute();
                if ($stmt->get_result()->num_rows === 0) {
                    $message = "Selected driver is not verified.";
                    $message_type = "error";
                } else {
                    $stmt = $conn->prepare("SELECT bus_driver_id FROM bus_driver WHERE driver_id = ? AND bus_driver_id != ? LIMIT 1");
                    $stmt->bind_param("ii", $driver_id, $assignment_id);
                    $stmt->execute();
                    if ($stmt->get_result()->num_rows > 0) {
                        $message = "This driver is already assigned to another bus.";
                        $message_type = "error";
                    } else {
                        $stmt = $conn->prepare("UPDATE bus_driver SET bus_id = ?, driver_id = ? WHERE bus_driver_id = ?");
                        $stmt->bind_param("iii", $bus_id, $driver_id, $assignment_id);
                        if ($stmt->execute()) {
                            header("Location: assign_driver.php?msg=updated");
                            exit;
                        }
                        $message = "Unable to update assignment.";
                        $message_type = "error";
                    }
                }
            }
        }
    }
    if (isset($_POST['delete_assignment'])) {
        $assignment_id = (int)($_POST['assignment_id'] ?? 0);
        if ($assignment_id <= 0) {
            $message = "Invalid assignment.";
            $message_type = "error";
        } else {
            if ($role === 'owner') {
                $stmt = $conn->prepare("
                SELECT bd.bus_driver_id
                FROM bus_driver bd
                INNER JOIN bus b ON bd.bus_id = b.bus_id
                WHERE bd.bus_driver_id = ?
                AND b.owner_id = ?
                LIMIT 1
            ");
                $stmt->bind_param("ii", $assignment_id, $user_id);
            } else {
                $stmt = $conn->prepare("
                SELECT bus_driver_id
                FROM bus_driver
                WHERE bus_driver_id = ?
                LIMIT 1
            ");
                $stmt->bind_param("i", $assignment_id);
            }
            $stmt->execute();
            $check = $stmt->get_result();
            if ($check->num_rows === 0) {
                $message = "You cannot remove this driver.";
                $message_type = "error";
            } else {
                $stmt = $conn->prepare("DELETE FROM bus_driver WHERE bus_driver_id = ?");
                $stmt->bind_param("i", $assignment_id);
                if ($stmt->execute()) {
                    header("Location: assign_driver.php?msg=deleted");
                    exit;
                }
                $message = "Unable to remove driver.";
                $message_type = "error";
            }
        }
    }
}
$buses = [];
if ($role === 'owner') {
    $stmt = $conn->prepare("SELECT bus_id,bus_number,bus_name,bus_type FROM bus WHERE owner_id = ? AND status = 'approved' ORDER BY bus_number ASC");
    $stmt->bind_param("i", $user_id);
} else {
    $stmt = $conn->prepare("SELECT bus_id,bus_number,bus_name,bus_type FROM bus WHERE status = 'approved' ORDER BY bus_number ASC");
}
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $buses[] = $row;
}
$drivers = [];
$stmt = $conn->prepare("SELECT u.user_id,u.name,u.email,u.phone,u.profile_image FROM users u WHERE u.role = 'driver' AND u.verification_status = 'verified' AND NOT EXISTS (SELECT 1 FROM bus_driver bd WHERE bd.driver_id = u.user_id) ORDER BY u.name ASC");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $drivers[] = $row;
}
$sql = "SELECT bd.bus_driver_id,bd.bus_id,bd.driver_id,bd.assigned_at,b.bus_number,b.bus_name,b.bus_type,u.name AS driver_name,u.email AS driver_email,u.phone AS driver_phone,u.profile_image FROM bus_driver bd INNER JOIN bus b ON bd.bus_id=b.bus_id INNER JOIN users u ON bd.driver_id=u.user_id";
$params = [];
$types = "";
if ($role === 'owner') {
    $sql .= " WHERE b.owner_id = ?";
    $params[] = $user_id;
    $types .= "i";
}
$sql .= " ORDER BY bd.bus_driver_id DESC";
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$assignments = [];
while ($row = $result->fetch_assoc()) {
    $assignments[] = $row;
}
$total_assignments = count($assignments);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Driver Assignment</title>
    <link rel="stylesheet" href="assign_driver.css">
</head>

<body>
    <div class="page">
        <div class="header">
            <div>
                <h1>Driver Assignment</h1>
                <p><?= $role === 'owner' ? 'Assign verified drivers to your approved buses.' : 'Manage all driver and bus assignments.' ?></p>
            </div>
            <a href="<?= $role === 'owner' ? 'dashboard.php' : 'dashboard.php' ?>" class="back">← Dashboard</a>
        </div>
        <?php if ($message !== ""): ?>
            <div class="message <?= htmlspecialchars($message_type) ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <?php if ($role === 'owner'): ?>
            <div class="card">
                <h2>Assign Driver to Bus</h2>
                <p>You can assign multiple drivers to the same bus.</p>
                <?php if (!empty($buses) && !empty($drivers)): ?>
                    <form method="POST" class="form" onsubmit="return confirm('Assign this driver to this bus?');">
                        <div>
                            <label>Approved Bus</label>
                            <select name="bus_id" required>
                                <option value="">Select Bus</option>
                                <?php foreach ($buses as $bus): ?>
                                    <option value="<?= (int)$bus['bus_id'] ?>"><?= htmlspecialchars($bus['bus_number']) ?> - <?= htmlspecialchars($bus['bus_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label>Verified Driver</label>
                            <select name="driver_id" required>
                                <option value="">Select Driver</option>
                                <?php foreach ($drivers as $driver): ?>
                                    <option value="<?= (int)$driver['user_id'] ?>"><?= htmlspecialchars($driver['name']) ?> - <?= htmlspecialchars($driver['phone']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" name="assign_driver" class="assign">✓ Assign Driver</button>
                    </form>
                <?php elseif (empty($buses)): ?>
                    <div class="empty">
                        <h3>No Approved Bus</h3>
                        <p>You need an approved bus before assigning a driver.</p>
                    </div>
                <?php else: ?>
                    <div class="empty">
                        <h3>No Available Verified Driver</h3>
                        <p>All verified drivers are already assigned.</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <div class="table-card">
            <div class="table-head">
                <h2><?= $role === 'owner' ? 'My Driver Assignments' : 'All Driver Assignments' ?></h2>
                <span><?= $total_assignments ?> Assignment(s)</span>
            </div>
            <?php if (!empty($assignments)): ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Driver</th>
                                <th>Phone</th>
                                <th>Bus</th>
                                <th>Bus Type</th>
                                <th>Assigned At</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($assignments as $assignment): ?>
                                <?php
                                $image = !empty($assignment['profile_image']) ? basename($assignment['profile_image']) : 'default.png';
                                $image_url = "../uploads/profile/" . $image;
                                ?>
                                <tr>
                                    <td>
                                        <div class="driver">
                                            <img src="<?= htmlspecialchars($image_url) ?>" alt="Driver" onerror="this.onerror=null;this.src='../uploads/profile/default.png';">
                                            <div>
                                                <div class="driver-name"><?= htmlspecialchars($assignment['driver_name']) ?></div>
                                                <div class="email"><?= htmlspecialchars($assignment['driver_email']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($assignment['driver_phone']) ?></td>
                                    <td>
                                        <div class="bus"><?= htmlspecialchars($assignment['bus_number']) ?></div>
                                        <div class="bus-name"><?= htmlspecialchars($assignment['bus_name']) ?></div>
                                    </td>
                                    <td><?= htmlspecialchars($assignment['bus_type']) ?></td>
                                    <td><?= !empty($assignment['assigned_at']) ? date("d M Y h:i A", strtotime($assignment['assigned_at'])) : "-" ?></td>
                                    <td><span class="badge verified">✓ Assigned</span></td>
                                    <td>
                                        <div class="actions">
                                            <?php if ($role === 'admin'): ?>
                                                <button type="button" class="edit" onclick="openEdit(<?= (int)$assignment['bus_driver_id'] ?>,<?= (int)$assignment['bus_id'] ?>,<?= (int)$assignment['driver_id'] ?>)">
                                                    Edit
                                                </button>
                                            <?php endif; ?>
                                            <form method="POST" onsubmit="return confirm('Are you sure you want to remove this driver from this bus?');">
                                                <input type="hidden" name="assignment_id" value="<?= (int)$assignment['bus_driver_id'] ?>">
                                                <button type="submit" name="delete_assignment" class="delete">
                                                    Remove
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php if ($role === 'admin'): ?>
                                    <tr id="edit-<?= (int)$assignment['bus_driver_id'] ?>" style="display:none;background:#f7f9fc">
                                        <td colspan="7">
                                            <form method="POST" class="edit-form">
                                                <input type="hidden" name="assignment_id" value="<?= (int)$assignment['bus_driver_id'] ?>">
                                                <select name="bus_id" required>
                                                    <?php foreach ($buses as $bus): ?>
                                                        <option value="<?= (int)$bus['bus_id'] ?>" <?= (int)$bus['bus_id'] === (int)$assignment['bus_id'] ? 'selected' : '' ?>><?= htmlspecialchars($bus['bus_number']) ?> - <?= htmlspecialchars($bus['bus_name']) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <select name="driver_id" required>
                                                    <option value="<?= (int)$assignment['driver_id'] ?>" selected><?= htmlspecialchars($assignment['driver_name']) ?> - Current Driver</option>
                                                    <?php
                                                    $stmt2 = $conn->prepare("SELECT u.user_id,u.name,u.phone FROM users u WHERE u.role='driver' AND u.verification_status='verified' AND (NOT EXISTS (SELECT 1 FROM bus_driver bd WHERE bd.driver_id=u.user_id) OR u.user_id=?) ORDER BY u.name ASC");
                                                    $stmt2->bind_param("i", $assignment['driver_id']);
                                                    $stmt2->execute();
                                                    $edit_drivers = $stmt2->get_result();
                                                    while ($ed = $edit_drivers->fetch_assoc()):
                                                    ?>
                                                        <?php if ((int)$ed['user_id'] !== (int)$assignment['driver_id']): ?>
                                                            <option value="<?= (int)$ed['user_id'] ?>"><?= htmlspecialchars($ed['name']) ?> - <?= htmlspecialchars($ed['phone']) ?></option>
                                                        <?php endif; ?>
                                                    <?php endwhile; ?>
                                                </select>
                                                <button type="submit" name="update_assignment" class="edit small-btn">Save</button>
                                                <button type="button" class="delete small-btn" onclick="closeEdit(<?= (int)$assignment['bus_driver_id'] ?>)">Cancel</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty">
                    <h3>No Driver Assignments</h3>
                    <p>No driver has been assigned to a bus yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <script>
        function openEdit(id, bus, driver) {
            document.querySelectorAll('[id^="edit-"]').forEach(function(e) {
                e.style.display = 'none';
            });
            const row = document.getElementById('edit-' + id);
            if (row) {
                row.style.display = 'table-row';
            }
        }

        function closeEdit(id) {
            const row = document.getElementById('edit-' + id);
            if (row) {
                row.style.display = 'none';
            }
        }
    </script>
</body>

</html>