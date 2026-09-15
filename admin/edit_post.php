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
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        html,
        body {
            min-height: 100%;
            background: #f4f6f9;
        }

        .header {
            height: 70px;
            width: 100%;
            background: #1560bd;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 25px;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 2000;
        }

        .header h2 {
            font-size: 24px;
        }

        .container {
            min-height: 100vh;
            padding-top: 50px;
        }

        .sidebar {
            position: fixed;
            top: 70px;
            left: 0;
            bottom: 0;
            width: 190px;
            background: #1d2c4e;
            overflow-y: auto;
            z-index: 1500;
        }

        .sidebar a {
            display: block;
            padding: 15px 20px;
            color: #fff;
            text-decoration: none;
            border-bottom: 1px solid rgba(255, 255, 255, 0.09);
            transition: 0.2s;
        }

        .sidebar a:hover {
            background: #8c0f3b;
        }

        .sidebar a.active {
            background: #1560bd;
            color: #fff;
            font-weight: bold;
            box-shadow: rgb(255, 0, 0);
            border-top: 5px solid #fff;
            border-bottom: 5px solid #fff;
            border-right: 15px solid #f3ba1e;
        }

        .content {
            margin-left: 200px;
            width: calc(100% - 200px);
            min-height: calc(100vh - 70px);
            padding: 30px;
        }

        .form-box {
            width: 550px;
            max-width: 100%;
            margin: 20px auto;
            background: #fff;
            padding: 30px;
            border-radius: 10px;
        }

        .form-box h2 {
            text-align: center;
            margin-bottom: 20px;
            color: #222;
        }

        .view-btn {
            display: block;
            width: 40%;
            text-align: center;
            margin-top: 10px;
            padding: 12px;
            background: #2f353d;
            color: white;
            text-decoration: none;
            border-radius: 6px;
        }

        .view-btn:hover {
            background: #104d99;
        }

        input,
        textarea,
        select {
            width: 100%;
            padding: 12px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 15px;
        }

        textarea {
            height: 160px;
            resize: vertical;
        }

        .current-img {
            width: 150px;
            height: 90px;
            object-fit: cover;
            border-radius: 6px;
            margin: 10px 0;
            display: block;
        }

        button {
            width: 100%;
            padding: 12px;
            background: #1560bd;
            color: #fff;
            border: 0;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
        }

        button:hover {
            background: #0d4f9e;
        }

        .error {
            background: #ffe5e5;
            color: red;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 10px;
        }

        @media(max-width:500px) {
            .header {
                padding: 0 15px;
            }

            .header h2 {
                font-size: 20px;
            }

            .sidebar {
                width: 180px;
            }

            .content {
                margin-left: 180px;
                width: calc(100% - 180px);
                padding: 15px;
            }
        }
    </style>
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
                <button type="submit">Update Post</button>
                <a href="view_post.php" class="view-btn">Back</a>
            </form>
        </div>
    </div>
    </div>
</body>

</html>