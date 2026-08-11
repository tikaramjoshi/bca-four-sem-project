<?php

session_start();

if (
    !isset($_SESSION['user_id']) ||
    $_SESSION['role'] !== 'admin'
) {
    header("Location: ../login.php");
    exit();
}

require_once "../db.php";

if (!isset($_GET['id'])) {
    header("Location: dashboard.php");
    exit();
}

$verification_id = (int)$_GET['id'];


$stmt = $conn->prepare("
    UPDATE driver_verification
    SET status = 'verified'
    WHERE verification_id = ?
");

$stmt->bind_param("i", $verification_id);
$stmt->execute();

$stmt->close();


$stmt = $conn->prepare("
    UPDATE users u
    INNER JOIN driver_verification dv
        ON u.user_id = dv.driver_id
    SET u.verification_status = 'verified'
    WHERE dv.verification_id = ?
");

$stmt->bind_param("i", $verification_id);
$stmt->execute();

$stmt->close();

header("Location: dashboard.php");
exit();
