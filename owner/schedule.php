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

if (isset($_SESSION['success'])) {
    $message = $_SESSION['success'];
    $message_type = "success";
    unset($_SESSION['success']);
}

if (isset($_SESSION['error'])) {
    $message = $_SESSION['error'];
    $message_type = "error";
}

$stmt = $conn->prepare("SELECT verification_status FROM users WHERE user_id=? AND role='owner' LIMIT 1");
$stmt->bind_param("i", $owner_id);
$stmt->execute();
$owner = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$owner) {
    session_destroy();
    header("Location: ../login.php");
    exit();
}

$isVerified = ($owner['verification_status'] === 'verified');

if (isset($_POST['add_schedule'])) {
    if (!$isVerified) {
        $message = "Please complete account verification first.";
        $message_type = "error";
    } else {
        $bus_id = (int)($_POST['bus_id'] ?? 0);
        $from_city = trim($_POST['from_city'] ?? '');
        $to_city = trim($_POST['to_city'] ?? '');
        $departure_date = trim($_POST['departure_date'] ?? '');
        $departure_time = trim($_POST['departure_time'] ?? '');
        $ticket_price = (float)($_POST['ticket_price'] ?? 0);

        if ($bus_id <= 0 || $from_city === '' || $to_city === '' || $departure_date === '' || $departure_time === '' || $ticket_price <= 0) {
            $message = "All fields are required.";
            $message_type = "error";
        } elseif ($from_city === $to_city) {
            $message = "From city and To city cannot be the same.";
            $message_type = "error";
        } else {
            $busStmt = $conn->prepare("SELECT bus_id,seats FROM bus WHERE bus_id=? AND owner_id=? AND status='approved' LIMIT 1");
            $busStmt->bind_param("ii", $bus_id, $owner_id);
            $busStmt->execute();
            $bus = $busStmt->get_result()->fetch_assoc();
            $busStmt->close();

            if (!$bus) {
                $message = "Invalid bus. You can only create schedule for your own approved bus.";
                $message_type = "error";
            } else {
                $available_seats = (int)$bus['seats'];

                $checkStmt = $conn->prepare("SELECT schedule_id FROM schedules WHERE bus_id=? AND departure_date=? AND departure_time=? LIMIT 1");
                $checkStmt->bind_param("iss", $bus_id, $departure_date, $departure_time);
                $checkStmt->execute();
                $exists = $checkStmt->get_result()->num_rows > 0;
                $checkStmt->close();

                if ($exists) {
                    $message = "A schedule already exists for this bus at the selected date and time.";
                    $message_type = "error";
                } else {
                    $insert = $conn->prepare("INSERT INTO schedules (bus_id,from_city,to_city,departure_date,departure_time,ticket_price,available_seats,status) VALUES (?,?,?,?,?,?,?,'active')");
                    $insert->bind_param("issssdi", $bus_id, $from_city, $to_city, $departure_date, $departure_time, $ticket_price, $available_seats);

                    if ($insert->execute()) {
                        $_SESSION['success'] = "Schedule added successfully.";
                        $insert->close();
                        header("Location: schedule.php");
                        exit();
                    } else {
                        $message = "Failed to add schedule.";
                        $message_type = "error";
                    }

                    $insert->close();
                }
            }
        }
    }
}

if (isset($_GET['delete'])) {
    $schedule_id = (int)$_GET['delete'];

    if ($schedule_id > 0) {
        $delete = $conn->prepare("DELETE s FROM schedules s INNER JOIN bus b ON s.bus_id=b.bus_id WHERE s.schedule_id=? AND b.owner_id=?");
        $delete->bind_param("ii", $schedule_id, $owner_id);

        if ($delete->execute() && $delete->affected_rows > 0) {
            $_SESSION['success'] = "Schedule deleted successfully.";
        } else {
            $_SESSION['error'] = "Schedule not found or you do not have permission.";
        }

        $delete->close();
    }

    header("Location: schedule.php");
    exit();
}

$busStmt = $conn->prepare("SELECT bus_id,bus_number,bus_name,bus_type,seats FROM bus WHERE owner_id=? AND status='approved' ORDER BY bus_id DESC");
$busStmt->bind_param("i", $owner_id);
$busStmt->execute();
$busResult = $busStmt->get_result();

