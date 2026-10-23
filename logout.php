<?php
session_start();
session_unset();
session_destroy();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Logout</title>
    <style>
        body {
            font-family: Arial, sans-serif;
        }

        .logout-message {
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            color: #28a745;
            padding: 18px 25px;
            border-left: 5px solid #28a745;
            border-radius: 8px;
            box-shadow: 0 4px 15px #0002;
            justify-content: center;

        }

        div {
            justify-content: center;
            display: flex;
        }
    </style>
</head>

<body>
    <div class="logout-message">
        <strong>Logout Successful!</strong>
        <p>You have logged out successfully.</p>
    </div>
    <script>
        setTimeout(function() {
            window.location.replace("index.php");
        }, 1000);
    </script>
</body>

</html>