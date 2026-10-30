<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != "admin") {
    header("Location: ../login.php");
    exit();
}
require_once "../db.php";
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: dashboard.php");
    exit();
}
$bus_id = (int)$_GET['id'];
$stmt = $conn->prepare(" SELECT bus_id,bus_name,bus_number FROM bus WHERE bus_id=? ");
$stmt->bind_param("i", $bus_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows == 0) {
    die("Bus not found.");
}
$bus = $result->fetch_assoc();
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $reason = trim($_POST['reason']);
    if ($reason == "") {
        $error = "Please enter reject reason.";
    } else {
        $stmt = $conn->prepare("  UPDATE bus  SET status='rejected',      reject_reason=?  WHERE bus_id=?  ");
        $stmt->bind_param("si", $reason, $bus_id);
        if ($stmt->execute()) {
            header("Location: dashboard.php");
            exit();
        } else {
            $error = "Failed to reject bus.";
        }
    }
}
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reject Bus</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f4f4;
        }

        .box {
            width: 450px;
            margin: 60px auto;
            background: #fff;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 0 10px #ccc;
        }

        h2 {
            margin-bottom: 20px;
        }

        textarea {
            width: 100%;
            height: 140px;
            padding: 10px;
            resize: none;
        }

        button,
        .cancel {
            color: #fff;
            border: none;
            padding: 10px;
            cursor: pointer;
            border-radius: 10px;
        }

        .button {
            background: red;
        }

        .cancel {
            background-color: #242a56;
        }

        .error {
            color: red;
            margin-bottom: 10px;
        }

        .info {
            margin-bottom: 15px;
        }
    </style>
</head>

<body>
    <div class="box">
        <h2>Reject Bus</h2>
        <div class="info">
            <b>Bus Number:</b> <?= htmlspecialchars($bus['bus_number']) ?>
            <br><br>
            <b>Bus Name:</b><?= htmlspecialchars($bus['bus_name']) ?>
        </div>
        <?php if (isset($error)) { ?>
            <div class="error"> <?= $error ?> </div>
        <?php } ?>
        <form method="POST">
            <label><b>Reject Reason</b></label><br><br>
            <textarea name="reason" placeholder="Write reject reason..." required></textarea>
            <br><br>
            <button type="submit" class="button"><i class="fa fa-close"></i> Reject Bus </button>
            <button type="submit" class="cancel" onclick="window.location.href='dashboard.php'"><i class="fa fa-home"></i> Dashboard</button>
        </form>
    </div>
</body>

</html>