<?php
require_once __DIR__ . 'db.php';
if (!isset($conn) || !$conn instanceof mysqli) exit('Database connection is not available.');
$from = trim($_GET['from_city'] ?? '');
$to = trim($_GET['to_city'] ?? '');
$date = trim($_GET['travel_date'] ?? '');
$searched = isset($_GET['search']);
$cities = $conn->query("SELECT city_name FROM (SELECT from_city AS city_name FROM schedule UNION SELECT to_city AS city_name FROM schedule) AS cities WHERE city_name <> '' ORDER BY city_name");
$schedules = null;
if ($searched) {
    $sql = "SELECT s.schedule_id, s.bus_id, s.from_city, s.to_city, s.departure_date, s.departure_time, s.ticket_price, s.available_seats, b.bus_name, b.bus_number, b.bus_type FROM schedule s JOIN bus b ON s.bus_id = b.bus_id WHERE s.status = 'active'";
    $params = [];
    $types = '';
    if ($from !== '') {
        $sql .= ' AND s.from_city = ?';
        $params[] = $from;
        $types .= 's';
    }
    if ($to !== '') {
        $sql .= ' AND s.to_city = ?';
        $params[] = $to;
        $types .= 's';
    }
    if ($date !== '') {
        $sql .= ' AND s.departure_date = ?';
        $params[] = $date;
        $types .= 's';
    }
    $sql .= ' ORDER BY s.departure_date, s.departure_time';
    $stmt = $conn->prepare($sql);
    if ($params) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $schedules = $stmt->get_result();
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Search Bus</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f7f6;
            margin: 0;
            color: #263238;
        }

        .header {
            background: #1560bd;
            color: #fff;
            padding: 18px
        }

        .header a {
            color: #fff;
            text-decoration: none;
        }

        .wrap {
            max-width: 1100px;
            margin: 35px auto;
            padding: 0 16px;
        }

        .search {
            background: #fff;
            padding: 22px;
            border-radius: 10px;
            box-shadow: 0 2px 12px #00000014;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
        }

        .search input,
        .search button {
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 15px;
        }

        .search button {
            background: #1560bd;
            color: #fff;
            border: 0;
            cursor: pointer;
        }

        .results {
            margin-top: 28px;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 18px;
        }

        .card {
            background: #fff;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 12px #00000014;
        }

        .card h3 {
            margin: 0 0 12px;
            color: #1560bd;
        }

        .card p {
            margin: 8px 0;
        }

        .book {
            display: inline-block;
            background: #0f766e;
            color: #fff;
            text-decoration: none;
            padding: 10px 14px;
            border-radius: 6px;
            margin-top: 10px;
        }

        .empty {
            background: #fff;
            padding: 28px;
            text-align: center;
            border-radius: 10px;
        }

        @media(max-width:700px) {
            .search {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <header class="header"><strong>Online Bus Ticket System</strong></header>
    <main class="wrap">
        <h2>Search Bus</h2>
        <form class="search" method="get"><input name="from_city" list="cities" placeholder="From City" value="<?= htmlspecialchars($from) ?>" required><input name="to_city" list="cities" placeholder="To City" value="<?= htmlspecialchars($to) ?>" required><input type="date" name="travel_date" min="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars($date) ?>" required><button name="search" value="1">Search Bus</button><datalist id="cities"><?php while ($city = $cities->fetch_assoc()): ?><option value="<?= htmlspecialchars($city['city_name']) ?>"><?php endwhile; ?></datalist></form>
        <?php if ($searched): ?><section class="results">
                <h2>Available Buses</h2><?php if ($schedules->num_rows): ?><div class="grid"><?php while ($schedule = $schedules->fetch_assoc()): ?><article class="card">
                                <h3><?= htmlspecialchars($schedule['bus_name']) ?></h3>
                                <p><strong>Bus:</strong> <?= htmlspecialchars($schedule['bus_number']) ?></p>
                                <p><strong>Route:</strong> <?= htmlspecialchars($schedule['from_city'] . ' to ' . $schedule['to_city']) ?></p>
                                <p><strong>Date:</strong> <?= htmlspecialchars(date('d M Y', strtotime($schedule['departure_date']))) ?></p>
                                <p><strong>Time:</strong> <?= htmlspecialchars(date('h:i A', strtotime($schedule['departure_time']))) ?></p>
                                <p><strong>Price:</strong> Rs. <?= htmlspecialchars(number_format((float)$schedule['ticket_price'], 2)) ?></p>
                                <p><strong>Available seats:</strong> <?= (int)$schedule['available_seats'] ?></p><a class="book" href="seat_selection.php?schedule_id=<?= (int)$schedule['schedule_id'] ?>&bus_id=<?= (int)$schedule['bus_id'] ?>">Select Seat</a>
                            </article><?php endwhile; ?></div><?php else: ?><p class="empty">No active bus schedule was found for this route and date.</p><?php endif; ?>
            </section><?php endif; ?>
    </main>
</body>

</html>