<?php
session_start();
require_once "../db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

$title = trim($_POST['title']);
$message = trim($_POST['message']);
$image = null;

if (!empty($_FILES['image']['name'])) {
    $folder = "../uploads/posts/";

    if (!is_dir($folder)) {
        mkdir($folder, 0777, true);
    }

    $image = time() . "_" . basename($_FILES['image']['name']);
    move_uploaded_file($_FILES['image']['tmp_name'], $folder . $image);
}

$stmt = $conn->prepare("INSERT INTO posts (title,message,image,status) VALUES (?,?,?,'active')");
$stmt->bind_param("sss", $title, $message, $image);
$stmt->execute();

if ($stmt->execute()) {
    $stmt->close();

    $notificationTitle = "New Post: " . $title;
    $notificationMessage = $message;
    $link = "dashboard.php";

    $notifyStmt = $conn->prepare("
        INSERT INTO notifications
        (user_id, type, title, message, link, is_read, created_at)
        SELECT user_id, 'system', ?, ?, ?, 0, NOW()
        FROM users
        WHERE role = 'passenger'
    ");

    if ($notifyStmt) {
        $notifyStmt->bind_param("sss", $notificationTitle, $notificationMessage, $link);
        $notifyStmt->execute();
        $notifyStmt->close();
    }
} else {
    $stmt->close();
}
header("Location: dashboard.php");
exit();
