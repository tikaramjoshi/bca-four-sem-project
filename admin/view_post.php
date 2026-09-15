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
            border-top: 5px solid #fff;
            border-bottom: 5px solid #fff;
            border-right: 15px solid #f3ba1e;
        }

        .content {
            margin-left: 200px;
            width: calc(100% - 200px);
            min-height: calc(100vh - 70px);
            padding: 100px 30px 30px;
        }

        .section-title {
            margin-bottom: 20px;
        }

        .section-title h2 {
            color: #222;
        }

        .table-box {
            width: 100%;
            margin-bottom: 20px;
            padding: 20px;
            background: #fff;
            border-radius: 10px;
        }

        .table-box h2 {
            margin-bottom: 10px;
            color: #222;
        }

        .table-scroll {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            margin-top: 15px;
            border-collapse: collapse;
        }

        table th,
        table td {
            padding: 12px;
            border: 1px solid #ddd;
            text-align: center;
            white-space: nowrap;
        }

        table th {
            background: #1560bd;
            color: #fff;
        }

        table tr:nth-child(even) {
            background: #f8f9fa;
        }

        .post-img {
            width: 100px;
            height: 60px;
            object-fit: cover;
            border-radius: 6px;
        }

        .edit,
        .delete {
            display: inline-block;
            padding: 8px 12px;
            color: #fff;
            text-decoration: none;
            border-radius: 5px;
            margin: 2px;
        }

        .edit {
            background: green;
        }

        .delete {
            background: red;
        }

        .edit:hover {
            background: #087308;
        }

        .delete:hover {
            background: #c40000;
        }

        .active-status {
            color: green;
            font-weight: bold;
        }

        .inactive-status {
            color: red;
            font-weight: bold;
        }

        .no-post {
            padding: 20px;
            text-align: center;
            color: green;
            font-size: 18px;
        }

        @media (max-width: 500px) {
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
                padding: 100px 15px 15px;
            }
        }

        .view-btn {
            display: block;
            width: 150px;
            text-align: center;
            margin-left: auto;
            margin-right: 30px;
            margin-bottom: 20px;
            padding: 12px 20px;
            background: #1560bd;
            color: white;
            text-decoration: none;
            border-radius: 6px;
        }

        .view-btn:hover {
            background: #104d99;
        }
    </style>


</head>

<body>

    <?php include 'admin_header.php' ?>

    <div class="content">
        <a href="add_post.php" class="view-btn">Add Post</a>
        <div class="section-title">
            <h2>Post Management</h2>
        </div>

        <div class="table-box">
            <h2>All Posts</h2>

            <div class="table-scroll">
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

                                <td class="<?= $post['status'] == 'active' ? 'active-status' : 'inactive-status' ?>">
                                    <?= htmlspecialchars($post['status']) ?>
                                </td>

                                <td>
                                    <a class="edit" href="edit_post.php?id=<?= $post['post_id'] ?>">Edit</a>
                                    <a class="delete" href="delete_post.php?id=<?= $post['post_id'] ?>" onclick="return confirm('Are you sure you want to delete this post?')">Delete</a>
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
    </div>

</body>

</html>