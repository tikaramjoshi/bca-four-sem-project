<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != "owner") {
    header("Location: ../login.php");
    exit();
}
require_once "../db.php";
$owner_id = (int)$_SESSION['user_id'];
$bus_id = (int)($_GET['id'] ?? 0);
if ($bus_id <= 0) {
    header("Location: dashboard.php");
    exit();
}
$stmt = $conn->prepare("SELECT bus_image FROM bus WHERE bus_id=? AND owner_id=?");
$stmt->bind_param("ii", $bus_id, $owner_id);
$stmt->execute();
$result = $stmt->get_result();
$bus = $result->fetch_assoc();
$stmt->close();
if (!$bus) {
    $_SESSION['error'] = "Bus not found.";
    header("Location: dashboard.php");
    exit();
}
if (!empty($bus['bus_image'])) {
    $image = "../uploads/bus/" . $bus['bus_image'];
    if (file_exists($image)) {
        unlink($image);
    }
}
$stmt = $conn->prepare("DELETE FROM bus WHERE bus_id=? AND owner_id=?");
$stmt->bind_param("ii", $bus_id, $owner_id);
if ($stmt->execute()) {
    $_SESSION['success'] = "Bus deleted successfully.";
} else {
    $_SESSION['error'] = "Bus delete failed.";
}
$stmt->close();
header("Location: dashboard.php");
exit();
