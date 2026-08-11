<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}
require_once "../db.php";
$user_id = (int)$_SESSION['user_id'];
$stmt = $conn->prepare("SELECT name, profile_image FROM users WHERE user_id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
$stmt->close();
$admin_name = $admin['name'] ?? 'Admin';
$profile_image = !empty($admin['profile_image']) ? $admin['profile_image'] : "default.png";
$today = date('Y-m-d');
$max_date = date('Y-m-d', strtotime('+7 days'));
$message = "";
$type = "";
if (isset($_POST['add_schedule'])) {
    $bus_id = (int)$_POST['bus_id'];
    $from_city = trim($_POST['from_city']);
    $to_city = trim($_POST['to_city']);
    $departure_date = $_POST['departure_date'];
    $departure_time = $_POST['departure_time'];
    $ticket_price = (float)$_POST['ticket_price'];
    $available_seats = (int)$_POST['available_seats'];
    $status = $_POST['status'];

    if ($bus_id <= 0) {
        $message = "Please select a bus.";
        $type = "error";
    } elseif ($from_city === '') {
        $message = "Please enter departure city.";
        $type = "error";
    } elseif ($to_city === '') {
        $message = "Please enter destination city.";
        $type = "error";
    } elseif ($from_city === $to_city) {
        $message = "Departure and destination city cannot be same.";
        $type = "error";
    } elseif (empty($departure_date)) {
        $message = "Please select departure date.";
        $type = "error";
    } elseif (empty($departure_time)) {
        $message = "Please select departure time.";
        $type = "error";
    } elseif ($ticket_price < 0) {
        $message = "Ticket price cannot be negative.";
        $type = "error";
    } elseif ($available_seats < 1) {
        $message = "Available seats must be at least 1.";
        $type = "error";
    } else {
        $stmt = $conn->prepare("INSERT INTO schedule (bus_id, from_city, to_city, departure_date, departure_time, ticket_price, available_seats, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssdis", $bus_id, $from_city, $to_city, $departure_date, $departure_time, $ticket_price, $available_seats, $status);

        if ($stmt->execute()) {
            $message = "Schedule added successfully.";
            $type = "success";
        } else {
            $message = "Failed to add schedule.";
            $type = "error";
        }

        $stmt->close();
    }
}

if (isset($_POST['update_schedule'])) {
    $schedule_id = (int)$_POST['schedule_id'];
    $bus_id = (int)$_POST['bus_id'];
    $from_city = trim($_POST['from_city']);
    $to_city = trim($_POST['to_city']);
    $departure_date = $_POST['departure_date'];
    $departure_time = $_POST['departure_time'];
    $ticket_price = (float)$_POST['ticket_price'];
    $available_seats = (int)$_POST['available_seats'];
    $status = $_POST['status'];

    if ($bus_id <= 0) {
        $message = "Please select a bus.";
        $type = "error";
    } elseif ($from_city === '') {
        $message = "Please select from city.";
        $type = "error";
    } elseif ($to_city === '') {
        $message = "Please select to city.";
        $type = "error";
    } elseif ($from_city === $to_city) {
        $message = "From city and To city cannot be same.";
        $type = "error";
    } elseif (empty($departure_date)) {
        $message = "Please select departure date.";
        $type = "error";
    } elseif ($departure_date < $today || $departure_date > $max_date) {
        $message = "Departure date must be within 7 days.";
        $type = "error";
    } elseif (empty($departure_time)) {
        $message = "Please select departure time.";
        $type = "error";
    } elseif ($ticket_price < 0) {
        $message = "Ticket price cannot be negative.";
        $type = "error";
    } elseif ($available_seats < 0) {
        $message = "Available seats cannot be negative.";
        $type = "error";
    } else {
        $stmt = $conn->prepare("UPDATE schedule SET bus_id=?, from_city=?, to_city=?, departure_date=?, departure_time=?, ticket_price=?, available_seats=?, status=? WHERE schedule_id=?");
        $stmt->bind_param("issssdisi", $bus_id, $from_city, $to_city, $departure_date, $departure_time, $ticket_price, $available_seats, $status, $schedule_id);

        if ($stmt->execute()) {
            $message = "Schedule updated successfully.";
            $type = "success";
        } else {
            $message = "Failed to update schedule.";
            $type = "error";
        }

        $stmt->close();
    }
}
if (isset($_GET['delete'])) {
    $schedule_id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM schedule WHERE schedule_id=?");
    $stmt->bind_param("i", $schedule_id);
    if ($stmt->execute()) {
        $message = "Schedule deleted successfully.";
        $type = "success";
    } else {
        $message = "Failed to delete schedule.";
        $type = "error";
    }
    $stmt->close();
}
$edit_schedule = null;
if (isset($_GET['edit'])) {
    $schedule_id = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM schedule WHERE schedule_id=?");
    $stmt->bind_param("i", $schedule_id);
    $stmt->execute();
    $edit_schedule = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
$buses = $conn->query("SELECT bus_id, bus_number, bus_name, seats FROM bus WHERE status='approved' ORDER BY bus_id DESC");
$routes = $conn->query("SELECT route_id, city_name FROM routes ORDER BY city_name ASC");
$schedules = $conn->query("SELECT s.schedule_id, s.from_city, s.to_city, s.departure_date, s.departure_time, s.ticket_price, s.available_seats, s.status, b.bus_number, b.bus_name FROM schedule s INNER JOIN bus b ON s.bus_id=b.bus_id ORDER BY s.departure_date ASC, s.departure_time ASC, s.schedule_id DESC");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Manage Schedule</title>
    <link rel="stylesheet" href="dashboard_admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        .schedule-page {
            width: 100%
        }

        .schedule-header,
        .schedule-form-box,
        .schedule-table-box {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(0, 0, 0, .08)
        }

        .schedule-header {
            padding: 22px 25px;
            margin-bottom: 20px
        }

        .schedule-header h2 {
            margin: 0;
            color: #1560BD
        }

        .schedule-header p {
            margin: 6px 0 0;
            color: #777
        }

        .message {
            padding: 13px 16px;
            border-radius: 7px;
            margin-bottom: 20px;
            font-weight: bold
        }

        .success {
            background: #e1f7e8;
            color: #16803c
        }

        .error {
            background: #ffe3e3;
            color: #c62828
        }

        .schedule-form-box {
            padding: 22px;
            margin-bottom: 20px
        }

        .schedule-form-box h3 {
            margin: 0 0 20px;
            color: #1560BD
        }

        .schedule-form {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px
        }

        .form-group {
            display: flex;
            flex-direction: column
        }

        .form-group label {
            font-size: 13px;
            font-weight: bold;
            color: #555;
            margin-bottom: 6px
        }

        .form-group input,
        .form-group select {
            height: 42px;
            padding: 0 12px;
            border: 1px solid #ccc;
            border-radius: 7px;
            outline: none;
            font-size: 14px;
            background: #fff
        }

        .form-group input:focus,
        .form-group select:focus {
            border-color: #1560BD
        }

        .form-actions {
            display: flex;
            align-items: end;
            gap: 8px
        }

        .btn {
            height: 42px;
            padding: 0 17px;
            border: 0;
            border-radius: 7px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            font-weight: bold;
            cursor: pointer
        }

        .btn-primary {
            background: #1560BD;
            color: #fff
        }

        .btn-primary:hover {
            background: #0d4e9c
        }

        .btn-cancel {
            background: #eee;
            color: #555
        }

        .schedule-table-box {
            overflow: hidden
        }

        .table-title {
            padding: 20px 22px;
            border-bottom: 1px solid #eee
        }

        .table-title h3 {
            margin: 0;
            color: #1560BD
        }

        .table-wrapper {
            width: 100%;
            overflow-x: auto
        }

        .schedule-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1000px
        }

        .schedule-table th {
            background: #1560BD;
            color: #fff;
            padding: 13px;
            text-align: left;
            font-size: 13px
        }

        .schedule-table td {
            padding: 13px;
            border-bottom: 1px solid #eee;
            color: #444;
            font-size: 14px
        }

        .schedule-table tr:hover {
            background: #f7faff
        }

        .status {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold
        }

        .active {
            background: #dff6e5;
            color: #16803c
        }

        .cancelled {
            background: #ffe1e1;
            color: #d62828
        }

        .completed {
            background: #e4edff;
            color: #1560BD
        }

        .edit,
        .delete {
            display: inline-flex;
            padding: 6px 10px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 12px;
            font-weight: bold;
            margin-right: 5px
        }

        .edit {
            background: #e4edff;
            color: #1560BD
        }

        .delete {
            background: #ffe1e1;
            color: #d62828
        }

        .empty {
            text-align: center;
            color: #999;
            padding: 40px !important
        }

        @media(max-width:1000px) {
            .schedule-form {
                grid-template-columns: repeat(2, 1fr)
            }
        }

        @media(max-width:600px) {
            .schedule-form {
                grid-template-columns: 1fr
            }
        }
    </style>
