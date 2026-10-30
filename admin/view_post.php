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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Posts</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="view_post.css">
</head>

<body>
    <?php include 'admin_header.php' ?>
    <div class="content">
        <a href="add_post.php" class="view-btn"><i class="fa fa-plus"></i> &nbsp; Add Post</a>
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
                                    <a class="edit" href="edit_post.php?id=<?= $post['post_id'] ?>"><i class="fa fa-edit"></i>&nbsp; Edit</a>
                                    <a class="delete" href="delete_post.php?id=<?= $post['post_id'] ?>" onclick="return confirm('Are you sure you want to delete this post?')"><i class="fa fa-trash"></i>&nbsp; Delete</a>
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