$scheduleStmt = $conn->prepare("
    SELECT
        s.schedule_id,
        s.bus_id,
        s.from_city,
        s.to_city,
        s.departure_date,
        s.departure_time,
        s.ticket_price,
        s.available_seats,
        s.status,
        b.bus_number,
        b.bus_name,
        b.bus_type,
        b.seats
    FROM schedules s
    INNER JOIN bus b ON s.bus_id=b.bus_id
    WHERE b.owner_id=?
    ORDER BY s.departure_date ASC,s.departure_time ASC,s.schedule_id DESC
");
$scheduleStmt->bind_param("i", $owner_id);
$scheduleStmt->execute();
$scheduleResult = $scheduleStmt->get_result();

$today = date('Y-m-d');
$max_date = date('Y-m-d', strtotime('+7 days'));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Owner Schedule</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        body {
            background: #f4f6f9;
            color: #222;
        }

        .main {
            height: 70px;
            background: #1560bd;
            display: flex;
            align-items: center;
            padding: 0 30px;
        }

        .main a {
            color: white;
            text-decoration: none;
            font-weight: bold;
            font-size: 17px;
        }

        .container {
            width: 95%;
            max-width: 1300px;
            margin: 30px auto;
        }

        .title {
            background: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 25px;
        }

        .title h1 {
            color: #1560bd;
            margin-bottom: 8px;
        }

        .title p {
            color: #666;
        }

        .alert {
            padding: 13px 16px;
            border-radius: 7px;
            margin-bottom: 20px;
            font-weight: bold;
        }

        .success {
            background: #d4edda;
            color: #155724;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
        }

        .form-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
        }

        .form-card h2 {
            color: #1560bd;
            margin-bottom: 20px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 18px;
        }

        .form-group label {
            display: block;
            font-weight: bold;
            margin-bottom: 7px;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 15px;
            outline: none;
        }

        .form-group input:focus,
        .form-group select:focus {
            border-color: #1560bd;
        }

        .full {
            grid-column: 1/-1;
        }

        .add-btn {
            background: #1560bd;
            color: white;
            border: none;
            padding: 13px 25px;
            border-radius: 6px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            margin-top: 5px;
        }

        .add-btn:hover {
            background: #0d4d9b;
        }

        .table-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            overflow-x: auto;
        }

        .table-card h2 {
            color: #1560bd;
            margin-bottom: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1100px;
        }

        th {
            background: #1560bd;
            color: white;
            padding: 13px 10px;
            text-align: center;
        }

        td {
            padding: 12px 10px;
            border-bottom: 1px solid #ddd;
            text-align: center;
        }

        tr:hover {
            background: #f7f9fc;
        }

        .status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: bold;
        }

        .active {
            background: #d4edda;
            color: #155724;
        }

        .inactive {
            background: #f8d7da;
            color: #721c24;
        }

        .edit-btn,
        .delete-btn {
            display: inline-block;
            padding: 7px 11px;
            border-radius: 5px;
            color: white;
            text-decoration: none;
            font-size: 13px;
            font-weight: bold;
            margin: 2px;
        }

        .edit-btn {
            background: #1560bd;
        }

        .delete-btn {
            background: #dc3545;
        }

        .edit-btn:hover {
            background: #0d4d9b;
        }

        .delete-btn:hover {
            background: #b52a37;
        }

        .no-data {
            text-align: center;
            padding: 30px;
            color: #777;
        }

        .warning {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 7px;
            margin-bottom: 20px;
        }

        .footer {
            margin-top: 40px;
            background: #1560bd;
            color: white;
            text-align: center;
            padding: 18px;
        }

        @media(max-width:700px) {
            .form-grid {
                grid-template-columns: 1fr;
            }

            .container {
                width: 92%;
            }

            .main {
                padding: 0 20px;
            }
        }
    </style>
</head>

