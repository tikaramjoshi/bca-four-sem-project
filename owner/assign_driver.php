<?php
session_start();
require_once "../db.php";

if (!isset($_SESSION['user_id'], $_SESSION['role']) || !in_array($_SESSION['role'], ['owner', 'admin'], true)) {
    header("Location: ../login.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$role = $_SESSION['role'];
$message = "";
$message_type = "";

$messages = [
    'assigned' => 'Driver assigned successfully.',
    'updated' => 'Driver assignment updated successfully.',
    'deleted' => 'Driver assignment deleted successfully.',
    'error' => 'Something went wrong.'
];

if (isset($_GET['msg'], $messages[$_GET['msg']])) {
    $message = $messages[$_GET['msg']];
    $message_type = $_GET['msg'] === 'error' ? 'error' : 'success';
}

function fetchAllRows($stmt)
{
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bus_id = (int)($_POST['bus_id'] ?? 0);
    $driver_id = (int)($_POST['driver_id'] ?? 0);
    $assignment_id = (int)($_POST['assignment_id'] ?? 0);

    if (isset($_POST['assign_driver'])) {
        if (!$bus_id || !$driver_id) {
            $message = "Please select a bus and driver.";
            $message_type = "error";
        } else {
            $sql = "SELECT bus_id FROM bus WHERE bus_id=? AND status='approved'";
            if ($role === 'owner') $sql .= " AND owner_id=?";
            $sql .= " LIMIT 1";

            $stmt = $conn->prepare($sql);
            $role === 'owner'
                ? $stmt->bind_param("ii", $bus_id, $user_id)
                : $stmt->bind_param("i", $bus_id);
            $stmt->execute();

            if (!$stmt->get_result()->num_rows) {
                $message = "You cannot assign a driver to this bus.";
                $message_type = "error";
            } else {
                $stmt = $conn->prepare("SELECT user_id FROM users WHERE user_id=? AND role='driver' AND verification_status='verified'");
                $stmt->bind_param("i", $driver_id);
                $stmt->execute();

                if (!$stmt->get_result()->num_rows) {
                    $message = "Only verified drivers can be assigned.";
                    $message_type = "error";
                } else {
                    $stmt = $conn->prepare("SELECT bus_driver_id FROM bus_driver WHERE driver_id=?");
                    $stmt->bind_param("i", $driver_id);
                    $stmt->execute();

                    if ($stmt->get_result()->num_rows) {
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
        if (!$assignment_id || !$bus_id || !$driver_id) {
            $message = "Invalid assignment information.";
            $message_type = "error";
        } else {
            $stmt = $conn->prepare("SELECT bus_id FROM bus WHERE bus_id=? AND status='approved'");
            $stmt->bind_param("i", $bus_id);
            $stmt->execute();

            if (!$stmt->get_result()->num_rows) {
                $message = "Selected bus is not approved.";
                $message_type = "error";
            } else {
                $stmt = $conn->prepare("SELECT user_id FROM users WHERE user_id=? AND role='driver' AND verification_status='verified'");
                $stmt->bind_param("i", $driver_id);
                $stmt->execute();

                if (!$stmt->get_result()->num_rows) {
                    $message = "Selected driver is not verified.";
                    $message_type = "error";
                } else {
                    $stmt = $conn->prepare("SELECT bus_driver_id FROM bus_driver WHERE driver_id=? AND bus_driver_id!=?");
                    $stmt->bind_param("ii", $driver_id, $assignment_id);
                    $stmt->execute();

                    if ($stmt->get_result()->num_rows) {
                        $message = "This driver is already assigned to another bus.";
                        $message_type = "error";
                    } else {
                        $stmt = $conn->prepare("UPDATE bus_driver SET bus_id=?, driver_id=? WHERE bus_driver_id=?");
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
        if (!$assignment_id) {
            $message = "Invalid assignment.";
            $message_type = "error";
        } else {
            $sql = "SELECT bd.bus_driver_id FROM bus_driver bd INNER JOIN bus b ON bd.bus_id=b.bus_id WHERE bd.bus_driver_id=?";
            if ($role === 'owner') $sql .= " AND b.owner_id=?";
            $stmt = $conn->prepare($sql);
            $role === 'owner'
                ? $stmt->bind_param("ii", $assignment_id, $user_id)
                : $stmt->bind_param("i", $assignment_id);
            $stmt->execute();

            if (!$stmt->get_result()->num_rows) {
                $message = "You cannot remove this driver.";
                $message_type = "error";
            } else {
                $stmt = $conn->prepare("DELETE FROM bus_driver WHERE bus_driver_id=?");
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

$sql = "SELECT bus_id,bus_number,bus_name,bus_type FROM bus WHERE status='approved'";
if ($role === 'owner') $sql .= " AND owner_id=?";
$sql .= " ORDER BY bus_number ASC";

$stmt = $conn->prepare($sql);
if ($role === 'owner') $stmt->bind_param("i", $user_id);
$stmt->execute();
$buses = fetchAllRows($stmt);

$stmt = $conn->prepare(" SELECT u.user_id,u.name,u.email,u.phone,u.profile_image FROM users u WHERE u.role='driver' AND u.verification_status='verified' AND NOT EXISTS (SELECT 1 FROM bus_driver bd WHERE bd.driver_id=u.user_id) ORDER BY u.name ASC ");
$stmt->execute();
$drivers = fetchAllRows($stmt);

$sql = "SELECT bd.bus_driver_id,bd.bus_id,bd.driver_id,bd.assigned_at, b.bus_number,b.bus_name,b.bus_type,u.name AS driver_name, u.email AS driver_email,u.phone AS driver_phone,u.profile_image, (SELECT dv.profile_photo FROM driver_verification dv WHERE dv.driver_id=u.user_id ORDER BY dv.verification_id DESC LIMIT 1) AS profile_photo FROM bus_driver bd INNER JOIN bus b ON bd.bus_id=b.bus_id INNER JOIN users u ON bd.driver_id=u.user_id";

if ($role === 'owner') $sql .= " WHERE b.owner_id=?";
$sql .= " ORDER BY bd.bus_driver_id DESC";

$stmt = $conn->prepare($sql);
if ($role === 'owner') $stmt->bind_param("i", $user_id);
$stmt->execute();
$assignments = fetchAllRows($stmt);
$total_assignments = count($assignments);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Driver Assignment</title>
    <link rel="stylesheet" href="assign_driver.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
</head>

<body>
    <?php require_once "owner_header.php"; ?>

    <div class="page">
        <div class="header">
            <div>
                <h1>Driver Assignment</h1>
                <p><?= $role === 'owner' ? 'Assign verified drivers to your approved buses.' : 'Manage all driver and bus assignments.' ?></p>
            </div>
            <a href="dashboard.php" class="back"><i class="fa-solid fa-house"></i> Home</a>
        </div>

        <?php if ($message !== ""): ?>
            <div class="message <?= htmlspecialchars($message_type) ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <?php if ($role === 'owner'): ?>
            <div class="card">
                <h2> Assign Driver to Bus</h2>
                <p>You can assign multiple drivers to the same bus.</p>

                <?php if ($buses && $drivers): ?>
                    <form method="POST" class="form" onsubmit="return confirm('Assign this driver to this bus?');">
                        <div>
                            <label>Approved Bus</label>
                            <select name="bus_id" required>
                                <option value="">Select Bus</option>
                                <?php foreach ($buses as $bus): ?>
                                    <option value="<?= (int)$bus['bus_id'] ?>"><?= htmlspecialchars($bus['bus_number'] . ' - ' . $bus['bus_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label>Verified Driver</label>
                            <select name="driver_id" required>
                                <option value="">Select Driver</option>
                                <?php foreach ($drivers as $driver): ?>
                                    <option value="<?= (int)$driver['user_id'] ?>"><?= htmlspecialchars($driver['name'] . ' - ' . $driver['phone']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" name="assign_driver" class="assign"><i class="fa-solid fa-link"></i> Assign Driver</button>
                    </form>
                <?php elseif (!$buses): ?>
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
                <h2> <?= $role === 'owner' ? 'My Driver Assignments' : 'All Driver Assignments' ?></h2>
                <span><?= $total_assignments ?> Assignment(s)</span>
            </div>

            <?php if ($assignments): ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>S.No</th>
                                <th>Driver</th>
                                <th>Contact</th>
                                <th>Bus No</th>
                                <th>Bus Type</th>
                                <th>Assigned At</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $sn = 1;
                            foreach ($assignments as $a): ?>
                                <?php
                                if (!empty($a['profile_photo'])) {
                                    $image_url = "../uploads/driver/profile/" . rawurlencode(basename($a['profile_photo']));
                                } elseif (!empty($a['profile_image'])) {
                                    $image_url = "../uploads/profile/" . rawurlencode(basename($a['profile_image']));
                                } else {
                                    $image_url = "../uploads/profile/default.png";
                                }
                                ?>
                                <tr>
                                    <td><?= $sn++ ?></td>
                                    <td>
                                        <div class="driver">
                                            <img src="<?= htmlspecialchars($image_url) ?>" alt="Driver" onerror="this.onerror=null;this.src='../uploads/profile/default.png';">
                                            <div class="driver-name"><?= htmlspecialchars($a['driver_name']) ?></div>
                                        </div>
                                    </td>
                                    <td>Phone: <?= htmlspecialchars($a['driver_phone']) ?>
                                        <div class="email">Email: <?= htmlspecialchars($a['driver_email']) ?></div>
                                    </td>
                                    <td>
                                        <div class="bus"><?= htmlspecialchars($a['bus_number']) ?></div>
                                        <div class="bus-name"><?= htmlspecialchars($a['bus_name']) ?></div>
                                    </td>
                                    <td><?= htmlspecialchars($a['bus_type']) ?></td>
                                    <td><?= !empty($a['assigned_at']) ? date("d M Y h:i A", strtotime($a['assigned_at'])) : "-" ?></td>
                                    <td><span class="badge verifie">Assigned</span></td>
                                    <td>
                                        <div class="actions">
                                            <?php if ($role === 'admin'): ?>
                                                <button type="button" class="edit" onclick="openEdit(<?= (int)$a['bus_driver_id'] ?>)"><i class="fa-solid fa-pen-to-square"></i> Edit</button>
                                            <?php endif; ?>
                                            <form method="POST" onsubmit="return confirm('Remove this driver from this bus?');">
                                                <input type="hidden" name="assignment_id" value="<?= (int)$a['bus_driver_id'] ?>">
                                                <button type="submit" name="delete_assignment" class="delete"><i class="fa-solid fa-trash"></i> Remove</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>

                                <?php if ($role === 'admin'): ?>
                                    <tr id="edit-<?= (int)$a['bus_driver_id'] ?>" style="display:none;background:#f7f9fc">
                                        <td colspan="7">
                                            <form method="POST" class="edit-form">
                                                <input type="hidden" name="assignment_id" value="<?= (int)$a['bus_driver_id'] ?>">
                                                <select name="bus_id" required>
                                                    <?php foreach ($buses as $bus): ?>
                                                        <option value="<?= (int)$bus['bus_id'] ?>" <?= (int)$bus['bus_id'] === (int)$a['bus_id'] ? 'selected' : '' ?>><?= htmlspecialchars($bus['bus_number'] . ' - ' . $bus['bus_name']) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <select name="driver_id" required>
                                                    <?php
                                                    $stmt2 = $conn->prepare(" SELECT u.user_id,u.name,u.phone FROM users u WHERE u.role='driver' AND u.verification_status='verified' AND (NOT EXISTS (SELECT 1 FROM bus_driver bd WHERE bd.driver_id=u.user_id) OR u.user_id=?) ORDER BY u.name ASC ");
                                                    $stmt2->bind_param("i", $a['driver_id']);
                                                    $stmt2->execute();
                                                    foreach (fetchAllRows($stmt2) as $ed):
                                                    ?>
                                                        <option value="<?= (int)$ed['user_id'] ?>" <?= (int)$ed['user_id'] === (int)$a['driver_id'] ? 'selected' : '' ?>><?= htmlspecialchars($ed['name'] . ' - ' . $ed['phone']) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button type="submit" name="update_assignment" class="edit small-btn"><i class="fa-solid fa-floppy-disk"></i> Save</button>
                                                <button type="button" class="delete small-btn" onclick="closeEdit(<?= (int)$a['bus_driver_id'] ?>)"><i class="fa-solid fa-xmark"></i> Cancel</button>
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

    <?php require_once "owner_footer.php"; ?>

    <script>
        function openEdit(id) {
            document.querySelectorAll('[id^="edit-"]').forEach(e => e.style.display = 'none');
            const row = document.getElementById('edit-' + id);
            if (row) row.style.display = 'table-row';
        }

        function closeEdit(id) {
            const row = document.getElementById('edit-' + id);
            if (row) row.style.display = 'none';
        }
    </script>
</body>

</html>