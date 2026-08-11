<?php

session_start();

if (
    !isset($_SESSION['user_id']) ||
    $_SESSION['role'] != 'admin'
) {
    header("Location: ../login.php");
    exit();
}

require_once "../db.php";

if (!isset($_GET['id'])) {
    header("Location: dashboard.php");
    exit();
}

$user_id = (int) $_GET['id'];

$stmt = $conn->prepare("
    UPDATE users
    SET verification_status = 'verified'
    WHERE user_id = ?
    AND role = 'passenger'
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$stmt->close();

header("Location: dashboard.php");
exit();
