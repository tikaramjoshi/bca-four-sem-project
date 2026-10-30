<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}
require_once "../db.php";
$id = (int)($_GET['id'] ?? 0);
$stmt = $conn->prepare("SELECT post_id,title,message,image,status FROM posts WHERE post_id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$post = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$post) {
    header("Location: view_post.php");
    exit();
}
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = trim($_POST['title'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $status = $_POST['status'] ?? 'active';
    $image = $post['image'];
    if ($title == '' || $message == '') {
        $error = "Title and message are required";
    } else {
        if (!empty($_FILES['image']['name'])) {
            $folder = "../uploads/posts/";
            if (!is_dir($folder)) {
                mkdir($folder, 0777, true);
            }
            $newImage = time() . "_" . basename($_FILES['image']['name']);
            if (move_uploaded_file($_FILES['image']['tmp_name'], $folder . $newImage)) {
                if (!empty($image) && file_exists($folder . $image)) {
                    unlink($folder . $image);
                }
                $image = $newImage;
            }
        }
        $stmt = $conn->prepare("UPDATE posts SET title=?,message=?,image=?,status=? WHERE post_id=?");
        $stmt->bind_param("ssssi", $title, $message, $image, $status, $id);
        if ($stmt->execute()) {
            $stmt->close();
            header("Location: view_post.php");
            exit();
        }
        $error = "Post update failed";
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html>

<head>
    <title>Edit Post</title>
    <link rel="stylesheet" href="edit_post.css">
</head>

<body>
    <?php include 'admin_header.php' ?>
    <div class="content">
        <div class="form-box">
            <h2>Edit Post</h2>
            <?php if (isset($error)) { ?>
                <div class="error"><?= htmlspecialchars($error) ?></div>
            <?php } ?>
            <form method="POST" enctype="multipart/form-data">
                <input type="text" name="title" value="<?= htmlspecialchars($post['title']) ?>" placeholder="Post Title" required>
                <textarea name="message" placeholder="Post Message" required><?= htmlspecialchars($post['message']) ?></textarea>
                <?php if (!empty($post['image'])) { ?>
                    <img class="current-img" src="../uploads/posts/<?= htmlspecialchars($post['image']) ?>">
                <?php } ?>
                <input type="file" name="image" accept="image/*">
                <select name="status">
                    <option value="active" <?= $post['status'] == 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $post['status'] == 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
                <button type="submit"><i class="fa fa-save"></i>&nbsp; Update Post</button>
                <button type="button" class="view-btn" onclick="window.location.href='view_post.php'"><i class="fa fa-arrow-left"></i>&nbsp; Back</button>
            </form>
        </div>
    </div>
    </div>
</body>

</html>