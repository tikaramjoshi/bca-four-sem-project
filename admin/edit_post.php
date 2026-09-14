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
    <style>
        * {
            box-sizing: border-box
        }

        body {
            margin: 0;
            font-family: Arial;
            background: #f4f6f9
        }

        .top {
            background: #4413e5;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center
        }

        .top h2 {
            color: white;
            margin: 0
        }

        .top a {
            background: white;
            color: #4413e5;
            text-decoration: none;
            padding: 10px 18px;
            border-radius: 6px;
            font-weight: bold
        }

        .container {
            width: 550px;
            max-width: 92%;
            margin: 40px auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 3px 15px rgba(0, 0, 0, .1)
        }

        .container h2 {
            text-align: center;
            margin-top: 0
        }

        input,
        textarea,
        select {
            width: 100%;
            padding: 12px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 15px
        }

        textarea {
            height: 160px;
            resize: vertical
        }

        .current-img {
            width: 150px;
            height: 90px;
            object-fit: cover;
            border-radius: 6px;
            margin: 10px 0
        }

        button {
            width: 100%;
            padding: 12px;
            background: #4413e5;
            color: white;
            border: 0;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer
        }

        button:hover {
            background: #3510b5
        }

        .error {
            background: #ffe5e5;
            color: red;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 10px
        }
    </style>
</head>

<body>
    <div class="top">
        <h2>Edit Post</h2>
        <a href="view_post.php">View Posts</a>
    </div>
    <div class="container">
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
            <button type="submit">Update Post</button>
        </form>
    </div>
</body>

</html>