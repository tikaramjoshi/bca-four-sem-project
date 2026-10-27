<?php
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'owner') {
    header("Location: ../login.php");
    exit();
}

require_once "../db.php";

$owner_id = (int)$_SESSION['user_id'];
$schedule_id = (int)($_GET['id'] ?? $_POST['schedule_id'] ?? 0);

if ($schedule_id <= 0) {
    header("Location: schedule.php");
    exit();
}

$stmt = $conn->prepare("
    SELECT s.*
    FROM schedules s
    INNER JOIN bus b ON s.bus_id = b.bus_id
    WHERE s.schedule_id = ? AND b.owner_id = ?
    LIMIT 1
");
$stmt->bind_param("ii", $schedule_id, $owner_id);
$stmt->execute();
$schedule = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$schedule) {
    $_SESSION['error'] = "Schedule not found or access denied.";
    header("Location: schedule.php");
    exit();
}

$message = "";

if (isset($_POST['update_schedule'])) {
    $bus_id = (int)($_POST['bus_id'] ?? 0);
    $from_city = trim($_POST['from_city'] ?? '');
    $to_city = trim($_POST['to_city'] ?? '');
    $departure_date = trim($_POST['departure_date'] ?? '');
    $departure_time = trim($_POST['departure_time'] ?? '');
    $ticket_price = (float)($_POST['ticket_price'] ?? 0);

    if (
        $bus_id <= 0 || $from_city === '' || $to_city === '' ||
        $departure_date === '' || $departure_time === '' || $ticket_price < 500
    ) {
        $message = "Please fill all fields correctly.";
    } elseif (strcasecmp($from_city, $to_city) === 0) {
        $message = "From City and To City cannot be the same.";
    } else {
        $busCheck = $conn->prepare("
            SELECT bus_id FROM bus
            WHERE bus_id = ? AND owner_id = ? AND status = 'approved'
            LIMIT 1
        ");
        $busCheck->bind_param("ii", $bus_id, $owner_id);
        $busCheck->execute();
        $validBus = $busCheck->get_result()->fetch_assoc();
        $busCheck->close();

        if (!$validBus) {
            $message = "Please select your own approved bus.";
        } else {
            $check = $conn->prepare("
                SELECT schedule_id FROM schedules
                WHERE bus_id = ? AND departure_date = ?
                AND departure_time = ? AND schedule_id != ?
                LIMIT 1
            ");
            $check->bind_param("issi", $bus_id, $departure_date, $departure_time, $schedule_id);
            $check->execute();
            $exists = $check->get_result()->num_rows > 0;
            $check->close();

            if ($exists) {
                $message = "A schedule already exists for this bus at that date and time.";
            } else {
                $update = $conn->prepare("
                    UPDATE schedules
                    SET bus_id = ?, from_city = ?, to_city = ?,
                        departure_date = ?, departure_time = ?, ticket_price = ?
                    WHERE schedule_id = ?
                ");
                $update->bind_param(
                    "issssdi",
                    $bus_id,
                    $from_city,
                    $to_city,
                    $departure_date,
                    $departure_time,
                    $ticket_price,
                    $schedule_id
                );

                if ($update->execute()) {
                    $update->close();
                    $_SESSION['success'] = "Schedule updated successfully.";
                    header("Location: schedule.php");
                    exit();
                }

                $message = "Failed to update schedule.";
                $update->close();
            }
        }
    }
}

$buses = $conn->prepare("
    SELECT bus_id, bus_number, bus_name, seats
    FROM bus
    WHERE owner_id = ? AND status = 'approved'
    ORDER BY bus_id DESC
");
$buses->bind_param("i", $owner_id);
$buses->execute();
$busResult = $buses->get_result();

$routes = [];
$result = $conn->query("SELECT city_name FROM routes ORDER BY city_name ASC");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $routes[] = $row['city_name'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Schedule</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="schedule.css">
    <style>
        .edit-box {
            max-width: 850px;
            margin: 25px auto;
            padding: 25px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 3px 12px #0002
        }

        .edit-form {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px
        }

        .edit-form label {
            display: block;
            margin-bottom: 7px;
            font-weight: 600
        }

        .edit-form input,
        .edit-form select {
            width: 100%;
            padding: 11px;
            border: 1px solid #ccc;
            border-radius: 5px;
            box-sizing: border-box
        }

        .edit-actions {
            grid-column: 1/-1;
            display: flex;
            gap: 10px
        }

        .save-btn,
        .back-btn {
            padding: 11px 18px;
            border: 0;
            border-radius: 5px;
            color: white;
            text-decoration: none;
            cursor: pointer
        }

        .save-btn {
            background: #1560bd
        }

        .back-btn {
            background: #555
        }

        .error-msg {
            margin: 15px auto;
            padding: 12px;
            background: #ffe5e5;
            color: #a00000;
            border-radius: 5px
        }

        @media(max-width:600px) {
            .edit-form {
                grid-template-columns: 1fr
            }

            .edit-actions {
                grid-column: auto
            }
        }
    </style>
</head>

<body>

    <?php require_once "owner_header.php"; ?>

    <div class="container">
        <div class="title">
            <h1>Edit Schedule</h1>
            <p>Update your bus schedule details.</p>
        </div>

        <?php if ($message !== ''): ?>
            <div class="error-msg"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <div class="edit-box">
            <form method="POST" class="edit-form">
                <input type="hidden" name="schedule_id" value="<?= (int)$schedule['schedule_id'] ?>">

                <div>
                    <label>Bus</label>
                    <select name="bus_id" required>
                        <?php while ($bus = $busResult->fetch_assoc()): ?>
                            <option value="<?= (int)$bus['bus_id'] ?>"
                                <?= (int)$bus['bus_id'] === (int)$schedule['bus_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($bus['bus_number'] . ' - ' . $bus['bus_name']) ?>
                                (<?= (int)$bus['seats'] ?> Seats)
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div>
                    <label>From City</label>
                    <select name="from_city" required>
                        <?php foreach ($routes as $city): ?>
                            <option value="<?= htmlspecialchars($city) ?>"
                                <?= strcasecmp($city, $schedule['from_city']) === 0 ? 'selected' : '' ?>>
                                <?= htmlspecialchars(ucwords(strtolower($city))) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label>To City</label>
                    <select name="to_city" required>
                        <?php foreach ($routes as $city): ?>
                            <option value="<?= htmlspecialchars($city) ?>"
                                <?= strcasecmp($city, $schedule['to_city']) === 0 ? 'selected' : '' ?>>
                                <?= htmlspecialchars(ucwords(strtolower($city))) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label>Departure Date</label>
                    <input type="date" name="departure_date"
                        value="<?= htmlspecialchars($schedule['departure_date']) ?>" required>
                </div>

                <div>
                    <label>Departure Time</label>
                    <input type="time" name="departure_time"
                        value="<?= htmlspecialchars(substr($schedule['departure_time'], 0, 5)) ?>" required>
                </div>

                <div>
                    <label>Ticket Price (Rs.)</label>
                    <input type="number" name="ticket_price" min="500" step="100"
                        value="<?= htmlspecialchars((string)$schedule['ticket_price']) ?>" required>
                </div>

                <div class="edit-actions">
                    <button type="submit" name="update_schedule" class="save-btn">
                        <i class="fa fa-save"></i> Update Schedule
                    </button>
                    <a href="schedule.php" class="back-btn">
                        <i class="fa fa-arrow-left"></i> Back
                    </a>
                </div>
            </form>
        </div>
    </div>

    <?php require_once "owner_footer.php"; ?>

</body>

</html>