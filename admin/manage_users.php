<?php
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../login.php");
    exit();
}
require_once "../db.php";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users</title>
    <link rel="stylesheet" href="dashboard_admin.css">
    <style>
        .manage-user-cards {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
            width: 98%;
            justify-content: center;

        }

        .manage-user-card {
            background: #e6ece9d4;
            padding: 35px 25px;
            border-radius: 10px;
            text-align: center;
            box-sizing: border-box;
            min-width: 0;
        }

        .manage-user-card p {
            font-size: 25px;
            margin-bottom: 35px;
        }

        .manage-user-btn {
            background-color: maroon;
            padding: 13px 15px;
            text-decoration: none;
            color: #fff;
            border-radius: 10px;
            display: inline-block;
        }

        @media (max-width: 700px) {
            .manage-user-cards {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <?php include "admin_header.php"; ?>
    <div class="content">
        <div class="section-title">
            <h2>Manage Users</h2>
        </div>
        <div class="manage-user-cards">
            <div class="manage-user-card">
                <p>View all users and Reset Password</p>
                <a href="reset_password.php" class="manage-user-btn">Reset Password</a>
            </div>
            <div class="manage-user-card">
                <p>View all users and Change Roles</p>
                <a href="change_role.php" class="manage-user-btn">Change Roles</a>
            </div>
        </div>
    </div>
    <script>
        function toggleMenu() {
            document.getElementById("settingMenu").classList.toggle("show");
        }
        window.addEventListener("click", function(e) {
            if (!e.target.closest(".setting")) {
                document.getElementById("settingMenu").classList.remove("show");
            }
        });
    </script>
</body>

</html>