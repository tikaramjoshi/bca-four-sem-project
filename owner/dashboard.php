<?php

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != "owner") {
    header("Location: ../login.php");
    exit();
}
require_once "../db.php";
include "owner_header.php";
$owner_id = (int)$_SESSION['user_id'];

$stmt = $conn->prepare("SELECT status, reject_reason FROM owner_verification WHERE owner_id=? ORDER BY verification_id DESC LIMIT 1");
$stmt->bind_param("i", $owner_id);
$stmt->execute();
$verify = $stmt->get_result()->fetch_assoc();
$stmt->close();

$verification_status = $verify['status'] ?? 'unverified';
$isVerified = ($verification_status === 'verified');


$message = "";
$message_type = "";
if (isset($_SESSION['success'])) {
    $message = $_SESSION['success'];
    $message_type = "success";
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    $message = $_SESSION['error'];
    $message_type = "error";
    unset($_SESSION['error']);
}

if (isset($_POST['register_bus'])) {
    if (!$isVerified) {
        $message = "Please complete account verification first.";
        $message_type = "error";
    } else {
        $bus_number = trim($_POST['bus_number']);
        $bus_name   = trim($_POST['bus_name']);
        $bus_type   = trim($_POST['bus_type']);
        $seats      = (int)$_POST['seats'];
        if (empty($bus_number) ||   empty($bus_name) || empty($bus_type) || $seats <= 0) {
            $message = "All fields are required.";
            $message_type = "error";
        } else {
            $check = $conn->prepare("SELECT bus_id FROM bus WHERE bus_number = ?");
            $check->bind_param("s", $bus_number);
            $check->execute();
            $result = $check->get_result();
            if ($result->num_rows > 0) {
                $message = "Bus Number Already Exists.";
                $message_type = "error";
            } else {
                if (
                    !isset($_FILES['bus_image']) ||
                    $_FILES['bus_image']['error'] === UPLOAD_ERR_NO_FILE
                ) {
                    $message = "Please select a bus image.";
                    $message_type = "error";
                } elseif (
                    $_FILES['bus_image']['error'] !== UPLOAD_ERR_OK
                ) {
                    $message = "Image upload failed.";
                    $message_type = "error";
                } else {
                    $upload_dir = "../uploads/";
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    $original_name = $_FILES['bus_image']['name'];
                    $tmp_name = $_FILES['bus_image']['tmp_name'];
                    $extension = strtolower(
                        pathinfo($original_name,  PATHINFO_EXTENSION)
                    );
                    $allowed = ["jpg", "jpeg", "png", "webp"];
                    if (!in_array($extension, $allowed)) {
                        $message = "Only JPG, JPEG, PNG and WEBP images are allowed.";
                        $message_type = "error";
                    } else {
                        $bus_image = "bus_" . time() . "_" . uniqid() . "." . $extension;
                        $image_path = $upload_dir . $bus_image;
                        if (
                            move_uploaded_file(
                                $tmp_name,
                                $image_path
                            )
                        ) {
                            $insert = $conn->prepare(" INSERT INTO bus (owner_id,bus_number,bus_name,bus_type,seats,bus_image,status) VALUES(?,?,?,?,?,?,'pending') ");
                            $insert->bind_param(
                                "isssis",
                                $owner_id,
                                $bus_number,
                                $bus_name,
                                $bus_type,
                                $seats,
                                $bus_image
                            );
                            if ($insert->execute()) {
                                $message = "Bus Registered Successfully.";
                                $message_type = "success";
                            } else {
                                $message = "Bus registration failed.";
                                $message_type = "error";
                            }
                        } else {
                            $message = "Image upload failed.";
                            $message_type = "error";
                        }
                    }
                }
            }
        }
    }
}
$search = "";
$sql = " SELECT * FROM bus WHERE owner_id=? ";
if (!empty($_GET['search'])) {
    $search = trim($_GET['search']);
    $sql .= "  AND ( bus_number LIKE ? OR bus_name LIKE ? ) ";
}
$sql .= " ORDER BY bus_id DESC ";
$stmt = $conn->prepare($sql);

