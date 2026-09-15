<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}
?>

<!DOCTYPE html>

<html>

<head>
    <title>Add Post</title>
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

        .content {
            margin-left: 200px;
            width: calc(100% - 200px);
            min-height: calc(100vh - 70px);
            padding: 100px 30px 30px;
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




        .form-box {
            width: 500px;
            max-width: 90%;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 3px 15px rgba(0, 0, 0, .1);
        }

        .form-box h2 {
            text-align: center;
            margin-bottom: 20px;
        }

        input,
        textarea {
            width: 100%;
            padding: 12px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 15px;
        }

        textarea {
            height: 130px;
            resize: none;
        }

        input[type=file] {
            padding: 10px;
        }

        button {
            width: 100%;
            padding: 12px;
            background: #1560bd;
            color: white;
            border: 0;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
        }

        button:hover {
            background: #104d99;
        }

        @media (max-width: 500px) {
            .content {
                margin-left: 180px;
                width: calc(100% - 180px);
                padding: 100px 15px 15px;
            }
        }
    </style>

</head>

<body>

    <?php include 'admin_header.php'; ?>

    <div class="content">

        <div class="form-box">
            <h2>Create New Post</h2>

            <form action="save_post.php" method="POST" enctype="multipart/form-data">
                <input type="text" name="title" placeholder="Post Title" required>
                <textarea name="message" placeholder="Post Message" required></textarea>
                <input type="file" name="image" accept="image/*">

                <button type="submit">Post</button>
                <a href="view_post.php" class="view-btn">Back</a>

            </form>
        </div>

    </div>

</body>

</html>