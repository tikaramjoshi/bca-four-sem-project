<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

require_once "../db.php";

$user_id = (int)$_SESSION['user_id'];
$admin_name = $_SESSION['name'];

$stmt = $conn->prepare("SELECT profile_image FROM users WHERE user_id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
$stmt->close();

$profile_image = !empty($admin['profile_image']) ? $admin['profile_image'] : "default.png";

$message = "";

if (isset($_POST['add_city'])) {
    $city_name = trim($_POST['city_name']);

    if ($city_name === "") {
        $message = "City name is required.";
    } else {
        $check = $conn->prepare("SELECT route_id FROM routes WHERE LOWER(city_name)=LOWER(?)");
        $check->bind_param("s", $city_name);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $message = "City already exists.";
        } else {
            $stmt = $conn->prepare("INSERT INTO routes (city_name) VALUES (?)");
            $stmt->bind_param("s", $city_name);

            if ($stmt->execute()) {
                $message = "City added successfully.";
            } else {
                $message = "Failed to add city.";
            }

            $stmt->close();
        }

        $check->close();
    }
}

if (isset($_POST['update_city'])) {
    $route_id = (int)$_POST['route_id'];
    $city_name = trim($_POST['city_name']);

    if ($city_name === "") {
        $message = "City name is required.";
    } else {
        $check = $conn->prepare("SELECT route_id FROM routes WHERE LOWER(city_name)=LOWER(?) AND route_id!=?");
        $check->bind_param("si", $city_name, $route_id);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $message = "City already exists.";
        } else {
            $stmt = $conn->prepare("UPDATE routes SET city_name=? WHERE route_id=?");
            $stmt->bind_param("si", $city_name, $route_id);

            if ($stmt->execute()) {
                $message = "City updated successfully.";
            } else {
                $message = "Failed to update city.";
            }

            $stmt->close();
        }

        $check->close();
    }
}

if (isset($_GET['delete'])) {
    $route_id = (int)$_GET['delete'];

    $stmt = $conn->prepare("DELETE FROM routes WHERE route_id=?");
    $stmt->bind_param("i", $route_id);

    if ($stmt->execute()) {
        $message = "City deleted successfully.";
    } else {
        $message = "Failed to delete city.";
    }

    $stmt->close();
}

$edit_city = null;

if (isset($_GET['edit'])) {
    $route_id = (int)$_GET['edit'];

    $stmt = $conn->prepare("SELECT route_id, city_name FROM routes WHERE route_id=?");
    $stmt->bind_param("i", $route_id);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $edit_city = $result->fetch_assoc();
    }

    $stmt->close();
}

