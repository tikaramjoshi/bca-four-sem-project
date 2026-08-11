<?php

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != "owner") {
    header("Location: ../login.php");
    exit();
}


require_once "../db.php";


$owner_id = $_SESSION['user_id'];


if (!isset($_GET['id'])) {
    header("Location: dashboard.php");
    exit();
}


$bus_id = (int)$_GET['id'];



$stmt = $conn->prepare("
SELECT 
bus_name,
bus_number,
reject_reason,
status
FROM bus
WHERE bus_id=?
AND owner_id=?
");


$stmt->bind_param(
    "ii",
    $bus_id,
    $owner_id
);


$stmt->execute();


$result = $stmt->get_result();


$bus = $result->fetch_assoc();



if (!$bus) {

    die("Bus Not Found");
}


?>


<!DOCTYPE html>
<html>

<head>

    <title>Reject Reason</title>


    <style>
        body {

            font-family: Arial;
            background: #f4f7fb;

        }


        .box {

            width: 450px;
            background: white;
            margin: 80px auto;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 15px #ccc;

        }


        h2 {

            color: red;
            text-align: center;

        }


        .reason {

            background: #ffe5e5;
            padding: 15px;
            border-left: 5px solid red;
            margin-top: 20px;

        }


        .back {

            display: block;
            margin-top: 20px;
            background: #1560BD;
            color: white;
            padding: 12px;
            text-align: center;
            text-decoration: none;
            border-radius: 5px;

        }
    </style>

</head>


<body>


    <div class="box">


        <h2>
            Bus Rejected
        </h2>


        <p>
            <b>Bus Number:</b>
            <?= htmlspecialchars($bus['bus_number']) ?>
        </p>


        <p>
            <b>Bus Name:</b>
            <?= htmlspecialchars($bus['bus_name']) ?>
        </p>



        <div class="reason">

            <b>Admin Reject Reason:</b>

            <br><br>

            <?= nl2br(htmlspecialchars($bus['reject_reason'])) ?>


        </div>



        <a href="dashboard.php" class="back">
            Back
        </a>


    </div>


</body>

</html>