if (!empty($search)) {
    $like = "%$search%";
    $stmt->bind_param(
        "iss",
        $owner_id,
        $like,
        $like
    );
} else {
    $stmt->bind_param("i", $owner_id);
}
$stmt->execute();
$busResult = $stmt->get_result();


$totalBusStmt = $conn->prepare("SELECT COUNT(*) FROM bus WHERE owner_id=?");
$totalBusStmt->bind_param("i", $owner_id);
$totalBusStmt->execute();
$totalResult = $totalBusStmt->get_result();
$totalBus = $totalResult->fetch_row()[0];
$pendingStmt = $conn->prepare("SELECT COUNT(*) FROM bus WHERE owner_id=? AND status='pending'");
$pendingStmt->bind_param("i", $owner_id);
$pendingStmt->execute();
$pendingResult = $pendingStmt->get_result();
$pending = $pendingResult->fetch_row()[0];
$pendingStmt->close();
$approvedStmt = $conn->prepare("SELECT COUNT(*) FROM bus WHERE owner_id=? AND status='approved'");
$approvedStmt->bind_param("i", $owner_id);
$approvedStmt->execute();
$approvedResult = $approvedStmt->get_result();
$approved = $approvedResult->fetch_row()[0];
$approvedStmt->close();
$rejectedStmt = $conn->prepare("SELECT COUNT(*) FROM bus WHERE owner_id=? AND status='rejected'");
$rejectedStmt->bind_param("i", $owner_id);
$rejectedStmt->execute();
$rejectedResult = $rejectedStmt->get_result();
$rejected = $rejectedResult->fetch_row()[0];
$rejectedStmt->close();

$driverStmt = $conn->prepare("SELECT COUNT(DISTINCT bd.driver_id) FROM bus_driver bd INNER JOIN bus b ON bd.bus_id=b.bus_id INNER JOIN users u ON bd.driver_id=u.user_id WHERE b.owner_id=? AND u.role='driver' AND u.verification_status='verified'");
$driverStmt->bind_param("i", $owner_id);
$driverStmt->execute();
$drivers = (int)$driverStmt->get_result()->fetch_row()[0];
$driverStmt->close();