</head>

<body>
    <div class="header">
        <h2>Welcome Admin</h2>
        <div class="setting">
            <strong><?= htmlspecialchars($admin_name) ?></strong>
            <img src="../uploads/profile/admin/<?= htmlspecialchars($admin_name) ?>/<?= htmlspecialchars($profile_image) ?>" alt="Profile" class="setting-profile" onclick="toggleMenu()">
            <div class="setting-menu" id="settingMenu">
                <a href="profile.php"><i class="fa fa-user"></i> My Profile</a>
                <a href="edit_profile.php"><i class="fa fa-edit"></i> Edit Profile</a>
                <a href="policy.php"><i class="fa fa-file"></i> Manage Policy</a>
                <a href="../logout.php"><i class="fa fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </div>
    <div class="container">
        <div class="sidebar">
            <a href="dashboard.php">Dashboard</a>
            <a href="view_owners.php">Manage Owners</a>
            <a href="drivers.php">Manage Drivers</a>
            <a href="passengers.php">Manage Passengers</a>
            <a href="pending_bus.php">Pending Bus</a>
            <a href="all_bus.php">All Bus</a>
            <a href="assign_driver.php">Assign Driver</a>
            <a href="routes.php">Routes</a>
            <a href="boarding.php">Boarding</a>
            <a href="dropping.php">Dropping</a>
            <a href="schedule.php" class="active">Schedule</a>
            <a href="bookings.php">Bookings</a>
        </div>
        <div class="content">
            <div class="schedule-page">
                <div class="schedule-header">
                    <h2>Manage Schedule</h2>
                    <p>Create and manage bus schedules</p>
                </div>
                <?php if ($message): ?>
                    <div class="message <?= htmlspecialchars($type) ?>"><?= htmlspecialchars($message) ?></div>
                <?php endif; ?>
                <div class="schedule-form-box">
                    <h3><i class="fa <?= $edit_schedule ? 'fa-edit' : 'fa-plus-circle' ?>"></i> <?= $edit_schedule ? 'Edit Schedule' : 'Add Schedule' ?></h3>
                    <form method="POST" class="schedule-form">
                        <?php if ($edit_schedule): ?>
                            <input type="hidden" name="schedule_id" value="<?= ((int)$edit_schedule['schedule_id']) ?>">
                        <?php endif; ?>
                        <div class="form-group">
                            <label>Bus</label>
                            <select name="bus_id" required>
                                <option value="">Select Bus</option>
                                <?php
                                $buses = $conn->query("SELECT bus_id, bus_number, bus_name, seats FROM bus WHERE status='approved' ORDER BY bus_id DESC");
                                while ($bus = $buses->fetch_assoc()):
                                ?>
                                    <option value="<?= (int)$bus['bus_id'] ?>"
                                        <?= $edit_schedule && $edit_schedule['bus_id'] == $bus['bus_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($bus['bus_number'] . ' - ' . $bus['bus_name']) ?>
                                        (<?= (int)$bus['seats'] ?> seats)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>From City</label>
                            <select name="from_city" required>
                                <option value="">Select From City</option>
                                <?php
                                $routes = $conn->query("SELECT route_id, city_name FROM routes ORDER BY city_name ASC");
                                while ($route = $routes->fetch_assoc()):
                                ?>
                                    <option value="<?= htmlspecialchars($route['city_name']) ?>"
                                        <?= ($edit_schedule['from_city'] ?? '') == $route['city_name'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars(ucwords($route['city_name'])) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>To City</label>
                            <select name="to_city" required>
                                <option value="">Select To City</option>
                                <?php
                                $routes = $conn->query("SELECT route_id, city_name FROM routes ORDER BY city_name ASC");
                                while ($route = $routes->fetch_assoc()):
                                ?>
                                    <option value="<?= htmlspecialchars($route['city_name']) ?>"
                                        <?= ($edit_schedule['to_city'] ?? '') == $route['city_name'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars(ucwords($route['city_name'])) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Departure Date</label>
                            <input type="date" name="departure_date"
                                value="<?= htmlspecialchars($edit_schedule['departure_date'] ?? '') ?>"
                                min="<?= date('Y-m-d') ?>"
                                max="<?= date('Y-m-d', strtotime('+7 days')) ?>"
                                required>
                        </div>

                        <div class="form-group">
                            <label>Departure Time</label>
                            <input type="time" name="departure_time"
                                value="<?= htmlspecialchars($edit_schedule['departure_time'] ?? '') ?>"
                                required>
                        </div>

                        <div class="form-group">
                            <label>Ticket Price</label>
                            <input type="number" name="ticket_price"
                                value="<?= htmlspecialchars($edit_schedule['ticket_price'] ?? '') ?>"
                                min="0" step="0.01" required>
                        </div>

                        <div class="form-group">
                            <label>Available Seats</label>
                            <input type="number" name="available_seats"
                                value="<?= htmlspecialchars($edit_schedule['available_seats'] ?? '') ?>"
                                min="0" required>
                        </div>

                        <div class="form-group">
                            <label>Status</label>
                            <select name="status">
                                <option value="active" <?= ($edit_schedule['status'] ?? '') == 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="cancelled" <?= ($edit_schedule['status'] ?? '') == 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                <option value="completed" <?= ($edit_schedule['status'] ?? '') == 'completed' ? 'selected' : '' ?>>Completed</option>
                            </select>
                        </div>
                </div>
                <div class="form-actions">
                    <?php if ($edit_schedule): ?>

                        <button type="submit" name="update_schedule" class="btn btn-primary">
                            <i class="fa fa-save"></i> Update Schedule
                        </button>

                        <a href="schedule.php" class="btn btn-cancel">
                            Cancel
                        </a>

                    <?php else: ?>

                        <button type="submit" name="add_schedule" class="btn btn-primary">
                            <i class="fa fa-plus"></i> Add Schedule
                        </button>

                    <?php endif; ?>
                </div>
                </form>
            </div>
            <div class="schedule-table-box">
                <div class="table-title">
                    <h3><i class="fa fa-calendar"></i> Schedule List</h3>
                </div>
                <div class="table-wrapper">
                    <table class="schedule-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Bus</th>
                                <th>Route</th>
                                <th>Departure Date</th>
                                <th>Departure Time</th>
                                <th>Ticket Price</th>
                                <th>Available Seats</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($schedules && $schedules->num_rows > 0): ?>
                                <?php while ($row = $schedules->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= (int)$row['schedule_id'] ?></td>
                                        <td>
                                            <strong><?= htmlspecialchars($row['bus_number']) ?></strong><br>
                                            <?= htmlspecialchars($row['bus_name']) ?>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars(ucwords($row['from_city'])) ?>
                                            →
                                            <?= htmlspecialchars(ucwords($row['to_city'])) ?>
                                        </td>
                                        <td><?= date("d M Y", strtotime($row['departure_date'])) ?></td>
                                        <td><?= date("h:i A", strtotime($row['departure_time'])) ?></td>
                                        <td>Rs. <?= number_format($row['ticket_price'], 2) ?></td>
                                        <td><strong><?= (int)$row['available_seats'] ?></strong></td>
                                        <td>
                                            <span class="status <?= htmlspecialchars($row['status']) ?>">
                                                <?= ucfirst(htmlspecialchars($row['status'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="schedule.php?edit=<?= (int)$row['schedule_id'] ?>" class="edit">
                                                <i class="fa fa-edit"></i> Edit
                                            </a>
                                            <a href="schedule.php?delete=<?= (int)$row['schedule_id'] ?>" class="delete" onclick="return confirm('Are you sure you want to delete this schedule?')">
                                                <i class="fa fa-trash"></i> Delete
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="empty"><i class="fa fa-calendar"></i><br><br>No schedules found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    </div>
    <script>
        function toggleMenu() {
            document.getElementById("settingMenu").classList.toggle("show");
        }
        window.onclick = function(e) {
            if (!e.target.closest(".setting")) {
                document.getElementById("settingMenu").classList.remove("show");
            }
        };
    </script>
</body>

</html>