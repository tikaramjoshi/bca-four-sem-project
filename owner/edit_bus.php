<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != "owner") {
    header("Location: ../login.php");
    exit();
}

require_once "../db.php";
$owner_id = $_SESSION['user_id'];
if (!isset($_GET['id'])) {
    header("Location: dashboard.php");
    exit();
}
$bus_id = (int)$_GET['id'];
$stmt = $conn->prepare(" SELECT * FROM bus WHERE bus_id=? AND owner_id=? ");
$stmt->bind_param(
    "ii",
    $bus_id,
    $owner_id
);
$stmt->execute();
$result = $stmt->get_result();
$bus = $result->fetch_assoc();

if (!$bus) {
    die("Bus Not Found");
}
$message = "";

if (isset($_POST['update_bus'])) {
    $bus_number = trim($_POST['bus_number']);
    $bus_name = trim($_POST['bus_name']);
    $bus_type = trim($_POST['bus_type']);
    $seats = (int)$_POST['seats'];
    $image_name = $bus['bus_image'];
    if (isset($_FILES['bus_image']) && $_FILES['bus_image']['error'] == 0) {
        $upload_dir = "../uploads/bus/";
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
        if (in_array($extension, $allowed)) {
            $new_image = time() . "_" . rand(1000, 9999) . "." . $extension;
            if (move_uploaded_file(
                $_FILES['bus_image']['tmp_name'],
                $upload_dir . $new_image
            )) {

                if (
                    !empty($bus['bus_image']) &&
                    file_exists($upload_dir . $bus['bus_image'])
                ) {
                    unlink($upload_dir . $bus['bus_image']);
                }
                $image_name = $new_image;
            }
        }
    }

    $check = $conn->prepare(" SELECT bus_id FROM bus WHERE bus_number=? AND bus_id!=? ");
    $check->bind_param(
        "si",
        $bus_number,
        $bus_id
    );
    $check->execute();
    $result = $check->get_result();
    if ($result->num_rows > 0) {
        $message = "Bus Number Already Exists.";
    } else {
        $update = $conn->prepare(" UPDATE bus SET bus_number=?, bus_name=?, bus_type=?, seats=?, bus_image=?, status='pending' WHERE bus_id=? ");
        $update->bind_param(
            "sssisi",
            $bus_number,
            $bus_name,
            $bus_type,
            $seats,
            $image_name,
            $bus_id
        );
        if ($update->execute()) {
            $_SESSION['success'] = "Bus updated successfully. Waiting for approval.";
            header("Location: dashboard.php");
            exit();
        }
    }
}
include "owner_header.php";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Bus</title>
    <link rel="stylesheet" href="edit_bus.css">
</head>

<body>
    <div class="container">
        <div class="edit-box">
            <h2>Edit Bus</h2>
            <?php if ($message != "") { ?>
                <div class="message"> <?= htmlspecialchars($message); ?> </div>
            <?php } ?>
            <form method="POST">
                <label>Bus Number</label>
                <input type="text" name="bus_number" value="<?= htmlspecialchars($bus['bus_number']); ?>" required>
                <label> Bus Name</label>
                <input type="text" name="bus_name" value="<?= htmlspecialchars($bus['bus_name']); ?>" required>
                <label> Bus Type </label>
                <select name="bus_type" required>
                    <option value="AC" <?= ($bus['bus_type'] == "AC") ? "selected" : ""; ?>> AC </option>
                    <option value="Non AC" <?= ($bus['bus_type'] == "Non AC") ? "selected" : ""; ?>>Non AC</option>
                    <option value="Deluxe" <?= ($bus['bus_type'] == "Deluxe") ? "selected" : ""; ?>>Deluxe </option>
                    <option value="Sofa" <?= ($bus['bus_type'] == "Sofa") ? "selected" : ""; ?>>Sofa</option>
                </select>
                <label> Total Seats</label>
                <input type="number" name="seats" min="10" max="80" value="<?= $bus['seats']; ?>" required>
                <label>Bus Image</label>
                <input type="file" name="bus_image" accept="image/*" onchange="previewImage(event)"> <br><br>
                <img id="preview" src="../uploads/bus/<?= htmlspecialchars($bus['bus_image']); ?>" width="200" height="130" style=" object-fit:cover; border-radius:8px; border:1px solid #ccc; ">
                <div class="button-group">
                    <button type="submit" name="update_bus" class="update-btn"> <i class="fas fa-save"></i> Update Bus </button>
                    <a href="dashboard.php" class="cancel-btn"> <i class="fas fa-times"></i> Cancel </a>
                </div>
            </form>
        </div>
    </div>
    <?php require_once "owner_footer.php"; ?>
    <script>
        function previewImage(event) {
            let image = document.getElementById("preview");
            image.src = URL.createObjectURL(
                event.target.files[0]
            );
        }
    </script>
</body>

</html>