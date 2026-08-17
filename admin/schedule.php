<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}
require_once "../db.php";
$user_id = (int)$_SESSION['user_id'];
$stmt = $conn->prepare("SELECT name,profile_image FROM users WHERE user_id=?");
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
if (isset($_POST['add_schedules']) || isset($_POST['update_schedules'])) {
    $edit = isset($_POST['update_schedules']);
    $schedule_id = (int)($_POST['schedule_id'] ?? 0);
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
    } elseif ($from_city === '') {
        $message = "Please select from city.";
    } elseif ($to_city === '') {
        $message = "Please select to city.";
    } elseif ($from_city === $to_city) {
        $message = "From city and To city cannot be same.";
    } elseif (empty($departure_date)) {
        $message = "Please select departure date.";
    } elseif ($departure_date < $today || $departure_date > $max_date) {
        $message = "Departure date must be within 7 days.";
    } elseif (empty($departure_time)) {
        $message = "Please select departure time.";
    } elseif ($ticket_price < 0) {
        $message = "Ticket price cannot be negative.";
    } elseif ($available_seats < 0) {
        $message = "Available seats cannot be negative.";
    } else {
        if ($edit) {
            $stmt = $conn->prepare("UPDATE schedules SET bus_id=?,from_city=?,to_city=?,departure_date=?,departure_time=?,ticket_price=?,available_seats=?,status=? WHERE schedule_id=?");
            $stmt->bind_param("issssdisi", $bus_id, $from_city, $to_city, $departure_date, $departure_time, $ticket_price, $available_seats, $status, $schedule_id);
        } else {
            if ($available_seats < 1) {
                $message = "Available seats must be at least 1.";
            } else {
                $stmt = $conn->prepare("INSERT INTO schedules (bus_id,from_city,to_city,departure_date,departure_time,ticket_price,available_seats,status) VALUES (?,?,?,?,?,?,?,?)");
                $stmt->bind_param("issssdis", $bus_id, $from_city, $to_city, $departure_date, $departure_time, $ticket_price, $available_seats, $status);
            }
        }
        if ($message === "") {
            if ($stmt->execute()) {
                $message = $edit ? "schedules updated successfully." : "schedules added successfully.";
                $type = "success";
            } else {
                $message = $edit ? "Failed to update schedules." : "Failed to add schedules.";
                $type = "error";
            }
            $stmt->close();
        } else {
            $type = "error";
        }
    }
    if ($message !== "" && $type === "") {
        $type = "error";
    }
}
if (isset($_GET['delete'])) {
    $schedule_id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM schedules WHERE schedule_id=?");
    $stmt->bind_param("i", $schedule_id);
    $message = $stmt->execute() ? "schedules deleted successfully." : "Failed to delete schedules.";
    $type = $stmt->execute() ? "success" : "error";
    $stmt->close();
}
$edit_schedules = null;
if (isset($_GET['edit'])) {
    $schedule_id = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM schedules WHERE schedule_id=?");
    $stmt->bind_param("i", $schedule_id);
    $stmt->execute();
    $edit_schedules = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
$buses = $conn->query("SELECT bus_id,bus_number,bus_name,seats FROM bus WHERE status='approved' ORDER BY bus_id DESC");
$routes = $conn->query("SELECT route_id,city_name FROM routes ORDER BY city_name ASC");
$schedules = $conn->query("SELECT s.schedule_id,s.from_city,s.to_city,s.departure_date,s.departure_time,s.ticket_price,s.available_seats,s.status,b.bus_number,b.bus_name FROM schedules s INNER JOIN bus b ON s.bus_id=b.bus_id ORDER BY s.departure_date ASC,s.departure_time ASC,s.schedule_id DESC");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Manage schedules</title>
    <link rel="stylesheet" href="schedule.css">
    <link rel="stylesheet" href="side.css">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

</head>

<body>

    <?php include "admin_header.php"; ?>
    <div class="content">
        <div class="schedules-page">
            <div class="schedules-header">
                <h2>Manage schedules</h2>
                <p>Create and manage bus schedules</p>
            </div>
            <?php if ($message): ?><div class="message <?= htmlspecialchars($type) ?>"><?= htmlspecialchars($message) ?></div><?php endif; ?>
            <div class="schedules-form-box">
                <h3><i class="fa <?= $edit_schedules ? 'fa-edit' : 'fa-plus-circle' ?>"></i> <?= $edit_schedules ? 'Edit schedules' : 'Add schedules' ?></h3>
                <form method="POST" class="schedules-form">
                    <?php if ($edit_schedules): ?><input type="hidden" name="schedule_id" value="<?= ((int)$edit_schedules['schedule_id']) ?>"><?php endif; ?>
                    <div class="form-group">
                        <label>Bus</label>
                        <select name="bus_id" required>
                            <option value="">Select Bus</option>
                            <?php while ($bus = $buses->fetch_assoc()): ?>
                                <option value="<?= ($bus['bus_id']) ?>" <?= $edit_schedules && $edit_schedules['bus_id'] == $bus['bus_id'] ? 'selected' : '' ?>><?= htmlspecialchars($bus['bus_number'] . ' - ' . $bus['bus_name']) ?> (<?= ($bus['seats']) ?> seats)</option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>From City</label>
                        <select name="from_city" required>
                            <option value="">Select From City</option>
                            <?php while ($route = $routes->fetch_assoc()): ?>
                                <option value="<?= htmlspecialchars($route['city_name']) ?>" <?= ($edit_schedules['from_city'] ?? '') == $route['city_name'] ? 'selected' : '' ?>><?= htmlspecialchars(ucwords($route['city_name'])) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>To City</label>
                        <select name="to_city" required>
                            <option value="">Select To City</option>
                            <?php
                            $routes2 = $conn->query("SELECT route_id,city_name FROM routes ORDER BY city_name ASC");
                            while ($route = $routes2->fetch_assoc()):
                            ?>
                                <option value="<?= htmlspecialchars($route['city_name']) ?>" <?= ($edit_schedules['to_city'] ?? '') == $route['city_name'] ? 'selected' : '' ?>><?= htmlspecialchars(ucwords($route['city_name'])) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Departure Date</label>
                        <input type="date" name="departure_date" value="<?= htmlspecialchars($edit_schedules['departure_date'] ?? '') ?>" min="<?= $today ?>" max="<?= $max_date ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Departure Time</label>
                        <input type="time" name="departure_time" value="<?= htmlspecialchars($edit_schedules['departure_time'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Ticket Price</label>
                        <input type="number" name="ticket_price" value="<?= htmlspecialchars($edit_schedules['ticket_price'] ?? '') ?>" min="0" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label>Available Seats</label>
                        <input type="number" name="available_seats" value="<?= htmlspecialchars($edit_schedules['available_seats'] ?? '') ?>" min="0" required>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="active" <?= ($edit_schedules['status'] ?? '') == 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="cancelled" <?= ($edit_schedules['status'] ?? '') == 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                            <option value="completed" <?= ($edit_schedules['status'] ?? '') == 'completed' ? 'selected' : '' ?>>Completed</option>
                        </select>
                    </div>
                    <div class="form-actions">
                        <?php if ($edit_schedules): ?>
                            <button type="submit" name="update_schedules" class="btn btn-primary"><i class="fa fa-save"></i> Update schedules</button>
                            <a href="schedules.php" class="btn btn-cancel">Cancel</a>
                        <?php else: ?>
                            <button type="submit" name="add_schedules" class="btn btn-primary"><i class="fa fa-plus"></i> Add schedules</button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            <div class="schedules-table-box">
                <div class="table-title">
                    <h3><i class="fa fa-calendar"></i> schedules List</h3>
                </div>
                <div class="table-wrapper">
                    <table class="schedules-table">
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
                                        <td><?= ($row['schedule_id']) ?></td>
                                        <td><strong><?= htmlspecialchars($row['bus_number']) ?></strong><br><?= htmlspecialchars($row['bus_name']) ?></td>
                                        <td><?= htmlspecialchars(ucwords($row['from_city'])) ?> → <?= htmlspecialchars(ucwords($row['to_city'])) ?></td>
                                        <td><?= date("d M Y", strtotime($row['departure_date'])) ?></td>
                                        <td><?= date("h:i A", strtotime($row['departure_time'])) ?></td>
                                        <td>Rs. <?= number_format($row['ticket_price'], 2) ?></td>
                                        <td><strong><?= ($row['available_seats']) ?></strong></td>
                                        <td><span class="status <?= htmlspecialchars($row['status']) ?>"><?= ucfirst(htmlspecialchars($row['status'])) ?></span></td>
                                        <td>
                                            <a href="schedules.php?edit=<?= ($row['schedule_id']) ?>" class="edit"><i class="fa fa-edit"></i> Edit</a>
                                            <a href="schedules.php?delete=<?= ($row['schedule_id']) ?>" class="delete" onclick="return confirm('Are you sure you want to delete this schedules?')"><i class="fa fa-trash"></i> Delete</a>
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

    <script>
        function toggleMenu() {
            document.getElementById("settingMenu").classList.toggle("show")
        }
        window.onclick = function(e) {
            if (!e.target.closest(".setting")) document.getElementById("settingMenu").classList.remove("show")
        }
    </script>
</body>

</html>