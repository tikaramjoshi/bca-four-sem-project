<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "../db.php";

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'driver') {
    header("Location: ../login.php");
    exit;
}

$driver_id = (int)$_SESSION['user_id'];

$stmt = $conn->prepare("SELECT name,profile_image FROM users WHERE user_id=? AND role='driver' LIMIT 1");
$stmt->bind_param("i", $driver_id);
$stmt->execute();
$result = $stmt->get_result();
$driver = $result->fetch_assoc();

$driver_photo = !empty($driver['profile_image']) ? "../uploads/" . $driver['profile_image'] : "../uploads/default.png";
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Scan Ticket</title>
    <link rel="stylesheet" href="dashboard.css">
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <style>
        .container {
            width: 500px;
            height: 100vh;
            margin: 100px auto;
            background: white;
            padding: 25px;
            border-radius: 10px;
        }

        .back {
            display: inline-block;
            padding: 9px 15px;
            background: #4413e5;
            color: #fff;
            text-decoration: none;
            border-radius: 6px;
            margin-bottom: 15px;
        }

        h2 {
            text-align: center;
            margin: 0 0 10px;
        }

        .info {
            text-align: center;
            color: #666;
            margin-bottom: 20px;
        }

        #reader {
            width: 100%;
        }

        .manual {
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
        }

        label {
            display: block;
            margin-bottom: 7px;
            font-weight: bold;
        }

        input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 15px;
        }

        button {
            width: 100%;
            padding: 12px;
            margin-top: 12px;
            border: 0;
            border-radius: 6px;
            background: #4413e5;
            color: white;
            font-size: 16px;
            cursor: pointer;
        }
    </style>
</head>

<body>
    <?php include "dri_header.php" ?>
    <div class="container">
        <a href="dashboard.php" class="back">Back</a>
        <h2>Scan Passenger Ticket</h2>
        <p class="info">Scan QR code or enter Booking Group ID manually.</p>
        <div id="reader"></div>
        <div class="manual">
            <h3>Manual Ticket Check</h3>
            <form action="verify_ticket.php" method="GET">
                <label>Booking Group ID</label>
                <input type="text" name="group_id" placeholder="Enter Booking Group ID" required>
                <button type="submit">Verify Ticket</button>
            </form>
        </div>
    </div>
    <form action="verify_ticket.php" method="GET">
        <label>Booking Group ID</label>
        <input type="text" name="group_id" placeholder="Enter Booking Group ID" required>
        <button type="submit">Verify Ticket</button>
    </form>
    <script>
        let alreadyScanned = false;

        function onScanSuccess(decodedText, decodedResult) {
            if (alreadyScanned) {
                return;
            }
            let groupId = decodedText.trim();
            if (groupId === "") {
                return;
            }
            alreadyScanned = true;
            window.location.href = "verify_ticket.php?group_id=" + encodeURIComponent(groupId);
        }

        function onScanFailure(error) {}
        let scanner = new Html5QrcodeScanner(
            "reader", {
                fps: 10,
                qrbox: {
                    width: 250,
                    height: 250
                },
                rememberLastUsedCamera: true
            },
            false
        );
        scanner.render(onScanSuccess, onScanFailure);

        function onScanSuccess(decodedText, decodedResult) {
            if (alreadyScanned) {
                return;
            }
            let groupId = decodedText.trim();
            if (groupId === "") {
                return;
            }
            alreadyScanned = true;
            window.location.href = "verify_ticket.php?group_id=" + encodeURIComponent(groupId);
        }
    </script>
</body>

</html>