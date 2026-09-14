<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}
require_once "../db.php";
$id = (int)($_GET['id'] ?? 0);
$stmt = $conn->prepare("SELECT image FROM posts WHERE post_id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$post = $stmt->get_result()->fetch_assoc();
$stmt->close();
if ($post) {
    if (!empty($post['image']) && file_exists("../uploads/posts/" . $post['image'])) {
        unlink("../uploads/posts/" . $post['image']);
    }
    $stmt = $conn->prepare("DELETE FROM posts WHERE post_id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
}
header("Location: view_post.php");
exit();
