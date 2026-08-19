<?php
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'owner') {
    header("Location: ../login.php");
    exit();
}

require_once "../db.php";

$owner_id = (int)$_SESSION['user_id'];
$message = "";
$message_type = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $bus_number = trim($_POST['bus_number'] ?? '');
    $bus_name = trim($_POST['bus_name'] ?? '');
    $bus_type = trim($_POST['bus_type'] ?? '');
    $seat = (int)($_POST['seat'] ?? 0);
    $facilities = $_POST['facilities'] ?? [];

    if (!is_array($facilities)) {
        $facilities = [$facilities];
    }

    $facilities = implode(', ', array_map('trim', $facilities));

    if ($bus_number === '' || $bus_name === '' || $bus_type === '' || $seat <= 0) {
        $message = "Please fill in all required fields.";
        $message_type = "error";
    } elseif (!isset($_FILES['bus_image']) || $_FILES['bus_image']['error'] !== UPLOAD_ERR_OK) {
        $message = "Please select a bus image.";
        $message_type = "error";
    } else {
        $check = $conn->prepare("SELECT bus_id FROM bus WHERE bus_number=? LIMIT 1");
        $check->bind_param("s", $bus_number);
        $check->execute();
        $result = $check->get_result();

        if ($result->num_rows > 0) {
            $message = "Bus number already exists.";
            $message_type = "error";
        } else {
            $upload_dir = "../uploads/bus/";

            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $file = $_FILES['bus_image'];
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if (!in_array($extension, $allowed, true)) {
                $message = "Only JPG, JPEG, PNG, GIF and WEBP images are allowed.";
                $message_type = "error";
            } elseif ($file['size'] > 5 * 1024 * 1024) {
                $message = "Bus image must be less than 5MB.";
                $message_type = "error";
            } else {
                $image_name = 'bus_' . time() . '_' . bin2hex(random_bytes(5)) . '.' . $extension;
                $image_path = $upload_dir . $image_name;

                if (!move_uploaded_file($file['tmp_name'], $image_path)) {
                    $message = "Failed to upload bus image.";
                    $message_type = "error";
                } else {
                    $insert = $conn->prepare("
                        INSERT INTO bus
                        (owner_id, bus_number, bus_name, bus_type, seats, facilities, bus_image, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
                    ");

                    $insert->bind_param(
                        "isssiss",
                        $owner_id,
                        $bus_number,
                        $bus_name,
                        $bus_type,
                        $seat,
                        $facilities,
                        $image_name
                    );

                    if ($insert->execute()) {
                        $_SESSION['success'] = "Bus registered successfully. Waiting for admin approval.";
                        header("Location: dashboard.php");
                        exit();
                    } else {
                        if (file_exists($image_path)) {
                            unlink($image_path);
                        }

                        $message = "Database Error: " . $insert->error;
                        $message_type = "error";
                    }

                    $insert->close();
                }
            }
        }

        $check->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Bus</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        body {
            background: #f4f7fb;
        }

        .container {
            width: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 40px 15px;
        }

        .register-box {
            width: 700px;
            background: #fff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, .08);
        }

        .register-box h2 {
            text-align: center;
            color: #1560BD;
            margin-bottom: 25px;
        }

        .register-box label {
            display: block;
            margin-top: 15px;
            margin-bottom: 6px;
            font-weight: bold;
        }

        .register-box input,
        .register-box select,
        .register-box textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 15px;
            outline: none;
        }

        .register-box input:focus,
        .register-box select:focus,
        .register-box textarea:focus {
            border-color: #1560BD;
        }

        #preview {
            margin-top: 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            object-fit: cover;
        }

        .success,
        .error {
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .success {
            background: #d4edda;
            color: #155724;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
        }

        .facilities {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            margin-top: 5px;
        }

        .facilities label {
            margin: 0;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-weight: normal;
            cursor: pointer;
        }

        .facilities input {
            width: auto;
            margin-right: 5px;
        }

        .button-group {
            display: flex;
            gap: 15px;
            margin-top: 25px;
        }

        .button-group button,
        .cancel-btn {
            flex: 1;
            height: 50px;
            display: flex;
            justify-content: center;
            align-items: center;
            text-decoration: none;
            font-size: 16px;
            font-weight: bold;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }

        .submit-btn {
            background: #1560BD;
            color: #fff;
        }

        .submit-btn:hover {
            background: #0d4d9b;
        }

        .cancel-btn {
            background: #777;
            color: #fff;
        }

        .cancel-btn:hover {
            background: #555;
        }

        @media(max-width:768px) {
            .register-box {
                width: 100%;
                padding: 20px;
            }

            .facilities {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>

<body>
    <?php include "header.php" ?>
    <div class="container">
        <div class="register-box">
            <h2>Register New Bus</h2>

            <?php if ($message !== ""): ?>
                <div class="<?= htmlspecialchars($message_type) ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <label>Bus Number</label>
                <input type="text" name="bus_number" placeholder="BA-2-KHA-1234" value="<?= htmlspecialchars($_POST['bus_number'] ?? '') ?>" required>

                <label>Bus Name</label>
                <input type="text" name="bus_name" placeholder="Green Line" value="<?= htmlspecialchars($_POST['bus_name'] ?? '') ?>" required>

                <label>Bus Type</label>
                <select name="bus_type" required>
                    <option value="">Select Bus Type</option>
                    <option value="AC" <?= ($_POST['bus_type'] ?? '') === 'AC' ? 'selected' : '' ?>>AC</option>
                    <option value="Deluxe" <?= ($_POST['bus_type'] ?? '') === 'Deluxe' ? 'selected' : '' ?>>Deluxe</option>
                    <option value="Normal" <?= ($_POST['bus_type'] ?? '') === 'Normal' ? 'selected' : '' ?>>Normal</option>
                    <option value="VIP" <?= ($_POST['bus_type'] ?? '') === 'VIP' ? 'selected' : '' ?>>VIP</option>
                </select>

                <label>Total Seats</label>
                <input type="number" name="seat" min="10" max="80" value="<?= htmlspecialchars($_POST['seat'] ?? '') ?>" required>

                <label>Bus Image</label>
                <input type="file" name="bus_image" id="image" accept="image/*" onchange="previewImage(event)" required>

                <img id="preview" src="../images/bus.png" width="180" height="120" alt="Bus Preview">

                <label>Facilities</label>

                <div class="facilities">
                    <label><input type="checkbox" name="facilities[]" value="WiFi"> WiFi</label>
                    <label><input type="checkbox" name="facilities[]" value="Charging"> Charging</label>
                    <label><input type="checkbox" name="facilities[]" value="AC"> AC</label>
                    <label><input type="checkbox" name="facilities[]" value="TV"> TV</label>
                    <label><input type="checkbox" name="facilities[]" value="Music"> Music</label>
                    <label><input type="checkbox" name="facilities[]" value="Water"> Water</label>
                    <label><input type="checkbox" name="facilities[]" value="Blanket"> Blanket</label>
                    <label><input type="checkbox" name="facilities[]" value="CCTV"> CCTV</label>
                    <label><input type="checkbox" name="facilities[]" value="Toilet"> Toilet</label>
                </div>

                <div class="button-group">
                    <button type="submit" name="register_bus" class="submit-btn">Register Bus</button>
                    <a href="dashboard.php" class="cancel-btn">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    <?php include "footer.php" ?>
    <script>
        function previewImage(event) {
            const file = event.target.files[0];
            if (file) {
                document.getElementById("preview").src = URL.createObjectURL(file);
            }
        }
    </script>
</body>

</html>