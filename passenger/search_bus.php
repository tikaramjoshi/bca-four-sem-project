<?php
session_start();
require_once "../db.php";
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'passenger') {
    header("Location: ../login.php");
    exit();
}
$from = trim($_GET['from'] ?? '');
$to = trim($_GET['to'] ?? '');
$date = $_GET['date'] ?? '';
$buses = [];
if ($from && $to && $date) {
    $sql = "
        SELECT
            b.bus_id,
            b.bus_number,
            b.bus_name,
            b.bus_type,
            b.seats,
            b.bus_image,
            s.schedule_id,
            s.from_city,
            s.to_city,
            s.departure_date,
            s.departure_time,
            s.ticket_price,
            s.available_seats,
            s.status
        FROM schedules s
        INNER JOIN bus b ON b.bus_id = s.bus_id
        WHERE b.status = 'approved'
        AND s.status = 'active'
        AND LOWER(TRIM(s.from_city)) = LOWER(TRIM(?))
        AND LOWER(TRIM(s.to_city)) = LOWER(TRIM(?))
        AND s.departure_date = ?
        ORDER BY s.departure_time ASC
    ";
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
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f5f6fa;
        }

        .container {
            width: 90%;
            max-width: 1100px;
            margin: 30px auto;
        }

        .top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .back-btn {
            background: #555;
            color: white;
            text-decoration: none;
            padding: 10px 16px;
            border-radius: 5px;
        }

        .search-info {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px #ddd;
        }

        .search-info h2 {
            margin-bottom: 15px;
            color: #1560bd;
        }

        .route {
            display: flex;
            gap: 30px;
            flex-wrap: wrap;
        }

        .bus-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 15px;
            box-shadow: 0 2px 8px #ddd;
        }

        .bus-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .bus-header h3 {
            color: #1560bd;
        }

        .bus-image {
            width: 120px;
            height: 75px;
            object-fit: cover;
            border-radius: 6px;
        }

        .bus-details {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }

        .bus-details div {
            padding: 10px;
            background: #f5f6fa;
            border-radius: 6px;
        }

        .bus-details strong {
            display: inline-block;
            margin-bottom: 5px;
        }

        .available {
            color: #198754;
            font-weight: bold;
        }

        .full {
            color: #dc3545;
            font-weight: bold;
        }

        .book-btn {
            display: inline-block;
            background: #1560bd;
            color: white;
            text-decoration: none;
            padding: 10px 18px;
            border-radius: 5px;
        }

        .book-btn:hover {
            background: #0d4d9b;
        }

        .disabled-btn {
            display: inline-block;
            background: #999;
            color: white;
            padding: 10px 18px;
            border-radius: 5px;
        }

        .no-bus {
            background: white;
            text-align: center;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 2px 8px #ddd;
        }

        .no-bus h3 {
            margin-bottom: 10px;
        }

        @media(max-width:500px) {
            .container {
                width: 95%;
            }

            .top {
                gap: 10px;
                flex-direction: column;
                align-items: flex-start;
            }

            .bus-header {
                align-items: flex-start;
                gap: 10px;
                flex-direction: column;
            }

            .bus-details {
                grid-template-columns: 1fr 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="top">
            <h1>Search Bus</h1>
            <a href="dashboard.php" class="back-btn">Home</a>
        </div>
        <div class="search-info">
            <h2>Available Buses</h2>
            <div class="route">
                <strong>
                    From:
                    <?= htmlspecialchars(ucfirst(strtolower($from))) ?>
                </strong>
                <strong>
                    To:
                    <?= htmlspecialchars(ucfirst(strtolower($to))) ?>
                </strong>
                <strong>
                    Date:
                    <?= htmlspecialchars($date) ?>
                </strong>
            </div>
        </div>
        <?php if (!empty($buses)): ?>
            <?php foreach ($buses as $bus): ?>
                <div class="bus-card">
                    <div class="bus-header">
                        <div>
                            <h3><?= htmlspecialchars($bus['bus_name']) ?></h3>
                            <p>
                                <?= htmlspecialchars($bus['bus_number']) ?>
                            </p>
                        </div>
                        <?php if (!empty($bus['bus_image'])): ?>
                            <img
                                src="../uploads/bus/<?= htmlspecialchars($bus['bus_image']) ?>"
                                class="bus-image"
                                alt="Bus"
                                onerror="this.onerror=null;this.src='../images/bus.png';">
                        <?php else: ?>
                            <img
                                src="../images/bus.png"
                                class="bus-image"
                                alt="Bus">
                        <?php endif; ?>
                    </div>
                    <div class="bus-details">
                        <div>
                            <strong>Bus Type</strong><br>
                            <?= htmlspecialchars($bus['bus_type']) ?>
                        </div>
                        <div>
                            <strong>Departure</strong><br>
                            <?= date("h:i A", strtotime($bus['departure_time'])) ?>
                        </div>
                        <div>
                            <strong>Ticket Price</strong><br>
                            Rs. <?= number_format((float)$bus['ticket_price'], 2) ?>
                        </div>
                        <div>
                            <strong>Total Seats</strong><br>
                            <?= htmlspecialchars($bus['seats']) ?>
                        </div>
                        <div>
                            <strong>Available Seats</strong><br>
                            <?php if ((int)$bus['available_seats'] > 0): ?>
                                <span class="available">
                                    <?= htmlspecialchars($bus['available_seats']) ?>
                                </span>
                            <?php else: ?>
                                <span class="full">
                                    Full
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if ((int)$bus['available_seats'] > 0): ?>
                        <a
                            class="book-btn"
                            href="seat_selection.php?schedule_id=<?= (int)$bus['schedule_id'] ?>&bus_id=<?= (int)$bus['bus_id'] ?>">
                            Select Seats
                        </a>
                    <?php else: ?>
                        <span class="disabled-btn">
                            No Available Seats
                        </span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="no-bus">
                <h3>No Bus Available</h3>
                <p> No bus found for <?= htmlspecialchars($from) ?> to <?= htmlspecialchars($to) ?> on <?= htmlspecialchars($date) ?>.
                </p>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>