$totalDriverStmt = $conn->prepare("
    SELECT COUNT(*) 
    FROM users u
    WHERE u.role = 'driver'
    AND u.verification_status = 'verified'
    AND u.user_id NOT IN (
        SELECT bd.driver_id
        FROM bus_driver bd
        INNER JOIN bus b ON bd.bus_id = b.bus_id
        WHERE b.owner_id = ?
    )
");
$totalDriverStmt->bind_param("i", $owner_id);
$totalDriverStmt->execute();
$totalDrivers = (int)$totalDriverStmt->get_result()->fetch_row()[0];
$totalDriverStmt->close();

$assignStmt = $conn->prepare(" SELECT COUNT(*) FROM bus_driver bd INNER JOIN bus b     ON bd.bus_id = b.bus_id INNER JOIN users u     ON bd.driver_id = u.user_id WHERE b.owner_id = ? AND u.role = 'driver' AND u.verification_status = 'verified' ");
$assignStmt->bind_param("i", $owner_id);
$assignStmt->execute();
$assignResult = $assignStmt->get_result();
$totalDrivers = $assignResult->fetch_row()[0];
$assignStmt->close();
$totalDriverStmt = $conn->prepare(" SELECT COUNT(*) FROM bus_driver bd INNER JOIN bus b ON bd.bus_id = b.bus_id WHERE b.owner_id = ? ");
$totalDriverStmt->bind_param("i", $owner_id);
$totalDriverStmt->execute();
$totalDriverResult = $totalDriverStmt->get_result();
$totalDrivers = (int)$totalDriverResult->fetch_row()[0];
$totalDriverStmt->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Owner Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="owner.css">
</head>

<body>
    <?php if ($verification_status == "rejected") { ?>
        <div class="verify-banner" style="border-left:6px solid red;">
            <h2>Verification Rejected</h2>
            <p><b>Reason:</b><br><?= htmlspecialchars($verify['reject_reason'] ?? '') ?></p>
            <a href="verification.php" class="verify-btn">Submit Again</a>
        </div>
    <?php } elseif (!$isVerified) { ?>
        <div class="verify-banner">
            <h2>Account Verification Required</h2>
            <p>Please complete your account verification to access owner features.</p>
            <a href="verification.php" class="verify-btn">Complete Verification</a>
        </div>
    <?php } ?>
    <?php if ($message != ""): ?>
        <div id="alertBox" class="alert <?= $message_type ?>"> <?= htmlspecialchars($message) ?> </div>
    <?php endif; ?>
    <section class="stats">
        <div class="box">
            <h2><?= $totalBus ?></h2>
            <p>Total Bus</p>
        </div>
        <div class="box">
            <h2><?= $drivers ?></h2>
            <p>Drivers</p>
        </div>
        <div class="box">
            <h2><?= $pending ?></h2>
            <p>Pending</p>
        </div>
        <div class="box">
            <h2><?= $approved ?></h2>
            <p>Approved</p>
        </div>
        <div class="box">
            <h2><?= $rejected ?></h2>
            <p>Rejected</p>
        </div>
        <div class="box">
            <h2><?= $totalDrivers ?></h2>
            <p>Unassigned Drivers</p>
        </div>
    </section>

    <section class="search-area">
        <h2>My Bus List</h2>
        <form method="GET" class="search-box">
            <input type="text" name="search" placeholder="Search Bus Number or Bus Name" value="<?= htmlspecialchars($search) ?>">
            <button type="submit"> Search </button>
        </form>
    </section>
    <section class="table-area">
        <table class="bus-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Image</th>
                    <th>Bus Number</th>
                    <th>Bus Name</th>
                    <th>Bus Type</th>
                    <th>Seats</th>
                    <th>Status</th>
                    <th>Action</th>
                    <th>Reject</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($busResult->num_rows > 0): ?>
                    <?php while ($row = $busResult->fetch_assoc()): ?>
                        <tr>
                            <td><?= $row['bus_id'] ?></td>
                            <td>
                                <?php if (!empty($row['bus_image'])) { ?>
                                    <img src="../uploads/bus/<?= htmlspecialchars($row['bus_image']) ?>" width="90" height="60" style="object-fit:cover;border-radius:6px;">
                                <?php } else { ?>
                                    <img src="../images/no-image.png" width="90" height="60">
                                <?php } ?>
                            </td>
                            <td><?= htmlspecialchars($row['bus_number']) ?></td>
                            <td><?= htmlspecialchars($row['bus_name']) ?></td>
                            <td><?= htmlspecialchars($row['bus_type']) ?></td>
                            <td><?= $row['seats'] ?></td>
                            <td>
                                <?php
                                $status = $row['status'];
                                if ($status == "approved") {
                                    echo "<span class='approved'>Approved</span>";
                                } elseif ($status == "pending") {
                                    echo "<span class='pending'>Pending</span>";
                                } else {
                                    echo "<span class='rejected'>Rejected</span>";
                                }
                                ?>
                            </td>
                            <td>
                                <?php if ($isVerified) { ?>
                                    <a href="edit_bus.php?id=<?= $row['bus_id'] ?>" class="edit-btn"> Edit </a>
                                    <a href="delete_bus.php?id=<?= $row['bus_id'] ?>" class="delete-btn" onclick="return confirm('Delete this bus?')"> Delete </a>
                                <?php } else { ?>
                                    <button class="edit-btn" disabled> Locked </button>
                                    <button class="delete-btn" disabled> Locked </button>
                                <?php } ?>
                            </td>
                            <td>
                                <?php if ($row['status'] == "rejected") { ?>
                                    <a href="reject_discription.php?id=<?= $row['bus_id'] ?>"
                                        class="view-btn">
                                        View Reason
                                    </a>
                                <?php } else { ?>
                                    -
                                <?php } ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8"> No Bus Found. </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </section>
    <?php include "owner_footer.php" ?>
    <script>
        window.onload = function() {
            const alertBox = document.getElementById("alertBox");
            if (alertBox) {
                setTimeout(function() {
                    alertBox.style.transition = "opacity .5s";
                    alertBox.style.opacity = "0";
                    setTimeout(function() {
                        alertBox.remove();
                    }, 500);
                }, 5000);
            }
        };
    </script>
</body>

</html>