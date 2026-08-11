<?php
session_start();

require_once "../db.php";

/* =========================
   ADMIN AUTHENTICATION
========================= */

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$message = "";
$message_type = "";


/* =========================
   ADD POPULAR ROUTE
========================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $from_city = trim($_POST['from_city'] ?? '');
    $to_city   = trim($_POST['to_city'] ?? '');
    $price     = trim($_POST['price'] ?? '');
    $status    = trim($_POST['status'] ?? 'active');

    /* Basic validation */

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

        /* =========================
           CHECK DUPLICATE ROUTE
        ========================= */

        $check = $conn->prepare("
            SELECT popular_id
            FROM popular_routes
            WHERE LOWER(from_city) = LOWER(?)
            AND LOWER(to_city) = LOWER(?)
            LIMIT 1
        ");

        $check->bind_param(
            "ss",
            $from_city,
            $to_city
        );

        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {

            $message = "This popular route already exists.";
            $message_type = "error";

            $check->close();
        } else {

            $check->close();

            /* =========================
               DEFAULT IMAGE
            ========================= */

            $image = "Bus Image/b1.jpg";


            /* =========================
               IMAGE UPLOAD
            ========================= */

            if (
                isset($_FILES['route_image']) &&
                $_FILES['route_image']['error'] === UPLOAD_ERR_OK
            ) {

                $file_tmp  = $_FILES['route_image']['tmp_name'];
                $file_name = $_FILES['route_image']['name'];

                $extension = strtolower(
                    pathinfo($file_name, PATHINFO_EXTENSION)
                );

                $allowed = [
                    'jpg',
                    'jpeg',
                    'png',
                    'gif',
                    'webp'
                ];

                if (in_array($extension, $allowed)) {

                    $new_file_name =
                        time() . "_" .
                        uniqid() . "." .
                        $extension;

                    /*
                     * popular_routes.php is inside admin/
                     * so upload folder is ../Bus Image/
                     */

                    $upload_dir = "../Bus Image/";

                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }

                    $destination =
                        $upload_dir . $new_file_name;

                    if (move_uploaded_file(
                        $file_tmp,
                        $destination
                    )) {

                        /*
                         * Store path from ROOT/home page
                         */

                        $image =
                            "Bus Image/" .
                            $new_file_name;
                    } else {

                        $message =
                            "Image upload failed. Default image used.";

                        $message_type = "error";
                    }
                } else {

                    $message =
                        "Invalid image format. Default image used.";

                    $message_type = "error";
                }
            }


            /* =========================
               INSERT ROUTE
            ========================= */

            $stmt = $conn->prepare("
                INSERT INTO popular_routes
                (
                    from_city,
                    to_city,
                    price,
                    image,
                    status
                )
                VALUES (?, ?, ?, ?, ?)
            ");

            $price = (float)$price;

            $stmt->bind_param(
                "ssdss",
                $from_city,
                $to_city,
                $price,
                $image,
                $status
            );

            if ($stmt->execute()) {

                $message =
                    "Popular route assigned successfully.";

                $message_type = "success";
            } else {

                $message =
                    "Error adding popular route.";

                $message_type = "error";
            }

            $stmt->close();
        }
    }
}


/* =========================
   DELETE POPULAR ROUTE
========================= */

