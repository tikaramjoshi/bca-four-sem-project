<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != "admin") {
    header("Location: ../login.php");
    exit();
}

require_once "../db.php";

if (!isset($_GET['id'])) {
    header("Location: dashboard.php");
    exit();
}

$id = (int)$_GET['id'];

$stmt = $conn->prepare("SELECT owner_id FROM owner_verification WHERE verification_id=?");
$stmt->bind_param("i", $id);
$stmt->execute();

$result = $stmt->get_result();
$data = $result->fetch_assoc();

if (!$data) {
    header("Location: dashboard.php");
    exit();
}

$owner_id = $data['owner_id'];

if ($_SERVER['REQUEST_METHOD'] == "POST") {

    $reason = trim($_POST['reason']);

    if ($reason == "") {
        $error = "Please enter reject reason.";
    } else {

        $conn->begin_transaction();

        try {

            $stmt = $conn->prepare("
                UPDATE owner_verification
                SET status='rejected',
                    reject_reason=?
                WHERE verification_id=?
            ");
            $stmt->bind_param("si", $reason, $id);
            $stmt->execute();

            $stmt = $conn->prepare("
                UPDATE users
                SET verification_status='unverified'
                WHERE user_id=?
            ");
            $stmt->bind_param("i", $owner_id);
            $stmt->execute();

            $conn->commit();

            header("Location: dashboard.php");
            exit();
        } catch (Exception $e) {

            $conn->rollback();
            $error = "Something went wrong.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reject Owner</title>
    <style>
        body {
            font-family: Arial;
            background: #f4f4f4;
        }

        .box {
            width: 450px;
            margin: 60px auto;
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px #ccc;
        }

        textarea {
            width: 100%;
            height: 140px;
            padding: 10px;
        }

        button {
            background: red;
            color: #fff;
            border: none;
            padding: 10px 20px;
            cursor: pointer;
            margin-top: 10px;
        }

        a {
            text-decoration: none;
            margin-left: 10px;
        }

        .error {
            color: red;
            margin-bottom: 10px;
        }
    </style>
</head>

<body>
    <div class="main">
        <nav>
            <a href="dashboard.php" id="home">Home</a>
        </nav>
    </div>
    <div class="box">
        <h2>Reject Owner Verification</h2>
        <?php if (isset($error)) { ?>
            <p class="error"><?= $error ?></p>
        <?php } ?>
        <form method="POST">
            <label><b>Reject Reason:</b></label><br><br>
            <textarea name="reason"
                placeholder="Write reason for rejection..."
                required></textarea>
            <br>
            <button type="submit">Reject Owner</button>
            <a href="dashboard.php">Cancel</a>
        </form>
    </div>
</body>

</html>