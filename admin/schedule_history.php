<?php
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
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
$history = null;
$error = '';
if (isset($_GET['edit'])) {
    $history_id = (int)$_GET['edit'];
    if ($history_id <= 0) {
        header("Location: schedule_history.php");
        exit();
    }
    $stmt = $conn->prepare("SELECT * FROM schedule_history WHERE history_id=? LIMIT 1");
    $stmt->bind_param("i", $history_id);
    $stmt->execute();
    $history = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$history) {
        header("Location: schedule_history.php");
        exit();
    }
}
if (isset($_POST['update_history'])) {
    $history_id = (int)($_POST['history_id'] ?? 0);
    $bus_id = (int)($_POST['bus_id'] ?? 0);
    $from_city = trim($_POST['from_city'] ?? '');
    $to_city = trim($_POST['to_city'] ?? '');
    $departure_date = $_POST['departure_date'] ?? '';
    $departure_time = $_POST['departure_time'] ?? '';
    $ticket_price = (float)($_POST['ticket_price'] ?? 0);
    $status = 'active';
    $available_seats = 0;
    if ($history_id <= 0) {
        $error = "Invalid history record.";
    } elseif ($bus_id <= 0) {
        $error = "Please select a bus.";
    } elseif ($from_city === '' || $to_city === '') {
        $error = "Please select route.";
    } elseif (strcasecmp($from_city, $to_city) === 0) {
        $error = "From city and To city cannot be same.";
    } elseif (empty($departure_date) || empty($departure_time)) {
        $error = "Please select departure date and time.";
    } elseif ($departure_date < $today || $departure_date > $max_date) {
        $error = "Departure date must be between today and the next 7 days.";
    } elseif ($ticket_price < 500) {
        $error = "Ticket price must be at least Rs. 500.";
    } else {
        $stmt = $conn->prepare("SELECT seats FROM bus WHERE bus_id=? AND status='approved' LIMIT 1");
        $stmt->bind_param("i", $bus_id);
        $stmt->execute();
        $bus_data = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$bus_data) {
            $error = "Selected bus is not available or not approved.";
        } else {
            $available_seats = (int)$bus_data['seats'];
            if ($available_seats <= 0) {
                $error = "Selected bus has invalid seat capacity.";
            } else {
                $departure_timestamp = strtotime($departure_date . ' ' . $departure_time);
                if ($departure_timestamp === false || $departure_timestamp <= time() + 1800) {
                    $error = "Departure time must be more than 30 minutes from current time.";
                } else {
                    $conn->begin_transaction();
                    try {
                        $stmt = $conn->prepare("UPDATE schedule_history SET bus_id=?, from_city=?, to_city=?, departure_date=?, departure_time=?, ticket_price=?, available_seats=?, status=? WHERE history_id=?");
                        $stmt->bind_param("issssdisi", $bus_id, $from_city, $to_city, $departure_date, $departure_time, $ticket_price, $available_seats, $status, $history_id);
                        if (!$stmt->execute()) {
                            throw new Exception("Failed to update history.");
                        }
                        $stmt->close();
                        $stmt = $conn->prepare("SELECT * FROM schedule_history WHERE history_id=? LIMIT 1");
                        $stmt->bind_param("i", $history_id);
                        $stmt->execute();
                        $updated = $stmt->get_result()->fetch_assoc();
                        $stmt->close();
                        if (!$updated) {
                            throw new Exception("Updated history record not found.");
                        }
                        $stmt = $conn->prepare("INSERT INTO schedules (schedule_id, bus_id, from_city, to_city, departure_date, departure_time, ticket_price, available_seats, status) VALUES (?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE bus_id=VALUES(bus_id), from_city=VALUES(from_city), to_city=VALUES(to_city), departure_date=VALUES(departure_date), departure_time=VALUES(departure_time), ticket_price=VALUES(ticket_price), available_seats=VALUES(available_seats), status='active'");
                        $stmt->bind_param("iisssdiss", $updated['schedule_id'], $updated['bus_id'], $updated['from_city'], $updated['to_city'], $updated['departure_date'], $updated['departure_time'], $updated['ticket_price'], $available_seats, $status);
                        if (!$stmt->execute()) {
                            throw new Exception("Failed to restore schedule.");
                        }
                        $stmt->close();
                        $stmt = $conn->prepare("DELETE FROM schedule_history WHERE history_id=?");
                        $stmt->bind_param("i", $history_id);
                        if (!$stmt->execute()) {
                            throw new Exception("Failed to remove history record.");
                        }
                        $stmt->close();
                        $conn->commit();
                        $_SESSION['schedule_message'] = "Schedule restored successfully.";
                        $_SESSION['schedule_type'] = "success";
                        header("Location: schedule.php?edit=" . (int)$updated['schedule_id']);
                        exit();
                    } catch (Exception $e) {
                        $conn->rollback();
                        $error = $e->getMessage();
                    }
                }
            }
        }
    }
}
$buses = $conn->query("SELECT bus_id, bus_number, bus_name, seats FROM bus WHERE status='approved' ORDER BY bus_id DESC");
$history_result = $conn->query("SELECT h.*, b.bus_number, b.bus_name, b.seats AS bus_total_seats FROM schedule_history h LEFT JOIN bus b ON h.bus_id=b.bus_id ORDER BY h.history_id DESC");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Schedule History</title>
    <link rel="stylesheet" href="schedule.css">
    <link rel="stylesheet" href="side.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
