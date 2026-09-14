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

header("Location: dashboard.php");
exit();
