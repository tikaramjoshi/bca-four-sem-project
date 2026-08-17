<?php
session_start();
require_once "../db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'owner') {
    header("Location: ../login.php");
    exit;
}

$owner_id = (int)$_SESSION['user_id'];
$message = "";
$message_type = "";

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'assigned') {
        $message = "Driver assigned successfully.";
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
        $bus_id = isset($_POST['bus_id']) ? (int)$_POST['bus_id'] : 0;
        $driver_id = isset($_POST['driver_id']) ? (int)$_POST['driver_id'] : 0;

        if ($bus_id <= 0 || $driver_id <= 0) {
            $message = "Please select a bus and driver.";
            $message_type = "error";
        } else {
            $stmt = $conn->prepare("
                SELECT bus_id
                FROM bus
                WHERE bus_id = ?
                AND owner_id = ?
                AND status = 'approved'
                LIMIT 1
            ");
            $stmt->bind_param("ii", $bus_id, $owner_id);
            $stmt->execute();
            $bus_result = $stmt->get_result();

            if ($bus_result->num_rows === 0) {
                $message = "Invalid bus or this bus is not approved.";
                $message_type = "error";
            } else {
                $stmt = $conn->prepare("
                    SELECT user_id
                    FROM users
                    WHERE user_id = ?
                    AND role = 'driver'
                    AND verification_status = 'verified'
                    LIMIT 1
                ");
                $stmt->bind_param("i", $driver_id);
                $stmt->execute();
                $driver_result = $stmt->get_result();

                if ($driver_result->num_rows === 0) {
                    $message = "This driver is not approved by admin.";
                    $message_type = "error";
                } else {
                    $stmt = $conn->prepare("
                        SELECT bus_driver_id
                        FROM bus_driver
                        WHERE driver_id = ?
                        LIMIT 1
                    ");
                    $stmt->bind_param("i", $driver_id);
                    $stmt->execute();
                    $assigned_result = $stmt->get_result();

                    if ($assigned_result->num_rows > 0) {
                        $message = "This driver is already assigned to another bus.";
                        $message_type = "error";
                    } else {
                        $stmt = $conn->prepare("
                            INSERT INTO bus_driver
                            (bus_id, driver_id)
                            VALUES (?, ?)
                        ");
                        $stmt->bind_param("ii", $bus_id, $driver_id);

                        if ($stmt->execute()) {
                            header("Location: driver.php?msg=assigned");
                            exit;
                        } else {
                            $message = "Unable to assign driver.";
                            $message_type = "error";
                        }
                    }
                }
            }
        }
    }

    if (isset($_POST['remove_driver'])) {
        $assignment_id = isset($_POST['assignment_id'])
            ? (int)$_POST['assignment_id']
            : 0;

        if ($assignment_id > 0) {
            $stmt = $conn->prepare("
                DELETE bd
                FROM bus_driver bd
                INNER JOIN bus b
                    ON bd.bus_id = b.bus_id
                WHERE bd.bus_driver_id = ?
                AND b.owner_id = ?
            ");
            $stmt->bind_param("ii", $assignment_id, $owner_id);

            if ($stmt->execute()) {
                header("Location: driver.php?msg=removed");
                exit;
            } else {
                $message = "Unable to remove driver.";
                $message_type = "error";
            }
        }
    }
}

$buses = [];

$stmt = $conn->prepare("
    SELECT
        bus_id,
        bus_number,
        bus_name,
        bus_type,
        seats
    FROM bus
    WHERE owner_id = ?
    AND status = 'approved'
    ORDER BY bus_id DESC
");
$stmt->bind_param("i", $owner_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $buses[] = $row;
}

$available_drivers = [];

$stmt = $conn->prepare("
    SELECT
        u.user_id,
        u.name,
        u.email,
        u.phone,
        u.profile_image,
        u.verification_status
    FROM users u
    WHERE u.role = 'driver'
    AND u.verification_status = 'verified'
    AND NOT EXISTS (
        SELECT 1
        FROM bus_driver bd
        WHERE bd.driver_id = u.user_id
    )
    ORDER BY u.name ASC
");
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $available_drivers[] = $row;
}

$assignments = [];

$stmt = $conn->prepare("
    SELECT
        bd.bus_driver_id,
        b.bus_id,
        b.bus_number,
        b.bus_name,
        b.bus_type,
        u.user_id AS driver_id,
        u.name AS driver_name,
        u.email AS driver_email,
        u.phone AS driver_phone,
        u.profile_image,
        u.verification_status
    FROM bus_driver bd
    INNER JOIN bus b
        ON bd.bus_id = b.bus_id
    INNER JOIN users u
        ON bd.driver_id = u.user_id
    WHERE b.owner_id = ?
    ORDER BY bd.bus_driver_id DESC
");
$stmt->bind_param("i", $owner_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $assignments[] = $row;
}

$total_buses = count($buses);
$total_available_drivers = count($available_drivers);
$total_assignments = count($assignments);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver Management</title>
    <link rel="stylesheet" href="driver.css">

</head>

<body>
    <div class="page">
        <div class="header">
            <div>
                <h1>Driver Management</h1>
                <p>Assign approved drivers to your approved buses.</p>
            </div>
            <a href="dashboard.php" class="back-btn">← Dashboard</a>
        </div>

        <?php if ($message !== ""): ?>
            <div class="message <?= htmlspecialchars($message_type) ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="stats">
            <div class="stat">
                <small>My Approved Buses</small>
                <strong><?= $total_buses ?></strong>
            </div>
            <div class="stat">
                <small>Available Approved Drivers</small>
                <strong><?= $total_available_drivers ?></strong>
            </div>
            <div class="stat">
                <small>My Assigned Drivers</small>
                <strong><?= $total_assignments ?></strong>
            </div>
        </div>

        <div class="box">
            <h2>Assign Driver</h2>
            <p class="box-description"> Only drivers approved by Admin and not assigned to another bus are available. </p>

            <?php if (!empty($buses) && !empty($available_drivers)): ?>
                <form method="POST" onsubmit="return confirm('Are you sure you want to assign this driver to this bus?');">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Select Approved Bus</label>
                            <select name="bus_id" required>
                                <option value="">Select bus</option>
                                <?php foreach ($buses as $bus): ?>
                                    <option value="<?= (int)$bus['bus_id'] ?>"> <?= htmlspecialchars($bus['bus_number']) ?> -
                                        <?= htmlspecialchars($bus['bus_name']) ?> </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Select Approved Driver</label>
                            <select name="driver_id" required>
                                <option value="">Select driver</option>
                                <?php foreach ($available_drivers as $driver): ?> <option value="<?= (int)$driver['user_id'] ?>"> <?= htmlspecialchars($driver['name']) ?> - <?= htmlspecialchars($driver['phone']) ?> </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <button type="submit" name="assign_driver" class="assign-btn">
                            Assign Driver
                        </button>
                    </div>
                </form>
            <?php elseif (empty($buses)): ?>
                <div class="empty">
                    <div class="empty-icon"></div>
                    <h3>No Approved Bus</h3>
                    <p>Admin must approve your bus before you can assign a driver.</p>
                </div>
            <?php else: ?>
                <div class="empty">
                    <div class="empty-icon"></div>
                    <h3>No Available Driver</h3>
                    <p>There are currently no Admin-approved drivers available.</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="table-box">
            <div class="table-header">
                <h2>Approved Drivers</h2>
                <span><?= count($available_drivers) ?> Driver(s)</span>
            </div>

            <?php if (!empty($available_drivers)): ?>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Driver</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Verification</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($available_drivers as $driver): ?>

                                <?php
                                $driver_image = !empty($driver['profile_image'])
                                    ? $driver['profile_image']
                                    : 'default.png';
                                ?>

                                <tr>
                                    <td>
                                        <div class="driver-info">
                                            <img src="../uploads/profile/<?= htm($driver_image) ?>" class="driver-image" alt="Driver" onerror="this. this.src='../images/default.png';">

                                            <div>
                                                <div class="driver-name">
                                                    <?= htmlspecialchars($driver['name']) ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($driver['email']) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($driver['phone']) ?>
                                    </td>

                                    <td>
                                        <span class="badge verified">
                                            Approved
                                        </span>
                                    </td>

                                    <td>
                                        <form method="POST">
                                            <input
                                                type="hidden"
                                                name="driver_id"
                                                value="<?= (int)$driver['user_id'] ?>">

                                            <select name="bus_id" required>
                                                <option value="">Select Bus</option>

                                                <?php foreach ($buses as $bus): ?>
                                                    <option value="<?= (int)$bus['bus_id'] ?>">
                                                        <?= htmlspecialchars($bus['bus_number']) ?>
                                                        -
                                                        <?= htmlspecialchars($bus['bus_name']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>

                                            <button
                                                type="submit"
                                                name="assign_driver"
                                                class="assign-btn">
                                                Assign
                                            </button>
                                        </form>
                                    </td>
                                </tr>

                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php else: ?>

                <div class="empty">
                    <div class="empty-icon"></div>
                    <h3>No Approved Drivers</h3>
                    <p>
                        No Admin-approved drivers are currently available.
                    </p>
                </div>

            <?php endif; ?>
        </div>
    </div>
</body>

</html>