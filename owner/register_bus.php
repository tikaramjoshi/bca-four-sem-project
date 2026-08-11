<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != "owner") {
    header("Location: ../login.php");
    exit();
}

require_once "../db.php";
$owner_id = $_SESSION['user_id'];
$message = "";
$message_type = "";


if (isset($_POST['register_bus'])) {
    $bus_number = trim($_POST['bus_number']);
    $bus_name = trim($_POST['bus_name']);
    $bus_type = trim($_POST['bus_type']);
    $seat = (int) $_POST['seat'];
    $facilities = trim($_POST['facilities']);
    $image_name = "";

    if (!isset($_FILES['bus_image']) || $_FILES['bus_image']['error'] != 0) {
        $message = "Bus image is required.";
        $message_type = "error";
    } else {
        $upload_dir = "../uploads/bus/";
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        $file_size = $_FILES['bus_image']['size'];
        if ($file_size > 5 * 1024 * 1024) {
            $message = "Image size must be less than 5MB.";
            $message_type = "error";
        } else {
            $extension = strtolower(
                pathinfo(
                    $_FILES['bus_image']['name'],
                    PATHINFO_EXTENSION
                )
            );
            $allowed = [
                "jpg",
                "jpeg",
                "png",
                "webp"
            ];
            if (!in_array($extension, $allowed)) {
                $message = "Only JPG, JPEG, PNG and WEBP images are allowed.";
                $message_type = "error";
            } else {
                $image_name = time() . "_" . rand(1000, 9999) . "." . $extension;
                if (!move_uploaded_file(
                    $_FILES['bus_image']['tmp_name'],
                    $upload_dir . $image_name
                )) {
                    $message = "Image upload failed.";
                    $message_type = "error";
                }
            }
        }
    }

    if (empty($message)) {

        $check = $conn->prepare(
            "SELECT bus_id FROM bus WHERE bus_number=?"
        );
        $check->bind_param(
            "s",
            $bus_number
        );
        $check->execute();
        $result = $check->get_result();
        if ($result->num_rows > 0) {
            $message = "Bus number already exists.";
            $message_type = "error";
        } else {
            $insert = $conn->prepare(
                "INSERT INTO bus
                (
                    owner_id,
                    bus_number,
                    bus_name,
                    bus_type,
                    seats,
                    facilities,
                    bus_image,
                    status
                )
                VALUES
                (?,?,?,?,?,?,?,'pending')"
            );
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
                $_SESSION['success'] =
                    "Bus registered successfully. Waiting for admin approval.";
                header("Location: dashboard.php");
                exit();
            } else {
                $message =
                    "Database Error : " . $insert->error;
                $message_type = "error";
            }
        }
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
            border: 1px solid #1560BD;
        }

        .register-box textarea {
            resize: none;
        }

        #preview {
            margin-top: 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            object-fit: cover;
        }

        button {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 6px;
            background: #1560BD;
            color: white;
            font-size: 17px;
            cursor: pointer;
            margin-top: 20px;
        }

        button:hover {
            background: #0d4d9b;
        }

        .success {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .button-group {
            display: flex;
            gap: 15px;
            margin-top: 25px;
        }

        .button-group button,
        .button-group a {
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
            background: #dc3545;
            color: #fff;
        }

        .cancel-btn:hover {
            background: #b52a37;
        }

        @media(max-width:768px) {
            .register-box {
                width: 100%;
                padding: 20px;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="register-box">
            <h2>Register New Bus</h2>
            <?php if ($message != "") { ?>
                <div class="<?php echo $message_type; ?>">
                    <?php echo $message; ?>
                </div>
            <?php } ?>
            <form method="POST" enctype="multipart/form-data">
                <label>Bus Number</label>
                <input type="text" name="bus_number" placeholder="BA-2-KHA-1234" required>
                <label>Bus Name</label>
                <input type="text" name="bus_name" placeholder="Green Line" required>
                <label>Bus Type</label>
                <select name="bus_type" required>
                    <option value="">Select Bus Type</option>
                    <option value="AC">AC</option>
                    <option value="Deluxe">Deluxe</option>
                    <option value="Normal">Normal</option>
                    <option value="VIP">VIP</option>
                </select>
                <label>Total Seats</label>
                <input type="number" name="seat" min="10" max="80" required>
                <label>Bus Image</label>
                <input type="file" name="bus_image" id="image" accept="image/*" onchange="previewImage(event)" required>
                <br>
                <img id="preview" src="../images/bus.png" width="180" height="120">
                <br><br>
                <label>Facilities</label>
                <textarea name="facilities" rows="3" placeholder="WiFi, Charging, AC..."></textarea>
                <div class="button-group">
                    <button type="submit" name="register_bus" class="submit-btn"> Register Bus</button>
                    <a href="dashboard.php" class="cancel-btn"> Cancel</a>
                </div>
            </form>
        </div>
    </div>
    <script>
        function previewImage(event) {
            let preview = document.getElementById("preview");
            preview.src = URL.createObjectURL(event.target.files[0]);
        }
    </script>
</body>

</html>