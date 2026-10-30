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
    <link rel="stylesheet" href="policy.css">
</head>

<body>
    <?php include "admin_header.php" ?>
    <div class="content">
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
        <div class="table-container">
            <table class="table-box">

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
                            <td> <a class="edit action" href="?edit=<?= $row['id'] ?>"><i class="fa fa-edit"></i>&nbsp; Edit</a>
                                <a class="delete action" href="?delete=<?= $row['id'] ?>" onclick="return confirm('Delete this policy?')"><i class="fa fa-trash"></i> &nbsp; Delete </a>
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
        </div>
    </div>

</body>

</html>