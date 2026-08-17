<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != "admin") {
    header("Location: ../login.php");
    exit();
}

require_once "../db.php";

$stmt = $conn->prepare("SELECT role FROM users WHERE user_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user || $user['role'] !== "admin") {
    session_destroy();
    header("Location: ../login.php");
    exit();
}

$edit = false;
$edit_id = 0;
$edit_text = "";

if (isset($_POST['save'])) {
    $policy_text = trim($_POST['policy_text']);
    if ($policy_text != "") {
        $stmt = $conn->prepare("INSERT INTO policy(policy_text) VALUES(?)");
        $stmt->bind_param("s", $policy_text);
        $stmt->execute();
    }
    header("Location: policy.php");
    exit();
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM policy WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: policy.php");
    exit();
}

if (isset($_GET['edit'])) {
    $edit = true;
    $edit_id = (int)$_GET['edit'];

    $stmt = $conn->prepare("SELECT policy_text FROM policy WHERE id=?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();

    if ($row = $stmt->get_result()->fetch_assoc()) {
        $edit_text = $row['policy_text'];
    }
}

if (isset($_POST['update'])) {
    $id = (int)$_POST['id'];
    $policy_text = trim($_POST['policy_text']);

    $stmt = $conn->prepare("UPDATE policy SET policy_text=? WHERE id=?");
    $stmt->bind_param("si", $policy_text, $id);
    $stmt->execute();

    header("Location: policy.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Policy</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f7f6;
            margin: 0;
            padding-top: 80px;
        }

        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            background: #1560BD;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 40px;
            z-index: 1000;
        }

        .nav-links {
            display: flex;
            gap: 15px;
        }

        .nav-links a {
            background-color: rgb(165, 154, 239);
            color: #000;
            text-decoration: none;
            padding: 10px 18px;
            border-radius: 5px;
            font-weight: bold;
            transition: .3s;
        }

        button:hover,
        .nav-links a:hover {
            background: #0d9916;
        }

        h2 {
            margin-bottom: 20px;
            color: #1560BD;
            text-align: center;
        }

        form,
        table {
            width: 95%;
            margin: 20px auto;
            background: #fff;
        }

        form {
            padding: 15px;
            border-radius: 8px;
        }

        table {
            border-collapse: collapse;
        }

        textarea {
            width: 100%;
            height: 100px;
            padding: 12px;
            resize: none;
            border: 2px solid #1560BD;
            border-radius: 8px;
            outline: none;
            font-size: 15px;
            box-sizing: border-box;
            margin-bottom: 15px;
        }

        textarea:focus {
            border-color: #0d4d99;
        }

        button {
            background: #1560BD;
            color: #fff;
            border: none;
            padding: 10px 20px;
            cursor: pointer;
            border-radius: 5px;
        }

        table th,
        table td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }

        table th {
            background: #1560BD;
            color: #fff;
        }

        table th:last-child,
        table td:last-child {
            width: 120px;
            text-align: center;
            white-space: nowrap;
        }

        .edit,
        .delete {
            color: #fff;
            padding: 6px 12px;
            text-decoration: none;
            border-radius: 4px;
            margin: 10px;
        }

        .edit {
            background: orange;
        }

        .delete {
            background: red;
        }


        .last {
            background-color: #1560BD;
            color: white;
            text-align: center;
            padding: 15px 0;
            width: 100%;
        }
    </style>
</head>

<body>
    <div class="nav-links navbar">
        <a href="dashboard.php" class="active"> <i class="fa fa-home"></i>&nbsp;Home</a>
    </div>

    <h2>Manage Booking Policy</h2>
    <form method="post">
        <?php if ($edit) { ?>
            <input type="hidden" name="id" value="<?= $edit_id ?>">
        <?php } ?>
        <textarea name="policy_text" required><?= htmlspecialchars($edit_text) ?></textarea>
        <?php if ($edit) { ?>
            <button type="submit" name="update"> Update Policy </button>
            <a href="policy.php"> Cancel </a>
        <?php } else { ?>
            <button type="submit" name="save"> <i class="fa fa-plus"></i>&nbsp; Add Policy </button>
        <?php } ?>
    </form>
    <table>
        <tr>
            <th>No</th>
            <th>Policy</th>
            <th>Action</th>
        </tr>
        <?php
        $result = $conn->query("SELECT * FROM policy ORDER BY id ASC");
        if (!$result) {
            die($conn->error);
        }
        $i = 1;
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
        ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td><?= htmlspecialchars($row['policy_text']) ?></td>
                    <td> <a class="edit" href="?edit=<?= $row['id'] ?>">Edit</a>
                        <a class="delete" href="?delete=<?= $row['id'] ?>" onclick="return confirm('Delete this policy?')"> Delete </a>
                    </td>
                </tr>
            <?php
            }
        } else {
            ?>
            <tr>
                <td colspan="3" style="text-align:center;"> No Policy Found </td>
            </tr>
        <?php
        }
        ?>
    </table>
    <footer class="last">
        <p>&copy;2026 Online Bus Ticket Booking System | All rights reserved.</p>
    </footer>
</body>

</html>