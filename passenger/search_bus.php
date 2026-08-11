<?php
require_once "../db.php";
$from = trim($_GET['from'] ?? '');
$to = trim($_GET['to'] ?? '');
$date = $_GET['date'] ?? '';
$buses = [];
if ($from && $to && $date) {
    $sql = "SELECT b.*, s.schedule_id, s.from_city, s.to_city, s.departure_date, s.departure_time, s.ticket_price, s.available_seats
            FROM bus b
            INNER JOIN schedule s ON b.bus_id = s.bus_id
            WHERE b.status = 'approved'
            AND s.status = 'active'
            AND s.from_city = ?
            AND s.to_city = ?
            AND s.departure_date = ?
            ORDER BY s.departure_time";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $from, $to, $date);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $buses[] = $row;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Bus</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box
        }

        body {
            font-family: Arial, sans-serif;
            background: #f5f6fa
        }

        .container {
            width: 90%;
            max-width: 1100px;
            margin: 30px auto
        }

        .search-info {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px #ddd
        }

        .search-info h2 {
            margin-bottom: 15px
        }

        .route {
            display: flex;
            gap: 30px;
            flex-wrap: wrap
        }

        .bus-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 15px;
            box-shadow: 0 2px 8px #ddd
        }

        .bus-card h3 {
            margin-bottom: 15px
        }

        .bus-details {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 20px
        }

        .bus-details div {
            padding: 10px;
            background: #f5f6fa;
            border-radius: 6px
        }

        .book-btn {
            display: inline-block;
            background: #007bff;
            color: white;
            text-decoration: none;
            padding: 10px 18px;
            border-radius: 5px
        }

        .no-bus {
            background: white;
            text-align: center;
            padding: 30px;
            border-radius: 10px
        }

        @media(max-width:700px) {
            .bus-details {
                grid-template-columns: 1fr 1fr
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="search-info">
            <h2>Available Buses</h2>
            <div class="route">
                <strong>From: <?= htmlspecialchars($from) ?></strong>
                <strong>To: <?= htmlspecialchars($to) ?></strong>
                <strong>Date: <?= htmlspecialchars($date) ?></strong>
            </div>
        </div>
        <?php if ($buses): ?>
            <?php foreach ($buses as $bus): ?>
                <div class="bus-card">
                    <h3><?= htmlspecialchars($bus['bus_name']) ?></h3>
                    <div class="bus-details">
                        <div>
                            <strong>Bus Number</strong><br>
                            <?= htmlspecialchars($bus['bus_number']) ?>
                        </div>
                        <div>
                            <strong>Bus Type</strong><br>
                            <?= htmlspecialchars($bus['bus_type']) ?>
                        </div>
                        <div>
                            <strong>Departure</strong><br>
                            <?= date("h:i A", strtotime($bus['departure_time'])) ?>
                        </div>
                        <div>
                            <strong>NPR</strong><br>
                            <p><b>Rs. <?= htmlspecialchars($bus['ticket_price']) ?></b> &nbsp;per seat </p>
                        </div>
                        <div>
                            <strong>Available Seats</strong><br>
                            <?= htmlspecialchars($bus['available_seats']) ?>
                        </div>
                    </div>
                    <a class="book-btn" href="seat_selection.php?schedule_id=<?= $bus['schedule_id'] ?>&bus_id=<?= $bus['bus_id'] ?>">
                        Select Seats
                    </a>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="no-bus">
                <h3>No Bus Available</h3>
                <p>No bus found for this route and date.</p>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>