</head>

<body>
    <?php include "admin_header.php"; ?>
    <div class="content">
        <div class="schedules-page">
            <div class="schedules-header">
                <h2>Schedule History</h2>
                <p>Previous bus schedules</p>
            </div>
            <?php if (!empty($error)): ?>
                <div class="message error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($history): ?>
                <div class="schedules-form-box">
                    <h3><i class="fa fa-edit"></i> Edit History Schedule</h3>
                    <form method="POST" class="schedules-form">
                        <input type="hidden" name="history_id" value="<?= (int)$history['history_id'] ?>">
                        <div class="form-group">
                            <label>Bus</label>
                            <select name="bus_id" required>
                                <option value="">Select Bus</option>
                                <?php if ($buses && $buses->num_rows > 0): ?>
                                    <?php while ($bus = $buses->fetch_assoc()): ?>
                                        <option value="<?= (int)$bus['bus_id'] ?>" <?= (int)$history['bus_id'] === (int)$bus['bus_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($bus['bus_number'] . ' - ' . $bus['bus_name']) ?> (<?= (int)$bus['seats'] ?> seats)
                                        </option>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>From City</label>
                            <input type="text" name="from_city" value="<?= htmlspecialchars($history['from_city'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label>To City</label>
                            <input type="text" name="to_city" value="<?= htmlspecialchars($history['to_city'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Departure Date</label>
                            <input type="date" name="departure_date" value="<?= htmlspecialchars($history['departure_date'] ?? '') ?>" min="<?= $today ?>" max="<?= $max_date ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Departure Time</label>
                            <input type="time" name="departure_time" value="<?= htmlspecialchars($history['departure_time'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Ticket Price</label>
                            <input type="number" name="ticket_price" value="<?= htmlspecialchars($history['ticket_price'] ?? '') ?>" min="500" step="100" required>
                        </div>
                        <div class="form-group">
                            <label>Available Seats</label>
                            <input type="text" value="<?= (int)($history['available_seats'] ?? 0) ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <input type="text" value="Active" readonly>
                        </div>
                        <div class="form-actions">
                            <button type="submit" name="update_history" class="btn btn-primary">
                                <i class="fa fa-save"></i> Save & Restore
                            </button>
                            <a href="schedule_history.php" class="btn btn-cancel">Cancel</a>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
            <div class="schedules-table-box">
                <div class="table-title">
                    <h3><i class="fa fa-history"></i> Schedule History List</h3>
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
                                <th>Moved At</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($history_result && $history_result->num_rows > 0): ?>
                                <?php while ($row = $history_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= (int)$row['schedule_id'] ?></td>
                                        <td>
                                            <strong><?= htmlspecialchars($row['bus_number'] ?? 'N/A') ?></strong>
                                            <br>
                                            <?= htmlspecialchars($row['bus_name'] ?? '') ?>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars(ucwords($row['from_city'] ?? '')) ?>
                                            <i class="fa fa-long-arrow-right"></i>
                                            <?= htmlspecialchars(ucwords($row['to_city'] ?? '')) ?>
                                        </td>
                                        <td><?= !empty($row['departure_date']) ? date("d M Y", strtotime($row['departure_date'])) : '-' ?></td>
                                        <td><?= !empty($row['departure_time']) ? date("h:i A", strtotime($row['departure_time'])) : '-' ?></td>
                                        <td>Rs. <?= number_format((float)$row['ticket_price'], 2) ?></td>
                                        <td><strong><?= (int)($row['available_seats'] ?? 0) ?></strong></td>
                                        <td>
                                            <span class="status <?= htmlspecialchars($row['status'] ?? '') ?>">
                                                <?= ucfirst(htmlspecialchars($row['status'] ?? '')) ?>
                                            </span>
                                        </td>
                                        <td><?= !empty($row['moved_at']) ? date("d M Y h:i A", strtotime($row['moved_at'])) : '-' ?></td>
                                        <td>
                                            <a href="schedule_history.php?edit=<?= (int)$row['history_id'] ?>" class="edit">
                                                <i class="fa fa-edit"></i> Edit
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="10" class="empty">
                                        <i class="fa fa-history"></i>
                                        <br><br>
                                        No schedule history found.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>

</html>