$routes = $conn->query("
    SELECT route_id, city_name, created_at
    FROM routes
    ORDER BY route_id DESC
");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Routes</title>
    <link rel="stylesheet" href="dashboard_admin.css">
    <link rel="stylesheet" href="routes.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

    <style>
        .route-page {
            width: 100%
        }

        .route-header,
        .route-form-box,
        .route-table-box {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, .08)
        }

        .route-header {
            padding: 20px 25px;
            margin-bottom: 20px
        }

        .route-header h2 {
            margin: 0;
            color: #1560BD;
            font-size: 25px
        }

        .route-header p {
            margin: 6px 0 0;
            color: #777
        }

        .route-message {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-weight: bold;
            background: #e7f5e9;
            color: #16803c
        }

        .route-form-box {
            padding: 22px;
            margin-bottom: 20px
        }

        .route-form-box h3 {
            margin: 0 0 20px;
            color: #1560BD
        }

        .route-form {
            display: flex;
            gap: 12px;
            align-items: center
        }

        .route-form input {
            flex: 1;
            height: 42px;
            padding: 8px 12px;
            border: 1px solid #ccc;
            border-radius: 6px;
            outline: none;
            font-size: 14px
        }

        .route-form input:focus {
            border-color: #1560BD
        }

        .route-btn {
            height: 42px;
            padding: 0 18px;
            border: 0;
            border-radius: 6px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            font-weight: bold;
            cursor: pointer
        }

        .route-btn-primary {
            background: #1560BD;
            color: #fff
        }

        .route-btn-primary:hover {
            background: #0d4e9c
        }

        .route-btn-cancel {
            background: #eee;
            color: #555
        }

        .route-table-box {
            overflow: hidden
        }

        .table-title {
            padding: 18px 22px;
            border-bottom: 1px solid #eee
        }

        .table-title h3 {
            margin: 0;
            color: #1560BD
        }

        .route-table-wrapper {
            width: 100%;
            overflow-x: auto
        }

        .route-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 650px
        }

        .route-table th {
            background: #1560BD;
            color: #fff;
            padding: 13px;
            text-align: left;
            font-size: 13px
        }

        .route-table td {
            padding: 14px;
            border-bottom: 1px solid #eee;
            color: #444;
            font-size: 14px
        }

        .route-table tr:hover {
            background: #f8fbff
        }

        .action-edit,
        .action-delete {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 12px;
            font-weight: bold;
            margin-right: 5px
        }

        .action-edit {
            background: #e4edff;
            color: #1560BD
        }

        .action-delete {
            background: #ffe1e1;
            color: #d62828
        }

        .action-edit:hover {
            background: #cddfff
        }

        .action-delete:hover {
            background: #ffcaca
        }

        .route-empty {
            text-align: center;
            padding: 40px !important;
            color: #999 !important
        }

        @media(max-width:600px) {
            .route-form {
                flex-direction: column;
                align-items: stretch
            }

            .route-btn {
                width: 100%
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
            <a href="routes.php" class="active">Routes</a>
            <a href="boarding.php">Boarding</a>
            <a href="dropping.php">Dropping</a>
            <a href="schedule.php">Schedule</a>
            <a href="bookings.php">Bookings</a>
        </div>

        <div class="content">

            <div class="route-page">

                <div class="route-header">
                    <h2>Manage Routes</h2>
                    <p>Add, edit and delete city names</p>
                </div>

                <?php if ($message): ?>
                    <div class="route-message">
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>

                <div class="route-form-box">

                    <h3>
                        <i class="fa <?= $edit_city ? 'fa-edit' : 'fa-plus-circle' ?>"></i>
                        <?= $edit_city ? 'Edit City' : 'Add City' ?>
                    </h3>

                    <form method="POST" class="route-form">

                        <?php if ($edit_city): ?>

                            <input type="hidden" name="route_id" value="<?= (int)$edit_city['route_id'] ?>">

                            <input type="text" name="city_name" value="<?= htmlspecialchars($edit_city['city_name']) ?>" placeholder="Enter city name" maxlength="100" required>

                            <button type="submit" name="update_city" class="route-btn route-btn-primary">
                                <i class="fa fa-save"></i> Update City
                            </button>

                            <a href="routes.php" class="route-btn route-btn-cancel">
                                Cancel
                            </a>

                        <?php else: ?>

                            <input type="text" name="city_name" placeholder="Enter city name" maxlength="100" required>

                            <button type="submit" name="add_city" class="route-btn route-btn-primary">
                                <i class="fa fa-plus"></i> Add City
                            </button>

                        <?php endif; ?>

                    </form>

                </div>

                <div class="route-table-box">

                    <div class="table-title">
                        <h3><i class="fa fa-route"></i> Route List</h3>
                    </div>

                    <div class="route-table-wrapper">

                        <table class="route-table">

                            <thead>
                                <tr>
                                    <th>Route ID</th>
                                    <th>City Name</th>
                                    <th>Created At</th>
                                    <th>Action</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php if ($routes && $routes->num_rows > 0): ?>

                                    <?php while ($route = $routes->fetch_assoc()): ?>

                                        <tr>
                                            <td><?= (int)$route['route_id'] ?></td>

                                            <td>
                                                <strong><?= htmlspecialchars($route['city_name']) ?></strong>
                                            </td>

                                            <td>
                                                <?= date("d M Y", strtotime($route['created_at'])) ?>
                                            </td>

                                            <td>
                                                <a href="routes.php?edit=<?= (int)$route['route_id'] ?>" class="action-edit">
                                                    <i class="fa fa-edit"></i> Edit
                                                </a>

                                                <a href="routes.php?delete=<?= (int)$route['route_id'] ?>" class="action-delete" onclick="return confirm('Are you sure you want to delete this city?')">
                                                    <i class="fa fa-trash"></i> Delete
                                                </a>
                                            </td>
                                        </tr>

                                    <?php endwhile; ?>

                                <?php else: ?>

                                    <tr>
                                        <td colspan="4" class="route-empty">
                                            <i class="fa fa-route"></i>
                                            <br><br>
                                            No cities found.
                                        </td>
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
        }
    </script>

</body>

</html>