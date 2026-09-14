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
            width: 500px;
            max-width: 90%;
            margin: 40px auto;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 3px 15px rgba(0, 0, 0, .1)
        }

        .container h2 {
            text-align: center;
            margin-top: 0
        }

        input,
        textarea {
            width: 100%;
            padding: 12px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 15px
        }

        textarea {
            height: 130px;
            resize: none
        }

        input[type=file] {
            padding: 10px
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
    </style>
</head>

<body>
    <div class="top">
        <h2>Add Post</h2>
        <a href="view_post.php">View Posts</a>
    </div>
    <div class="container">
        <h2>Create New Post</h2>
        <form action="save_post.php" method="POST" enctype="multipart/form-data">
            <input type="text" name="title" placeholder="Post Title" required>
            <textarea name="message" placeholder="Post Message" required></textarea>
            <input type="file" name="image" accept="image/*">
            <button type="submit">Post</button>
        </form>
    </div>
</body>

</html>