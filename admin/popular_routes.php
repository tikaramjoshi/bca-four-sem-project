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
    $stmt = $conn->prepare("SELECT * FROM popular_routes WHERE popular_id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $edit_route = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $edit_id = (int)($_POST['edit_id'] ?? 0);
    $from_city = trim($_POST['from_city'] ?? '');
    $to_city = trim($_POST['to_city'] ?? '');
    $price = trim($_POST['price'] ?? '');
    $status = trim($_POST['status'] ?? 'active');
    if ($from_city === "" || $to_city === "" || $price === "") {
        $message = "Please fill all required fields.";
        $message_type = "error";
    } elseif (strcasecmp($from_city, $to_city) === 0) {
        $message = "From and To city cannot be same.";
        $message_type = "error";
    } elseif (!is_numeric($price) || $price < 0) {
        $message = "Please enter a valid price.";
        $message_type = "error";
    } elseif (!in_array($status, ['active', 'inactive'])) {
        $message = "Invalid status.";
        $message_type = "error";
    } else {
        $check = $conn->prepare("SELECT popular_id FROM popular_routes WHERE LOWER(from_city)=LOWER(?) AND LOWER(to_city)=LOWER(?) AND popular_id!=? LIMIT 1");
        $check->bind_param("ssi", $from_city, $to_city, $edit_id);
        $check->execute();
        $check->store_result();
        if ($check->num_rows > 0) {
            $message = "This popular route already exists.";
            $message_type = "error";
            $check->close();
        } else {
            $check->close();
            $image = $edit_route['image'] ?? "Bus Image/b1.jpg";
            if (isset($_FILES['route_image']) && $_FILES['route_image']['error'] === UPLOAD_ERR_OK) {
                $extension = strtolower(pathinfo($_FILES['route_image']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                if (in_array($extension, $allowed)) {
                    $new_file_name = time() . "_" . uniqid() . "." . $extension;
                    $upload_dir = "../Bus Image/";
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    if (move_uploaded_file($_FILES['route_image']['tmp_name'], $upload_dir . $new_file_name)) {
                        if ($edit_id > 0 && !empty($image) && $image !== "Bus Image/b1.jpg") {
                            $old_image = "../" . $image;
                            if (file_exists($old_image)) {
                                unlink($old_image);
                            }
                        }
                        $image = "Bus Image/" . $new_file_name;
                    } else {
                        $message = "Image upload failed.";
                        $message_type = "error";
                    }
                } else {
                    $message = "Invalid image format.";
                    $message_type = "error";
                }
            }
            if ($message_type !== "error") {
                $price = (float)$price;
                if ($edit_id > 0) {
                    $stmt = $conn->prepare("UPDATE popular_routes SET from_city=?,to_city=?,price=?,image=?,status=? WHERE popular_id=?");
                    $stmt->bind_param("ssdssi", $from_city, $to_city, $price, $image, $status, $edit_id);
                    $message = $stmt->execute() ? "Popular route updated successfully." : "Error updating popular route.";
                    $message_type = $stmt->execute() ? "success" : "error";
                } else {
                    $stmt = $conn->prepare("INSERT INTO popular_routes (from_city,to_city,price,image,status) VALUES (?,?,?,?,?)");
                    $stmt->bind_param("ssdss", $from_city, $to_city, $price, $image, $status);
                    $message = $stmt->execute() ? "Popular route assigned successfully." : "Error adding popular route.";
                    $message_type = $stmt->execute() ? "success" : "error";
                }
                $stmt->close();
                if ($message_type === "success") {
                    header("Location: popular_routes.php");
                    exit;
                }
            }
        }
    }
}
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if ($id > 0) {
        $stmt = $conn->prepare("SELECT image FROM popular_routes WHERE popular_id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $route = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $stmt = $conn->prepare("DELETE FROM popular_routes WHERE popular_id=?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute() && $route && !empty($route['image']) && $route['image'] !== "Bus Image/b1.jpg") {
            $image_path = "../" . $route['image'];
            if (file_exists($image_path)) {
                unlink($image_path);
            }
        }
        $stmt->close();
    }
    header("Location: popular_routes.php");
    exit;
}
$result = $conn->query("SELECT * FROM popular_routes ORDER BY popular_id DESC");
$city_result = $conn->query("SELECT city_name FROM routes ORDER BY city_name ASC");
$cities = [];
while ($city = $city_result->fetch_assoc()) {
    $cities[] = $city['city_name'];
}
$routes = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $routes[] = $row;
    }
}
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
    <div class="container">
        <div class="content">
            <div><a href="dashboard.php" class="add-btn">Home</a></div>
            <div class="route-box">
                <h2><?= $edit_route ? 'Edit Popular Route' : 'Manage Popular Routes' ?></h2>
                <?php if ($message !== ""): ?>
                    <div class="message <?= htmlspecialchars($message_type) ?>"><?= htmlspecialchars($message) ?></div>
                <?php endif; ?>
                <form method="POST" enctype="multipart/form-data">
                    <?php if ($edit_route): ?>
                        <input type="hidden" name="edit_id" value="<?= (int)$edit_route['popular_id'] ?>">
                    <?php endif; ?>
                    <div class="form-row">
                        <div class="form-group">
                            <label>From City</label>
                            <select name="from_city" required>
                                <option value="">Select From City</option>
                                <?php foreach ($cities as $city): ?>
                                    <option value="<?= htmlspecialchars($city) ?>" <?= ($edit_route['from_city'] ?? '') === $city ? 'selected' : '' ?>><?= htmlspecialchars(ucwords($city)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>To City</label>
                            <select name="to_city" required>
                                <option value="">Select To City</option>
                                <?php foreach ($cities as $city): ?>
                                    <option value="<?= htmlspecialchars($city) ?>" <?= ($edit_route['to_city'] ?? '') === $city ? 'selected' : '' ?>><?= htmlspecialchars(ucwords($city)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Price</label>
                            <input type="number" name="price" value="<?= htmlspecialchars($edit_route['price'] ?? '') ?>" placeholder="Enter Price" min="0" step="100" required>
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status">
                                <option value="active" <?= ($edit_route['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= ($edit_route['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Bus Image</label>
                            <input type="file" name="route_image" accept=".jpg,.jpeg,.png,.gif,.webp">
                        </div>
                    </div>
                    <button type="submit" class="add-btn"><?= $edit_route ? 'Update Popular Route' : 'Assign Popular Route' ?></button>
                    <?php if ($edit_route): ?>
                        <a href="popular_routes.php" class="add-btn">Cancel</a>
                    <?php endif; ?>
                </form>
            </div>
            <div class="route-box">
                <h2>Assigned Popular Routes</h2>
                <?php if (count($routes) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Image</th>
                                <th>From</th>
                                <th>To</th>
                                <th>Price</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($routes as $route): ?>
                                <tr>
                                    <td><?= (int)$route['popular_id'] ?></td>
                                    <td><img src="<?= htmlspecialchars("../" . $route['image']) ?>" class="route-thumb" alt="Route Image"></td>
                                    <td><?= htmlspecialchars($route['from_city']) ?></td>
                                    <td><?= htmlspecialchars($route['to_city']) ?></td>
                                    <td>Rs. <?= htmlspecialchars($route['price']) ?></td>
                                    <td>
                                        <?php if ($route['status'] === 'active'): ?>
                                            <span class="badge badge-active">Active</span>
                                        <?php else: ?>
                                            <span class="badge badge-inactive">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="?edit=<?= (int)$route['popular_id'] ?>" class="edit-btn">Edit</a>
                                        <a href="?delete=<?= (int)$route['popular_id'] ?>" class="delete-btn" onclick="return confirm('Are you sure you want to delete this route?');">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="no-route">No popular routes assigned yet.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>

</html>