<body>

    <div class="main">
        <a href="dashboard.php">
            <i class="fa fa-home"></i>&nbsp; Home
        </a>
    </div>

    <div class="container">

        <div class="title">
            <h1>Schedule Management</h1>
            <p>Create and manage schedules for your approved buses.</p>
        </div>

        <?php if ($message !== ""): ?>
            <div class="alert <?= htmlspecialchars($message_type) ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if (!$isVerified): ?>

            <div class="warning">
                <i class="fa fa-lock"></i>
                Please complete your account verification before creating or managing schedules.
            </div>

        <?php elseif ($busResult->num_rows === 0): ?>

            <div class="warning">
                <i class="fa fa-bus"></i>
                No approved bus found. Your bus must be approved by admin before creating a schedule.
            </div>

        <?php else: ?>

            <div class="form-card">
                <h2><i class="fa fa-calendar-plus"></i> Add New Schedule</h2>

                <form method="POST">

                    <div class="form-grid">

                        <div class="form-group">
                            <label>Bus</label>
                            <select name="bus_id" required>
                                <option value="">Select Approved Bus</option>

                                <?php while ($bus = $busResult->fetch_assoc()): ?>

                                    <option value="<?= (int)$bus['bus_id'] ?>">
                                        <?= htmlspecialchars($bus['bus_number']) ?> -
                                        <?= htmlspecialchars($bus['bus_name']) ?>
                                        (<?= (int)$bus['seats'] ?> Seats)
                                    </option>

                                <?php endwhile; ?>

                            </select>
                        </div>

                        <div class="form-group">
                            <label>From City</label>
                            <input type="text" name="from_city" placeholder="Enter departure city" required>
                        </div>

                        <div class="form-group">
                            <label>To City</label>
                            <input type="text" name="to_city" placeholder="Enter destination city" required>
                        </div>

                        <div class="form-group">
                            <label>Departure Date</label>
                            <input type="date" name="departure_date" min="<?= $today ?>" max="<?= $max_date ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Departure Time</label>
                            <input type="time" name="departure_time" required>
                        </div>

                        <div class="form-group">
                            <label>Ticket Price</label>
                            <input type="number" name="ticket_price" min="1" step="0.01" placeholder="Enter ticket price" required>
                        </div>

                        <div class="full">
                            <button type="submit" name="add_schedule" class="add-btn">
                                <i class="fa fa-plus"></i> Add Schedule
                            </button>
                        </div>

                    </div>

                </form>
            </div>

        <?php endif; ?>

        <div class="table-card">

            <h2>
                <i class="fa fa-calendar"></i> My Bus Schedules
            </h2>

            <?php if ($scheduleResult->num_rows > 0): ?>

                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Bus Number</th>
                            <th>Bus Name</th>
                            <th>Route</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Price</th>
                            <th>Available Seats</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php while ($row = $scheduleResult->fetch_assoc()): ?>

                            <tr>

                                <td><?= (int)$row['schedule_id'] ?></td>

                                <td><?= htmlspecialchars($row['bus_number']) ?></td>

                                <td><?= htmlspecialchars($row['bus_name']) ?></td>

                                <td>
                                    <?= htmlspecialchars($row['from_city']) ?>
                                    <i class="fa fa-arrow-right"></i>
                                    <?= htmlspecialchars($row['to_city']) ?>
                                </td>

                                <td><?= htmlspecialchars($row['departure_date']) ?></td>

                                <td><?= htmlspecialchars(date("h:i A", strtotime($row['departure_time']))) ?></td>

                                <td>Rs. <?= number_format((float)$row['ticket_price'], 2) ?></td>

                                <td><?= (int)$row['available_seats'] ?></td>

                                <td>
                                    <?php if ($row['status'] === 'active'): ?>
                                        <span class="status active">Active</span>
                                    <?php else: ?>
                                        <span class="status inactive">
                                            <?= htmlspecialchars(ucfirst($row['status'])) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <a href="edit_schedule.php?id=<?= (int)$row['schedule_id'] ?>" class="edit-btn">
                                        <i class="fa fa-edit"></i> Edit
                                    </a>

                                    <a href="schedule.php?delete=<?= (int)$row['schedule_id'] ?>" class="delete-btn" onclick="return confirm('Delete this schedule?')">
                                        <i class="fa fa-trash"></i> Delete
                                    </a>
                                </td>

                            </tr>

                        <?php endwhile; ?>

                    </tbody>
                </table>

            <?php else: ?>

                <div class="no-data">
                    <i class="fa fa-calendar-xmark" style="font-size:45px"></i>
                    <p>No schedule found for your buses.</p>
                </div>

            <?php endif; ?>

        </div>

    </div>

    <footer class="footer">
        <p>&copy;2026 Online Bus Ticket Booking System || All Rights Reserved.</p>
    </footer>

</body>

</html>