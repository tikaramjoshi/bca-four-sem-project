<?php
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../login.php");
    exit();
}

require_once "../db.php";

if (!isset($_GET['id']) || (int)$_GET['id'] <= 0) {
    die("Invalid passenger ID");
}

$user_id = (int)$_GET['id'];

$stmt = $conn->prepare("
    UPDATE users
    SET verification_status = 'rejected'
    WHERE user_id = ?
    AND role = 'passenger'
");

if (!$stmt) {
    die("Prepare Error: " . $conn->error);
}

$stmt->bind_param("i", $user_id);

if (!$stmt->execute()) {
    die("Execute Error: " . $stmt->error);
}

if ($stmt->affected_rows > 0) {
    $_SESSION['success'] = "Passenger rejected successfully.";
} else {
    $_SESSION['error'] = "No record updated. Check passenger ID or current status.";
}

$stmt->close();

header("Location: dashboard.php");
exit();
