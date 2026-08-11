<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != "admin") {
    header("Location: ../login.php");
    exit();
}

require_once "../db.php";

if (!isset($_GET['id'])) {
    header("Location: dashboard.php");
    exit();
}

$id = (int)$_GET['id'];

$stmt = $conn->prepare("
    SELECT owner_id
    FROM owner_verification
    WHERE verification_id=?
");
$stmt->bind_param("i", $id);
$stmt->execute();

$result = $stmt->get_result();
$data = $result->fetch_assoc();

if (!$data) {
    header("Location: dashboard.php");
    exit();
}

$owner_id = $data['owner_id'];

$conn->begin_transaction();

try {

    $stmt = $conn->prepare("
        UPDATE owner_verification
        SET status='verified',
            reject_reason=NULL
        WHERE verification_id=?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    $stmt = $conn->prepare("
        UPDATE users
        SET verification_status='verified'
        WHERE user_id=?
    ");
    $stmt->bind_param("i", $owner_id);
    $stmt->execute();

    $conn->commit();
} catch (Exception $e) {

    $conn->rollback();
}

header("Location: dashboard.php");
exit();
