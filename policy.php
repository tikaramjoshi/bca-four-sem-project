<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Policy</title>
    <style>
        * {
            padding: 0;
            margin: 0;
            box-sizing: border-box;
        }

        html,
        body {
            height: 100%;
        }

        body {
            font-family: Arial, sans-serif;
            display: flex;
            flex-direction: column;
            background: #f4f7f6;
        }

        .main {
            background-color: #1560BD;
            padding: 10px 25px;
        }

        nav {
            display: flex;
            justify-content: flex-start;
            align-items: center;
        }

        nav a {
            text-decoration: none;
            color: black;
            background-color: rgb(165, 154, 239);
            padding: 8px 15px;
            font-weight: bold;
            border-radius: 4px;
        }

        nav a:hover {
            color: white;
            background-color: rgb(15, 160, 112);
        }

        .last-policy {
            flex: 1;
            padding: 20px 40px;
        }

        h2 {
            text-align: center;
            margin: 30px 0;
            color: #1560bd;
        }

        .last-policy p {
            font-size: 20px;
            line-height: 1.8;
            margin-bottom: 8px;
        }

        .last {
            background-color: #1560BD;
            color: white;
            text-align: center;
            padding: 15px 0;
            width: 100%;
        }

        @media(max-width:600px) {
            .last-policy {
                padding: 20px;
            }

            .last-policy p {
                font-size: 15px;
            }
        }
    </style>
</head>

<body>
    <div class="main">
        <nav>
            <a href="index.php" id="home">
                Home
            </a>
        </nav>
    </div>
    <div class="last-policy">
        <?php
        require_once "db.php";
        $result = $conn->query("SELECT * FROM policy ORDER BY id ASC");
        $i = 1;
        while ($row = $result->fetch_assoc()) {
            echo "<p>";
            echo $i++ . ". ";
            echo htmlspecialchars($row['policy_text']);
            echo "</p>";
        }
        ?>
    </div>
    <footer class="last">
   <p>&copy;2026 Online Bus Ticket Booking System | All rights reserved.</p>
    </footer>
</body>

</html>