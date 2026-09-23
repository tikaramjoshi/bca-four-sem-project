<?php
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'driver') {
    header("Location: ../login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Scan Ticket</title>
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <style>
        * {
            box-sizing: border-box
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f6f9;
        }

        .container {
            width: 90%;
            max-width: 500px;
            margin: 40px auto;
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
    </script>

</body>

</html>