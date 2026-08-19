<?php
session_start();
require_once "../db.php";
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}
$message = "";
$message_type = "";
$edit_route = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM popular_routes WHERE popular_id=? LIMIT 1");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $edit_route = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
$buses = [];
$result = $conn->query("SELECT bus_id,bus_name,bus_number,seats FROM bus WHERE status='approved' ORDER BY bus_name");
while ($result && $row = $result->fetch_assoc()) $buses[] = $row;
$cities = [];
$result = $conn->query("SELECT city_name FROM routes ORDER BY city_name");
while ($result && $row = $result->fetch_assoc()) $cities[] = $row['city_name'];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $edit_id = (int)($_POST['edit_id'] ?? 0);
    $bus_id = (int)($_POST['bus_id'] ?? 0);
    $from_city = trim($_POST['from_city'] ?? '');
    $to_city = trim($_POST['to_city'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $departure_date = trim($_POST['departure_date'] ?? '');
    $departure_time = date("H:i", strtotime($_POST['departure_time'] ?? ''));
    $status = $_POST['status'] ?? 'active';

    if (!$bus_id || !$from_city || !$to_city || $price < 0 || !$departure_date || !$departure_time) {
        $message = "Please fill all required fields.";
        $message_type = "error";
    } elseif (strcasecmp($from_city, $to_city) === 0) {
        $message = "From and To city cannot be same.";
        $message_type = "error";
    } elseif (!in_array($status, ['active', 'inactive'], true)) {
        $message = "Invalid status.";
        $message_type = "error";
    } else {
        $stmt = $conn->prepare("SELECT popular_id FROM popular_routes WHERE LOWER(TRIM(from_city))=LOWER(TRIM(?)) AND LOWER(TRIM(to_city))=LOWER(TRIM(?)) AND departure_date=? AND TIME(departure_time)=? AND popular_id!=? LIMIT 1");
        $stmt->bind_param("ssssi", $from_city, $to_city, $departure_date, $departure_time, $edit_id);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        if ($exists) {
            $message = "This popular route already exists.";
            $message_type = "error";
        } else {
            $image = $edit_route['image'] ?? 'Bus Image/b1.jpg';
            if (isset($_FILES['route_image']) && $_FILES['route_image']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['route_image']['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
                    $message = "Invalid image format.";
                    $message_type = "error";
                } else {
                    $dir = "../Bus Image/";
                    if (!is_dir($dir)) mkdir($dir, 0777, true);
                    $file = time() . "_" . uniqid() . "." . $ext;
                    if (move_uploaded_file($_FILES['route_image']['tmp_name'], $dir . $file)) {
                        if ($edit_id && !empty($image) && $image !== "Bus Image/b1.jpg" && file_exists("../" . $image)) unlink("../" . $image);
                        $image = "Bus Image/" . $file;
                    } else {
                        $message = "Image upload failed.";
                        $message_type = "error";
                    }
                }
            }

            if ($message_type !== "error") {
                $bus_stmt = $conn->prepare("SELECT seats FROM bus WHERE bus_id=? LIMIT 1");
                $bus_stmt->bind_param("i", $bus_id);
                $bus_stmt->execute();
                $bus = $bus_stmt->get_result()->fetch_assoc();
                $bus_stmt->close();
                $total_seats = (int)($bus['seats'] ?? 0);

                if ($edit_id) {
                    $stmt = $conn->prepare("SELECT bus_id,from_city,to_city,departure_date,departure_time FROM popular_routes WHERE popular_id=?");
                    $stmt->bind_param("i", $edit_id);
                    $stmt->execute();
                    $old = $stmt->get_result()->fetch_assoc();
                    $stmt->close();

                    $stmt = $conn->prepare("UPDATE popular_routes SET bus_id=?,from_city=?,to_city=?,departure_date=?,departure_time=?,price=?,image=?,status=? WHERE popular_id=?");
                    $stmt->bind_param("issssdssi", $bus_id, $from_city, $to_city, $departure_date, $departure_time, $price, $image, $status, $edit_id);
                    $success = $stmt->execute();
                    $stmt->close();

                    if ($success && $old) {
                        $stmt = $conn->prepare("SELECT schedule_id FROM schedules WHERE bus_id=? AND LOWER(TRIM(from_city))=LOWER(TRIM(?)) AND LOWER(TRIM(to_city))=LOWER(TRIM(?)) AND departure_date=? AND TIME(departure_time)=? LIMIT 1");
                        $stmt->bind_param("issss", $old['bus_id'], $old['from_city'], $old['to_city'], $old['departure_date'], $old['departure_time']);
                        $stmt->execute();
                        $old_schedule = $stmt->get_result()->fetch_assoc();
                        $stmt->close();

                        if ($old_schedule) {
                            $stmt = $conn->prepare("UPDATE schedules SET bus_id=?,from_city=?,to_city=?,departure_date=?,departure_time=?,ticket_price=?,available_seats=?,status=? WHERE schedule_id=?");
                            $stmt->bind_param("issssdisi", $bus_id, $from_city, $to_city, $departure_date, $departure_time, $price, $total_seats, $status, $old_schedule['schedule_id']);
                            $stmt->execute();
                            $stmt->close();
                        }
                    }
                } else {
                    $stmt = $conn->prepare("INSERT INTO popular_routes(bus_id,from_city,to_city,departure_date,departure_time,price,image,status) VALUES(?,?,?,?,?,?,?,?)");
                    $stmt->bind_param("issssdss", $bus_id, $from_city, $to_city, $departure_date, $departure_time, $price, $image, $status);
                    $success = $stmt->execute();
                    $stmt->close();

                    if ($success && $status === 'active') {
                        $stmt = $conn->prepare("SELECT schedule_id FROM schedules WHERE bus_id=? AND LOWER(TRIM(from_city))=LOWER(TRIM(?)) AND LOWER(TRIM(to_city))=LOWER(TRIM(?)) AND departure_date=? AND TIME(departure_time)=TIME(?) LIMIT 1");
                        $stmt->bind_param("issss", $bus_id, $from_city, $to_city, $departure_date, $departure_time);
                        $stmt->execute();
                        $schedule_exists = $stmt->get_result()->fetch_assoc();
                        $stmt->close();

                        if ($schedule_exists) {
                            $stmt = $conn->prepare("UPDATE schedules SET ticket_price=?,available_seats=?,status='active' WHERE schedule_id=?");
                            $stmt->bind_param("dii", $price, $total_seats, $schedule_exists['schedule_id']);
                            $stmt->execute();
                            $stmt->close();
                        } else {
                            $stmt = $conn->prepare("INSERT INTO schedules(bus_id,from_city,to_city,departure_date,departure_time,ticket_price,available_seats,status) VALUES(?,?,?,?,?,?,?,'active')");
                            $stmt->bind_param("issssdi", $bus_id, $from_city, $to_city, $departure_date, $departure_time, $price, $total_seats);
                            $stmt->execute();
                            $stmt->close();
                        }
                    }
                }

                if ($success) {
                    header("Location: popular_routes.php");
                    exit;
                }
                $message = "Database error.";
                $message_type = "error";
            }
        }
    }
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if ($id > 0) {
        $stmt = $conn->prepare("SELECT image,bus_id,from_city,to_city,departure_date,departure_time FROM popular_routes WHERE popular_id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $route = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($route) {
            $stmt = $conn->prepare("DELETE FROM popular_routes WHERE popular_id=?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("DELETE FROM schedules WHERE bus_id=? AND LOWER(TRIM(from_city))=LOWER(TRIM(?)) AND LOWER(TRIM(to_city))=LOWER(TRIM(?)) AND departure_date=? AND TIME(departure_time)=TIME(?)");
            $stmt->bind_param("issss", $route['bus_id'], $route['from_city'], $route['to_city'], $route['departure_date'], $route['departure_time']);
            $stmt->execute();
            $stmt->close();

            if (!empty($route['image']) && $route['image'] !== "Bus Image/b1.jpg" && file_exists("../" . $route['image'])) unlink("../" . $route['image']);
        }
    }
    header("Location: popular_routes.php");
    exit;
}

$routes = [];
$result = $conn->query("SELECT pr.*,b.bus_name,b.bus_number,b.seats FROM popular_routes pr LEFT JOIN bus b ON pr.bus_id=b.bus_id ORDER BY pr.popular_id DESC");
while ($result && $row = $result->fetch_assoc()) $routes[] = $row;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Manage Popular Routes</title>
    <link rel="stylesheet" href="popular_routes.css">
    <link rel="stylesheet" href="side.css">
</head>

<body>
    <?php include "admin_header.php"; ?>
    <div class="content">
        <div class="route-box">
            <h2><?= $edit_route ? 'Edit Popular Route' : 'Manage Popular Routes' ?></h2>
            <?php if ($message): ?><div class="message <?= htmlspecialchars($message_type) ?>"><?= htmlspecialchars($message) ?></div><?php endif; ?>
            <form method="POST" enctype="multipart/form-data">
                <?php if ($edit_route): ?><input type="hidden" name="edit_id" value="<?= (int)$edit_route['popular_id'] ?>"><?php endif; ?>
                <div class="form-row">
                    <div class="form-group"><label>Bus</label><select name="bus_id" required>
                            <option value="">Select Bus</option><?php foreach ($buses as $bus): ?><option value="<?= (int)$bus['bus_id'] ?>" <?= (($edit_route['bus_id'] ?? '') == $bus['bus_id']) ? 'selected' : '' ?>><?= htmlspecialchars($bus['bus_name']) ?> - <?= htmlspecialchars($bus['bus_number']) ?> - <?= (int)$bus['seats'] ?> Seats</option><?php endforeach; ?>
                        </select></div>
                    <div class="form-group"><label>From City</label><select name="from_city" required>
                            <option value="">Select From City</option><?php foreach ($cities as $city): ?><option value="<?= htmlspecialchars($city) ?>" <?= (($edit_route['from_city'] ?? '') === $city) ? 'selected' : '' ?>><?= htmlspecialchars(ucwords(strtolower($city))) ?></option><?php endforeach; ?>
                        </select></div>
                    <div class="form-group"><label>To City</label><select name="to_city" required>
                            <option value="">Select To City</option><?php foreach ($cities as $city): ?><option value="<?= htmlspecialchars($city) ?>" <?= (($edit_route['to_city'] ?? '') === $city) ? 'selected' : '' ?>><?= htmlspecialchars(ucwords(strtolower($city))) ?></option><?php endforeach; ?>
                        </select></div>
                    <div class="form-group"><label>Price</label><input type="number" name="price" value="<?= htmlspecialchars($edit_route['price'] ?? '') ?>" min="0" step="100" required></div>
                    <div class="form-group"><label>Departure Date</label><input type="date" name="departure_date" value="<?= htmlspecialchars($edit_route['departure_date'] ?? '') ?>" min="<?= date('Y-m-d') ?>" required></div>
                    <div class="form-group"><label>Departure Time</label><input type="time" name="departure_time" value="<?= !empty($edit_route['departure_time']) ? date('H:i', strtotime($edit_route['departure_time'])) : '' ?>" required></div>
                    <div class="form-group"><label>Route Image</label><input type="file" name="route_image" accept=".jpg,.jpeg,.png,.gif,.webp"></div>
                    <div class="form-group"><label>Status</label><select name="status">
                            <option value="active" <?= (($edit_route['status'] ?? '') === 'active') ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= (($edit_route['status'] ?? '') === 'inactive') ? 'selected' : '' ?>>Inactive</option>
                        </select></div>
                </div>
                <button type="submit" class="add-btn"><?= $edit_route ? 'Update Popular Route' : 'Assign Popular Route' ?></button>
                <?php if ($edit_route): ?><a href="popular_routes.php" class="add-btn">Cancel</a><?php endif; ?>
            </form>
        </div>
        <div class="route-box">
            <h2>Assigned Popular Routes</h2>
            <?php if ($routes): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Bus</th>
                            <th>Image</th>
                            <th>From</th>
                            <th>To</th>
                            <th>Price</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Total Seats</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($routes as $route): ?>
                            <tr>
                                <td><?= (int)$route['popular_id'] ?></td>
                                <td><?= htmlspecialchars($route['bus_name'] ?? '-') ?><br><?= htmlspecialchars($route['bus_number'] ?? '-') ?></td>
                                <td><img src="../<?= htmlspecialchars($route['image'] ?: 'Bus Image/b1.jpg') ?>" class="route-thumb"></td>
                                <td><?= htmlspecialchars($route['from_city']) ?></td>
                                <td><?= htmlspecialchars($route['to_city']) ?></td>
                                <td>Rs. <?= number_format((float)$route['price'], 2) ?></td>
                                <td><?= date('d M Y', strtotime($route['departure_date'])) ?></td>
                                <td><?= date('H:i', strtotime($route['departure_time'])) ?></td>
                                <td><?= (int)$route['seats'] ?></td>
                                <td><span class="badge <?= $route['status'] === 'active' ? 'badge-active' : 'badge-inactive' ?>"><?= ucfirst($route['status']) ?></span></td>
                                <td><a href="?edit=<?= (int)$route['popular_id'] ?>" class="edit-btn">Edit</a> <a href="?delete=<?= (int)$route['popular_id'] ?>" class="delete-btn" onclick="return confirm('Delete this route?')">Delete</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?><div class="no-route">No popular routes assigned yet.</div><?php endif; ?>
        </div>
    </div>
    <script>
        const from = document.querySelector('[name="from_city"]'),
            to = document.querySelector('[name="to_city"]');

        function check() {
            [...to.options].forEach(o => o.disabled = o.value && o.value.toLowerCase() === from.value.toLowerCase());
            if (to.value.toLowerCase() === from.value.toLowerCase()) to.value = '';
        }
        from.addEventListener('change', check);
        check();
    </script>
</body>

</html>