if (isset($_GET['delete'])) {

    $id = intval($_GET['delete']);

    if ($id > 0) {

        /*
         * Get image first
         */

        $stmt = $conn->prepare("
            SELECT image
            FROM popular_routes
            WHERE popular_id = ?
        ");

        $stmt->bind_param("i", $id);
        $stmt->execute();

        $result = $stmt->get_result();
        $route = $result->fetch_assoc();

        $stmt->close();


        /*
         * Delete database record
         */

        $stmt = $conn->prepare("
            DELETE FROM popular_routes
            WHERE popular_id = ?
        ");

        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {

            /*
             * Delete uploaded image
             * but keep default image
             */

            if (
                $route &&
                !empty($route['image']) &&
                $route['image'] !== "Bus Image/b1.jpg"
            ) {

                $image_path = "../" . $route['image'];

                if (file_exists($image_path)) {
                    unlink($image_path);
                }
            }
        }

        $stmt->close();
    }

    header("Location: popular_routes.php");
    exit;
}


/* =========================
   FETCH POPULAR ROUTES
========================= */

$result = $conn->query("
    SELECT *
    FROM popular_routes
    ORDER BY popular_id DESC
");

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

    <meta name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>Manage Popular Routes</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #f4f7f6;
        }

        .container {
            width: 92%;
            max-width: 1200px;
            margin: 40px auto;
        }

        .route-box {
            background: #fff;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, .08);
            margin-bottom: 25px;
        }

        .route-box h2 {
            margin: 0 0 20px;
            color: #1560bd;
        }

        .message {
            padding: 12px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-weight: bold;
        }

        .success {
            background: #dff5e8;
            color: #087443;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
        }

        .form-row {
            display: grid;
            grid-template-columns:
                1fr 1fr 130px 130px 180px;

            gap: 15px;
            align-items: end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            margin-bottom: 6px;
            font-size: 14px;
            font-weight: bold;
            color: #333;
        }

        input,
        select {
            width: 100%;
            padding: 11px;
            border: 1px solid #ccc;
            border-radius: 5px;
            background: white;
        }

        input:focus,
        select:focus {
            outline: none;
            border-color: #1560bd;
        }

        input[type="file"] {
            padding: 8px;
        }

        .add-btn {
            margin-top: 20px;
            padding: 12px 25px;
            border: none;
            border-radius: 5px;
            background: #1560bd;
            color: white;
            font-weight: bold;
            cursor: pointer;
        }

        .add-btn:hover {
            background: #0fa070;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 12px;
            border-bottom: 1px solid #ddd;
            text-align: center;
            vertical-align: middle;
        }

        th {
            background: #1560bd;
            color: white;
        }

        .route-thumb {
            width: 80px;
            height: 55px;
            object-fit: cover;
            border-radius: 5px;
        }

        .badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
        }

        .badge-active {
            background: #dff5e8;
            color: #087443;
        }

        .badge-inactive {
            background: #f8d7da;
            color: #721c24;
        }

        .delete-btn {
            display: inline-block;
            background: #dc3545;
            color: white;
            padding: 7px 12px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 13px;
        }

        .delete-btn:hover {
            background: #b52a37;
        }

        .no-route {
            padding: 20px;
            text-align: center;
            color: #777;
        }

        @media(max-width: 900px) {

            .form-row {
                grid-template-columns: 1fr;
            }

            .route-box {
                overflow-x: auto;
            }

            table {
                min-width: 750px;
            }

        }
    </style>

</head>

<body>

    <div class="container">


        <!-- =========================
         ADD ROUTE
    ========================= -->

        <div class="route-box">

            <h2>Manage Popular Routes</h2>

            <?php if ($message !== ""): ?>

                <div class="message <?= $message_type ?>">

                    <?= htmlspecialchars($message) ?>

                </div>

            <?php endif; ?>


            <form
                method="POST"
                enctype="multipart/form-data">

                <div class="form-row">


                    <!-- FROM -->

                    <div class="form-group">

                        <label>From City</label>

                        <input
                            type="text"
                            name="from_city"
                            placeholder="Kathmandu"
                            required>

                    </div>


                    <!-- TO -->

                    <div class="form-group">

                        <label>To City</label>

                        <input
                            type="text"
                            name="to_city"
                            placeholder="Pokhara"
                            required>

                    </div>


                    <!-- PRICE -->

                    <div class="form-group">

                        <label>Price</label>

                        <input
                            type="number"
                            name="price"
                            placeholder="700"
                            min="0"
                            step="0.01"
                            required>

                    </div>


                    <!-- STATUS -->

                    <div class="form-group">

                        <label>Status</label>

                        <select name="status">

                            <option value="active">
                                Active
                            </option>

                            <option value="inactive">
                                Inactive
                            </option>

                        </select>

                    </div>


                    <!-- IMAGE -->

                    <div class="form-group">

                        <label>Route Image</label>

                        <input
                            type="file"
                            name="route_image"
                            accept=".jpg,.jpeg,.png,.gif,.webp">

                    </div>

                </div>


                <button
                    type="submit"
                    class="add-btn">
                    Assign Popular Route
                </button>

            </form>

        </div>



        <!-- =========================
         ASSIGNED ROUTES
    ========================= -->

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

                                <td>
                                    <?= (int)$route['popular_id'] ?>
                                </td>


                                <td>

                                    <?php
                                    $admin_image =
                                        "../" . $route['image'];
                                    ?>

                                    <img
                                        src="<?= htmlspecialchars($admin_image) ?>"
                                        class="route-thumb"
                                        alt="Route Image">

                                </td>


                                <td>
                                    <?= htmlspecialchars(
                                        $route['from_city']
                                    ) ?>
                                </td>


                                <td>
                                    <?= htmlspecialchars(
                                        $route['to_city']
                                    ) ?>
                                </td>


                                <td>
                                    Rs.
                                    <?= htmlspecialchars(
                                        $route['price']
                                    ) ?>
                                </td>


                                <td>

                                    <?php if (
                                        $route['status'] === 'active'
                                    ): ?>

                                        <span class="badge badge-active">
                                            Active
                                        </span>

                                    <?php else: ?>

                                        <span class="badge badge-inactive">
                                            Inactive
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <td>

                                    <a
                                        href="?delete=<?= (int)$route['popular_id'] ?>"
                                        class="delete-btn"
                                        onclick="return confirm(
                                    'Are you sure you want to delete this route?'
                                );">
                                        Delete
                                    </a>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            <?php else: ?>

                <div class="no-route">
                    No popular routes assigned yet.
                </div>

            <?php endif; ?>

        </div>

    </div>

</body>

</html>