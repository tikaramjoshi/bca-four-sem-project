<?php
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
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
    SELECT owner_id
    FROM owner_verification
    WHERE verification_id=?
    LIMIT 1
");
$stmt->bind_param("i", $verification_id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$data) {
    header("Location: dashboard.php");
    exit();
}

$owner_id = (int)$data['owner_id'];

$conn->begin_transaction();

try {

    $stmt = $conn->prepare("
        UPDATE owner_verification
        SET status='verified',
            reject_reason=NULL
        WHERE verification_id=?
    ");
    $stmt->bind_param("i", $verification_id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("
        UPDATE users
        SET verification_status='verified'
        WHERE user_id=?
        AND role='owner'
    ");
    $stmt->bind_param("i", $owner_id);
    $stmt->execute();
    $stmt->close();

    $conn->commit();
} catch (Exception $e) {

    $conn->rollback();
}

header("Location: dashboard.php");
exit();
