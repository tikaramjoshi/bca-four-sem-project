<?php
session_start();
require_once "../db.php";

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'admin'
) {
    header("Location: ../login.php");
    exit;
}

$message = "";
$message_type = "";

if (isset($_GET['msg'])) {

    if ($_GET['msg'] === 'assigned') {
        $message = "Driver assigned to bus successfully.";
        $message_type = "success";
    }

    if ($_GET['msg'] === 'removed') {
        $message = "Driver assignment removed successfully.";
        $message_type = "success";
    }

    if ($_GET['msg'] === 'error') {
        $message = "Something went wrong.";
        $message_type = "error";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['assign_driver'])) {

        $bus_id = isset($_POST['bus_id'])
            ? (int) $_POST['bus_id']
            : 0;

        $driver_id = isset($_POST['driver_id'])
            ? (int) $_POST['driver_id']
            : 0;

        if ($bus_id <= 0 || $driver_id <= 0) {

            $message = "Please select a valid bus and driver.";
            $message_type = "error";
        } else {

            $stmt = $conn->prepare("
                SELECT bus_id
                FROM bus
                WHERE bus_id = ?
                AND status = 'approved'
                LIMIT 1
            ");

            $stmt->bind_param("i", $bus_id);
            $stmt->execute();

            $bus_result = $stmt->get_result();

            if ($bus_result->num_rows === 0) {

                $message = "Selected bus is not approved.";
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

                    $message = "Only verified drivers can be assigned.";
                    $message_type = "error";
                } else {

                    $stmt = $conn->prepare("
                        SELECT bus_id
                        FROM bus_driver
                        WHERE driver_id = ?
                        LIMIT 1
                    ");

                    $stmt->bind_param("i", $driver_id);
                    $stmt->execute();

                    $existing_driver = $stmt->get_result();

                    if ($existing_driver->num_rows > 0) {

                        $message = "This driver is already assigned to a bus.";
                        $message_type = "error";
                    } else {

                        $stmt = $conn->prepare("
                            SELECT driver_id
                            FROM bus_driver
                            WHERE bus_id = ?
                            LIMIT 1
                        ");

                        $stmt->bind_param("i", $bus_id);
                        $stmt->execute();

                        $existing_bus = $stmt->get_result();

                        if ($existing_bus->num_rows > 0) {

                            $message = "This bus already has a driver.";
                            $message_type = "error";
                        } else {

                            $stmt = $conn->prepare("
                                INSERT INTO bus_driver
                                (bus_id, driver_id)
                                VALUES (?, ?)
                            ");

                            $stmt->bind_param(
                                "ii",
                                $bus_id,
                                $driver_id
                            );

                            if ($stmt->execute()) {

                                header(
                                    "Location: assign_driver.php?msg=assigned"
                                );
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
    }

    if (isset($_POST['remove_assignment'])) {

        $assignment_id = isset($_POST['assignment_id'])
            ? (int) $_POST['assignment_id']
            : 0;

        if ($assignment_id > 0) {

            $stmt = $conn->prepare("
                DELETE FROM bus_driver
                WHERE bus_driver_id = ?
            ");

            $stmt->bind_param(
                "i",
                $assignment_id
            );

            if ($stmt->execute()) {

                header(
                    "Location: assign_driver.php?msg=removed"
                );
                exit;
            } else {

                $message = "Unable to remove assignment.";
                $message_type = "error";
            }
        }
    }
}

$search = isset($_GET['search'])
    ? trim($_GET['search'])
    : "";

$bus_id_filter = isset($_GET['bus_id'])
    ? (int) $_GET['bus_id']
    : 0;

$driver_id_filter = isset($_GET['driver_id'])
    ? (int) $_GET['driver_id']
    : 0;

$buses = [];

$stmt = $conn->prepare("
    SELECT
        b.bus_id,
        b.bus_number,
        b.bus_name,
        b.bus_type,
        b.seats,
        b.status,
        u.name AS owner_name
    FROM bus b
    LEFT JOIN users u
        ON b.owner_id = u.user_id
    WHERE b.status = 'approved'
    ORDER BY b.bus_id DESC
");

$stmt->execute();

$bus_result = $stmt->get_result();

while ($row = $bus_result->fetch_assoc()) {
    $buses[] = $row;
}

$drivers = [];

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

$driver_result = $stmt->get_result();

while ($row = $driver_result->fetch_assoc()) {
    $drivers[] = $row;
}

$sql = "
    SELECT
        bd.bus_driver_id,

        b.bus_id,
        b.bus_number,
        b.bus_name,
        b.bus_type,
        b.seats,

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

    WHERE 1 = 1
";

$params = [];
$types = "";

if ($search !== "") {

    $sql .= "
        AND (
            b.bus_number LIKE ?
            OR b.bus_name LIKE ?
            OR u.name LIKE ?
            OR u.email LIKE ?
            OR u.phone LIKE ?
        )
    ";

    $search_value = "%" . $search . "%";

    $params[] = $search_value;
    $params[] = $search_value;
    $params[] = $search_value;
    $params[] = $search_value;
    $params[] = $search_value;

    $types .= "sssss";
}

if ($bus_id_filter > 0) {

    $sql .= "
        AND b.bus_id = ?
    ";

    $params[] = $bus_id_filter;
    $types .= "i";
}

if ($driver_id_filter > 0) {

    $sql .= "
        AND u.user_id = ?
    ";

    $params[] = $driver_id_filter;
    $types[] = "i";
}

$sql .= "
    ORDER BY bd.bus_driver_id DESC
";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param(
        $types,
        ...$params
    );
}

$stmt->execute();

$assignment_result = $stmt->get_result();

$assignments = [];

while ($row = $assignment_result->fetch_assoc()) {
    $assignments[] = $row;
}

$total_assignments = count($assignments);
$total_buses = count($buses);
$total_available_drivers = count($drivers);
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>Assign Driver</title>

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
            max-width: 1400px;
            margin: 30px auto;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 25px;
        }

        .page-header h1 {
            font-size: 28px;
            color: #222;
            margin-bottom: 6px;
        }

        .page-header p {
            color: #777;
            font-size: 14px;
        }

        .back-btn {
            background: #1560bd;
            color: white;
            text-decoration: none;
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

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 3px 12px rgba(0, 0, 0, .06);
        }

        .stat-title {
            color: #777;
            font-size: 13px;
            margin-bottom: 9px;
        }

        .stat-value {
            color: #1560bd;
            font-size: 28px;
            font-weight: 700;
        }

        .assign-box {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 3px 12px rgba(0, 0, 0, .06);
            margin-bottom: 25px;
        }

        .box-title {
            margin-bottom: 20px;
        }

        .box-title h2 {
            font-size: 20px;
            margin-bottom: 5px;
        }

        .box-title p {
            color: #777;
            font-size: 13px;
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
            color: #444;
            font-size: 13px;
            font-weight: 600;
        }

        .form-group select {
            width: 100%;
            height: 44px;
            border: 1px solid #ddd;
            border-radius: 7px;
            padding: 0 12px;
            background: white;
            outline: none;
            font-size: 14px;
        }

        .form-group select:focus {
            border-color: #1560bd;
        }

        .assign-btn {
            height: 44px;
            border: none;
            padding: 0 22px;
            border-radius: 7px;
            background: #198754;
            color: white;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
        }

        .assign-btn:hover {
            background: #146c43;
        }

        .filter-box {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(0, 0, 0, .06);
            margin-bottom: 25px;
        }

        .filter-form {
            display: grid;
            grid-template-columns: 1fr 220px 220px auto auto;
            gap: 12px;
        }

        .filter-input,
        .filter-select {
            height: 43px;
            width: 100%;
            border: 1px solid #ddd;
            border-radius: 7px;
            padding: 0 12px;
            outline: none;
            font-size: 14px;
            background: white;
        }

        .filter-input:focus,
        .filter-select:focus {
            border-color: #1560bd;
        }

        .filter-btn,
        .clear-btn {
            height: 43px;
            padding: 0 20px;
            border-radius: 7px;
            border: none;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 14px;
        }

        .filter-btn {
            background: #1560bd;
            color: white;
        }

        .filter-btn:hover {
            background: #0d4f9c;
        }

        .clear-btn {
            background: #6c757d;
            color: white;
        }

        .clear-btn:hover {
            background: #565e64;
        }

        .table-box {
            background: white;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(0, 0, 0, .06);
            overflow: hidden;
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
            min-width: 1050px;
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
            border-bottom: 1px solid #eee;
            font-size: 14px;
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
            border: 2px solid #e5e5e5;
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

        .action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 34px;
            padding: 0 11px;
            border-radius: 6px;
            text-decoration: none;
            border: none;
            font-size: 12px;
            cursor: pointer;
        }

        .remove-btn {
            background: #dc3545;
            color: white;
        }

        .remove-btn:hover {
            background: #b02a37;
        }

        .view-btn {
            background: #1560bd;
            color: white;
        }

        .view-btn:hover {
            background: #0d4f9c;
        }

        .empty {
            text-align: center;
            padding: 60px 20px;
            color: #777;
        }

        .empty-icon {
            font-size: 42px;
            margin-bottom: 12px;
        }

        .empty h3 {
            color: #444;
            margin-bottom: 6px;
        }

        .empty p {
            font-size: 14px;
        }

        .actions {
            display: flex;
            gap: 7px;
            flex-wrap: wrap;
        }

        @media (max-width: 1050px) {

            .form-grid {
                grid-template-columns: 1fr 1fr;
            }

            .assign-btn {
                width: 100%;
            }

            .filter-form {
                grid-template-columns: 1fr 1fr;
            }

        }

        @media (max-width: 700px) {

            .page {
                width: 94%;
                margin: 20px auto;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .stats {
                grid-template-columns: 1fr;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .filter-form {
                grid-template-columns: 1fr;
            }

            .filter-btn,
            .clear-btn {
                width: 100%;
            }

        }
    </style>

</head>

<body>

    <div class="page">

        <div class="page-header">

            <div>

                <h1>
                    Driver Assignment
                </h1>

                <p>
                    Assign verified drivers to approved buses.
                </p>

            </div>

            <a
                href="dashboard.php"
                class="back-btn">
                ← Dashboard
            </a>

        </div>

        <?php if ($message !== ""): ?>

            <div class="message <?= htmlspecialchars($message_type) ?>">
                <?= htmlspecialchars($message) ?>
            </div>

        <?php endif; ?>

        <div class="stats">

            <div class="stat-card">

                <div class="stat-title">
                    Approved Buses
                </div>

                <div class="stat-value">
                    <?= $total_buses ?>
                </div>

            </div>

            <div class="stat-card">

                <div class="stat-title">
                    Available Verified Drivers
                </div>

                <div class="stat-value">
                    <?= $total_available_drivers ?>
                </div>

            </div>

            <div class="stat-card">

                <div class="stat-title">
                    Current Assignments
                </div>

                <div class="stat-value">
                    <?= $total_assignments ?>
                </div>

            </div>

        </div>

        <div class="assign-box">

            <div class="box-title">

                <h2>
                    Assign Driver to Bus
                </h2>

                <p>
                    Only approved buses and verified available drivers are shown.
                </p>

            </div>

            <?php if (!empty($buses) && !empty($drivers)): ?>

                <form
                    method="POST"
                    onsubmit="return confirmAssignment();">

                    <div class="form-grid">

                        <div class="form-group">

                            <label>
                                Select Bus
                            </label>

                            <select
                                name="bus_id"
                                required>

                                <option value="">
                                    Select approved bus
                                </option>

                                <?php foreach ($buses as $bus): ?>

                                    <option
                                        value="<?= (int) $bus['bus_id'] ?>">

                                        <?= htmlspecialchars($bus['bus_number']) ?>
                                        -
                                        <?= htmlspecialchars($bus['bus_name']) ?>
                                        |
                                        <?= htmlspecialchars($bus['bus_type']) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                        <div class="form-group">

                            <label>
                                Select Verified Driver
                            </label>

                            <select
                                name="driver_id"
                                required>

                                <option value="">
                                    Select driver
                                </option>

                                <?php foreach ($drivers as $driver): ?>

                                    <option
                                        value="<?= (int) $driver['user_id'] ?>">

                                        <?= htmlspecialchars($driver['name']) ?>
                                        -
                                        <?= htmlspecialchars($driver['phone']) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                        <div>

                            <button
                                type="submit"
                                name="assign_driver"
                                class="assign-btn">

                                ✓ Assign Driver

                            </button>

                        </div>

                    </div>

                </form>

            <?php elseif (empty($buses)): ?>

                <div class="empty">

                    <div class="empty-icon">
                        🚌
                    </div>

                    <h3>
                        No Approved Buses
                    </h3>

                    <p>
                        Approve a bus before assigning a driver.
                    </p>

                </div>

            <?php else: ?>

                <div class="empty">

                    <div class="empty-icon">
                        👤
                    </div>

                    <h3>
                        No Available Drivers
                    </h3>

                    <p>
                        You need a verified driver who is not already assigned.
                    </p>

                </div>

            <?php endif; ?>

        </div>

        <div class="filter-box">

            <form
                method="GET"
                class="filter-form">

                <input
                    type="text"
                    name="search"
                    class="filter-input"
                    placeholder="Search driver, bus number, email or phone..."
                    value="<?= htmlspecialchars($search) ?>">

                <select
                    name="bus_id"
                    class="filter-select">

                    <option value="0">
                        All Buses
                    </option>

                    <?php foreach ($buses as $bus): ?>

                        <option
                            value="<?= (int) $bus['bus_id'] ?>"
                            <?= $bus_id_filter === (int) $bus['bus_id']
                                ? 'selected'
                                : '' ?>>

                            <?= htmlspecialchars($bus['bus_number']) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

                <select
                    name="driver_id"
                    class="filter-select">

                    <option value="0">
                        All Assigned Drivers
                    </option>

                    <?php

                    $assigned_driver_list = [];

                    foreach ($assignments as $assignment) {
                        $assigned_driver_list[$assignment['driver_id']] = $assignment['driver_name'];
                    }

                    foreach ($assigned_driver_list as $id => $name):

                    ?>

                        <option
                            value="<?= (int) $id ?>"
                            <?= $driver_id_filter === (int) $id
                                ? 'selected'
                                : '' ?>>

                            <?= htmlspecialchars($name) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

                <button
                    type="submit"
                    class="filter-btn">

                    Search

                </button>

                <a
                    href="assign_driver.php"
                    class="clear-btn">

                    Clear

                </a>

            </form>

        </div>

        <div class="table-box">

            <div class="table-header">

                <h2>
                    Current Driver Assignments
                </h2>

                <span>
                    <?= $total_assignments ?> assignment(s)
                </span>

            </div>

            <?php if (!empty($assignments)): ?>

                <div class="table-wrapper">

                    <table>

                        <thead>

                            <tr>

                                <th>
                                    Driver
                                </th>

                                <th>
                                    Phone
                                </th>

                                <th>
                                    Assigned Bus
                                </th>

                                <th>
                                    Bus Type
                                </th>

                                <th>
                                    Verification
                                </th>

                                <th>
                                    Status
                                </th>

                                <th>
                                    Actions
                                </th>

                            </tr>

                        </thead>

                        <tbody>

                            <?php foreach ($assignments as $assignment): ?>

                                <?php

                                $profile_image =
                                    !empty($assignment['profile_image'])
                                    ? $assignment['profile_image']
                                    : 'default.png';

                                ?>

                                <tr>

                                    <td>

                                        <div class="driver-info">

                                            <img
                                                src="../uploads/profile/<?= htmlspecialchars($profile_image) ?>"
                                                class="driver-image"
                                                alt="Driver"
                                                onerror="this.onerror=null;this.src='../images/default.png';">

                                            <div>

                                                <div class="driver-name">

                                                    <?= htmlspecialchars(
                                                        $assignment['driver_name']
                                                    ) ?>

                                                </div>

                                                <div class="driver-email">

                                                    <?= htmlspecialchars(
                                                        $assignment['driver_email']
                                                    ) ?>

                                                </div>

                                            </div>

                                        </div>

                                    </td>

                                    <td>

                                        <?= htmlspecialchars(
                                            $assignment['driver_phone']
                                        ) ?>

                                    </td>

                                    <td>

                                        <div class="bus-number">

                                            <?= htmlspecialchars(
                                                $assignment['bus_number']
                                            ) ?>

                                        </div>

                                        <div class="bus-name">

                                            <?= htmlspecialchars(
                                                $assignment['bus_name']
                                            ) ?>

                                        </div>

                                    </td>

                                    <td>

                                        <?= htmlspecialchars(
                                            $assignment['bus_type']
                                        ) ?>

                                    </td>

                                    <td>

                                        <span class="badge verified">
                                            ✓ Verified
                                        </span>

                                    </td>

                                    <td>

                                        <span class="badge assigned">
                                            ● Assigned
                                        </span>

                                    </td>

                                    <td>

                                        <div class="actions">

                                            <a
                                                href="view_driver.php?id=<?= (int) $assignment['driver_id'] ?>"
                                                class="action-btn view-btn">

                                                View Driver

                                            </a>

                                            <form
                                                method="POST"
                                                style="display:inline;"
                                                onsubmit="return confirmRemove();">

                                                <input
                                                    type="hidden"
                                                    name="assignment_id"
                                                    value="<?= (int) $assignment['bus_driver_id'] ?>">

                                                <button
                                                    type="submit"
                                                    name="remove_assignment"
                                                    class="action-btn remove-btn">

                                                    Remove

                                                </button>

                                            </form>

                                        </div>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <div class="empty">

                    <div class="empty-icon">
                        🔗
                    </div>

                    <h3>
                        No Driver Assignments
                    </h3>

                    <p>
                        No driver has been assigned to a bus yet.
                    </p>

                </div>

            <?php endif; ?>

        </div>

    </div>

    <script>
        function confirmAssignment() {

            return confirm(
                "Are you sure you want to assign this driver to this bus?"
            );

        }

        function confirmRemove() {

            return confirm(
                "Are you sure you want to remove this driver assignment?"
            );

        }
    </script>

</body>

</html>