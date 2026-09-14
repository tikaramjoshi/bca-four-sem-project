<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}
require_once "../db.php";
$result = $conn->query("SELECT * FROM posts ORDER BY post_id DESC");
?>
<!DOCTYPE html>
<html>

<head>
    <title>View Posts</title>
    <style>
        * {
            box-sizing: border-box
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
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
            width: 95%;
            margin: 30px auto;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 3px 15px rgba(0, 0, 0, .1)
        }

        h3 {
            text-align: center;
            margin-top: 0
        }

        .table-box {
            overflow-x: auto
        }

        table {
            width: 100%;
            border-collapse: collapse
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left
        }

        th {
            background: #4413e5;
            color: white
        }

        tr:nth-child(even) {
            background: #f8f8f8
        }

        .post-img {
            width: 100px;
            height: 60px;
            object-fit: cover;
            border-radius: 5px
        }

        .btn {
            padding: 7px 12px;
            border-radius: 5px;
            text-decoration: none;
            color: white;
            display: inline-block
        }

        .edit {
            background: #198754
        }

        .delete {
            background: #dc3545
        }

        .active {
            color: green;
            font-weight: bold
        }

        .inactive {
            color: red;
            font-weight: bold
        }

        .no-post {
            text-align: center;
            padding: 30px
        }
    </style>
</head>

<body>
    <div class="top">
        <h2>View Posts</h2>
        <a href="add_post.php">+ Add Post</a>
    </div>
    <div class="container">
        <h3>All Posts</h3>
        <div class="table-box">
            <table>
                <tr>
                    <th>ID</th>
                    <th>Image</th>
                    <th>Title</th>
                    <th>Message</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
                <?php if ($result && $result->num_rows > 0) { ?>
                    <?php while ($post = $result->fetch_assoc()) { ?>
                        <tr>
                            <td><?= $post['post_id'] ?></td>
                            <td>
                                <?php if (!empty($post['image'])) { ?>
                                    <img class="post-img" src="../uploads/posts/<?= htmlspecialchars($post['image']) ?>">
                                <?php } else { ?>
                                    No Image
                                <?php } ?>
                            </td>
                            <td><?= htmlspecialchars($post['title']) ?></td>
                            <td><?= nl2br(htmlspecialchars($post['message'])) ?></td>
                            <td class="<?= $post['status'] == 'active' ? 'active' : 'inactive' ?>">
                                <?= htmlspecialchars($post['status']) ?>
                            </td>
                            <td>
                                <a class="btn edit" href="edit_post.php?id=<?= $post['post_id'] ?>">Edit</a>
                                <a class="btn delete" href="delete_post.php?id=<?= $post['post_id'] ?>" onclick="return confirm('Are you sure you want to delete this post?')">Delete</a>
                            </td>
                        </tr>
                    <?php } ?>
                <?php } else { ?>
                    <tr>
                        <td colspan="6" class="no-post">No Posts Found</td>
                    </tr>
                <?php } ?>
            </table>
        </div>
    </div>
</body>

</html>