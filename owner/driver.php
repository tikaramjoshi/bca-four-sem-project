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
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, Helvetica, sans-serif;
        }

        body {
            background: #f4f6f9;
            color: #222;
        }

        .page {
            width: 94%;
            max-width: 1350px;
            margin: 30px auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .header h1 {
            font-size: 28px;
            margin-bottom: 6px;
        }

        .header p {
            color: #777;
            font-size: 14px;
        }

        .back-btn {
            text-decoration: none;
            background: #1560bd;
            color: #fff;
            padding: 11px 18px;
            border-radius: 7px;
            font-size: 14px;
        }

        .back-btn:hover {
            background: #0d4f9c;
        }

        .message {
            padding: 14px 17px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .message.success {
            background: #d1e7dd;
            color: #0f5132;
        }

        .message.error {
            background: #f8d7da;
            color: #842029;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
            margin-bottom: 25px;
        }

        .stat {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 3px 12px rgba(0, 0, 0, .06);
        }

        .stat small {
            display: block;
            color: #777;
            font-size: 13px;
            margin-bottom: 8px;
        }

        .stat strong {
            color: #1560bd;
            font-size: 28px;
        }

        .box {
            background: #fff;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 3px 12px rgba(0, 0, 0, .06);
        }

        .box h2 {
            font-size: 20px;
            margin-bottom: 6px;
        }

        .box-description {
            color: #777;
            font-size: 13px;
            margin-bottom: 20px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 15px;
            align-items: end;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 13px;
            font-weight: 600;
            color: #444;
        }

        .form-group select {
            width: 100%;
            height: 44px;
            border: 1px solid #ddd;
            border-radius: 7px;
            padding: 0 12px;
            background: #fff;
            outline: none;
            font-size: 14px;
        }

        .form-group select:focus {
            border-color: #1560bd;
        }

        .assign-btn {
            height: 44px;
            padding: 0 22px;
            border: none;
            border-radius: 7px;
            background: #198754;
            color: #fff;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
        }

        .assign-btn:hover {
            background: #146c43;
        }

        .table-box {
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 3px 12px rgba(0, 0, 0, .06);
        }

        .table-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-header h2 {
            font-size: 19px;
        }

        .table-header span {
            color: #777;
            font-size: 13px;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 950px;
            border-collapse: collapse;
        }

        th {
            background: #f7f9fc;
            color: #555;
            padding: 15px;
            text-align: left;
            font-size: 13px;
            border-bottom: 1px solid #eee;
        }

        td {
            padding: 15px;
            font-size: 14px;
            border-bottom: 1px solid #eee;
            vertical-align: middle;
        }

        tr:hover td {
            background: #fafcff;
        }

        .driver-info {
            display: flex;
            align-items: center;
            gap: 11px;
        }

        .driver-image {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #ddd;
        }

        .driver-name {
            font-weight: 600;
            margin-bottom: 3px;
        }

        .driver-email {
            color: #888;
            font-size: 12px;
        }

        .bus-number {
            color: #1560bd;
            font-weight: 700;
        }

        .bus-name {
            color: #777;
            font-size: 12px;
            margin-top: 3px;
        }

        .badge {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .verified {
            background: #d1e7dd;
            color: #0f5132;
        }

        .assigned {
            background: #cff4fc;
            color: #055160;
        }

        .remove-btn {
            border: none;
            background: #dc3545;
            color: #fff;
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
        }

        .remove-btn:hover {
            background: #b02a37;
        }

        .empty {
            text-align: center;
            padding: 50px 20px;
            color: #777;
        }

        .empty-icon {
            font-size: 40px;
            margin-bottom: 10px;
        }

        .empty h3 {
            color: #444;
            margin-bottom: 6px;
        }

        .empty p {
            font-size: 14px;
        }

        @media(max-width:900px) {
            .form-grid {
                grid-template-columns: 1fr;
            }

            .assign-btn {
                width: 100%;
            }

            .stats {
                grid-template-columns: 1fr;
            }
        }

        @media(max-width:600px) {
            .page {
                width: 94%;
                margin: 20px auto;
            }

            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
        }

        .table-box {
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 3px 12px rgba(0, 0, 0, .06);
            margin-bottom: 25px;
        }

        .table-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-header h2 {
            font-size: 19px;
        }

        .table-header span {
            color: #777;
            font-size: 13px;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f7f9fc;
            color: #555;
            padding: 15px;
            text-align: left;
            font-size: 13px;
            border-bottom: 1px solid #eee;
        }

        td {
            padding: 15px;
            font-size: 14px;
            border-bottom: 1px solid #eee;
            vertical-align: middle;
        }

        tr:hover td {
            background: #fafcff;
        }

        .driver-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .driver-image {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #ddd;
        }

        .driver-name {
            font-weight: 600;
        }

        .badge {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .verified {
            background: #d1e7dd;
            color: #0f5132;
        }

        td form {
            display: flex;
            gap: 7px;
            align-items: center;
        }

        td select {
            height: 36px;
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 0 8px;
            background: #fff;
        }

        .assign-btn {
            height: 36px;
            padding: 0 14px;
            border: none;
            border-radius: 6px;
            background: #198754;
            color: #fff;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
        }

        .assign-btn:hover {
            background: #146c43;
        }
    </style>
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
            <p class="box-description">
                Only drivers approved by Admin and not assigned to another bus are available.
            </p>

            <?php if (!empty($buses) && !empty($available_drivers)): ?>
                <form method="POST" onsubmit="return confirm('Are you sure you want to assign this driver to this bus?');">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Select Approved Bus</label>
                            <select name="bus_id" required>
                                <option value="">Select bus</option>
                                <?php foreach ($buses as $bus): ?>
                                    <option value="<?= (int)$bus['bus_id'] ?>">
                                        <?= htmlspecialchars($bus['bus_number']) ?> -
                                        <?= htmlspecialchars($bus['bus_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Select Approved Driver</label>
                            <select name="driver_id" required>
                                <option value="">Select driver</option>
                                <?php foreach ($available_drivers as $driver): ?>
                                    <option value="<?= (int)$driver['user_id'] ?>">
                                        <?= htmlspecialchars($driver['name']) ?> -
                                        <?= htmlspecialchars($driver['phone']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <button type="submit" name="assign_driver" class="assign-btn">
                            ✓ Assign Driver
                        </button>
                    </div>
                </form>
            <?php elseif (empty($buses)): ?>
                <div class="empty">
                    <div class="empty-icon">🚌</div>
                    <h3>No Approved Bus</h3>
                    <p>Admin must approve your bus before you can assign a driver.</p>
                </div>
            <?php else: ?>
                <div class="empty">
                    <div class="empty-icon">👤</div>
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
                                            <img
                                                src="../uploads/profile/<?= htmlspecialchars($driver_image) ?>"
                                                class="driver-image"
                                                alt="Driver"
                                                onerror="this.onerror=null;this.src='../images/default.png';">

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
                                            ✓ Approved
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
                    <div class="empty-icon">👤</div>
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