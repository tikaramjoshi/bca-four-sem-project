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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scan Ticket</title>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f6f9;
        }

        .container {
            max-width: 600px;
            margin: 40px auto;
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        h2 {
            text-align: center;
            margin-bottom: 25px;
        }

        #reader {
            width: 100%;
            max-width: 450px;
            margin: auto;
        }

        .manual {
            margin-top: 30px;
            border-top: 1px solid #ddd;
            padding-top: 20px;
        }

        input {
            width: 100%;
            padding: 12px;
            margin-top: 8px;
            border: 1px solid #ccc;
            border-radius: 6px;
        }

        button {
            width: 100%;
            margin-top: 12px;
            padding: 12px;
            border: none;
            border-radius: 6px;
            background: #4413e5;
            color: white;
            font-size: 16px;
            cursor: pointer;
        }

        button:hover {
            opacity: 0.9;
        }

        .info {
            text-align: center;
            color: #666;
            margin-bottom: 20px;
        }
    </style>
</head>

<body>
    <div class="container">
        <h2>Scan Passenger Ticket</h2>
        <p class="info"> Scan the passenger QR code using your camera. </p>
        <div id="reader"></div>
        <div class="manual">
            <h3>Manual Ticket Check</h3>
            <form action="verify_ticket.php" method="GET">
                <label>Booking Group ID</label>
                <input type="text" name="group_id" placeholder="Enter Booking Group ID" required>
                <button type="submit"> Verify Ticket </button>
            </form>
        </div>
    </div>
    <script>
        let alreadyScanned = false;

        function onScanSuccess(decodedText, decodedResult) {
            if (alreadyScanned) {
                return;
            }
            alreadyScanned = true;
            let groupId = decodedText.trim();
            if (groupId === "") {
                alreadyScanned = false;
                return;
            }
            window.location.href =
                "verify_ticket.php?group_id=" +
                encodeURIComponent(groupId);
        }

        function onScanFailure(error) {}
        let scanner = new Html5QrcodeScanner(
            "reader", {
                fps: 10,
                qrbox: {
                    width: 250,
                    height: 250
                }
            },
            false
        );
        scanner.render(onScanSuccess, onScanFailure);
    </script>
</body>

</html>