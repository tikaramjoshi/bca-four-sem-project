<?php

session_start();

require_once "../db.php";

/* ---------------------------------------
   DRIVER LOGIN CHECK
--------------------------------------- */

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'driver') {

    header("Location: ../login.php");
    exit;
}

$driver_id = (int) $_SESSION['user_id'];


/* ---------------------------------------
   GET GROUP ID
--------------------------------------- */

$group_id = trim($_GET['group_id'] ?? '');

if ($group_id === '') {

    die("Invalid ticket. Booking Group ID is missing.");
}


/* ---------------------------------------
   GET BOOKINGS
--------------------------------------- */

$sql = "SELECT 
            booking_id,
            booking_group_id,
            user_id,
            bus_name,
            bus_number,
            route,
            travel_date,
            seat_number,
            amount,
            status,
            ticket_status,
            scanned_at,
            scanned_by,
            created_at
        FROM bookings
        WHERE booking_group_id = ?
        ORDER BY booking_id ASC";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    die("Database error: " . mysqli_error($conn));
}

mysqli_stmt_bind_param($stmt, "s", $group_id);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) == 0) {

    mysqli_stmt_close($stmt);

    die("Ticket not found.");
}


/* ---------------------------------------
   STORE BOOKINGS
--------------------------------------- */

$bookings = [];

while ($row = mysqli_fetch_assoc($result)) {
    $bookings[] = $row;
}

mysqli_stmt_close($stmt);


/* ---------------------------------------
   CHECK VALID BOOKING
--------------------------------------- */

$valid_booking = false;
$all_cancelled = true;

foreach ($bookings as $booking) {

    $status = strtolower(trim($booking['status'] ?? ''));

    if ($status === 'confirmed' || $status === 'paid') {

        $valid_booking = true;
        $all_cancelled = false;
    }
}


/* ---------------------------------------
   CANCELLED CHECK
--------------------------------------- */

if (!$valid_booking) {

    $all_cancelled = true;

    foreach ($bookings as $booking) {

        $status = strtolower(trim($booking['status'] ?? ''));

        if ($status !== 'cancelled') {
            $all_cancelled = false;
            break;
        }
    }

    if ($all_cancelled) {
?>
        <!DOCTYPE html>
        <html>

        <head>
            <title>Ticket Cancelled</title>

            <style>
                body {
                    font-family: Arial;
                    background: #f4f6f9;
                    text-align: center;
                    padding-top: 80px;
                }

                .box {
                    max-width: 500px;
                    margin: auto;
                    background: white;
                    padding: 35px;
                    border-radius: 12px;
                    box-shadow: 0 4px 15px rgba(0, 0, 0, .1);
                }

                h2 {
                    color: #dc2626;
                }

                a {
                    display: inline-block;
                    margin-top: 20px;
                    padding: 12px 20px;
                    background: #4413e5;
                    color: white;
                    text-decoration: none;
                    border-radius: 6px;
                }
            </style>
        </head>

        <body>

            <div class="box">

                <h2>Ticket Cancelled</h2>

                <p>
                    This passenger ticket has been cancelled.
                </p>

                <a href="scan_ticket.php">
                    Scan Another Ticket
                </a>

            </div>

        </body>

        </html>
    <?php

        exit;
    }


    /* ---------------------------------------
       PENDING / INVALID
    --------------------------------------- */

    ?>
    <!DOCTYPE html>
    <html>

    <head>

        <title>Ticket Not Valid</title>

        <style>
            body {
                font-family: Arial;
                background: #f4f6f9;
                text-align: center;
                padding-top: 80px;
            }

            .box {
                max-width: 500px;
                margin: auto;
                background: white;
                padding: 35px;
                border-radius: 12px;
                box-shadow: 0 4px 15px rgba(0, 0, 0, .1);
            }

            h2 {
                color: #d97706;
            }

            a {
                display: inline-block;
                margin-top: 20px;
                padding: 12px 20px;
                background: #4413e5;
                color: white;
                text-decoration: none;
                border-radius: 6px;
            }
        </style>

    </head>

    <body>

        <div class="box">

            <h2>Ticket Not Valid</h2>

            <p>
                This ticket is not confirmed or paid.
            </p>

            <a href="scan_ticket.php">
                Scan Another Ticket
            </a>

        </div>

    </body>

    </html>

<?php

    exit;
}


/* ---------------------------------------
   CHECK ALREADY USED
--------------------------------------- */

$already_used = false;
$first_scanned_at = null;

foreach ($bookings as $booking) {

    if (($booking['ticket_status'] ?? '') === 'used') {

        $already_used = true;

        if (!empty($booking['scanned_at'])) {

            $time = $booking['scanned_at'];

            if (
                $first_scanned_at === null ||
                strtotime($time) < strtotime($first_scanned_at)
            ) {
                $first_scanned_at = $time;
            }
        }
    }
}


/* ---------------------------------------
   ALREADY USED
--------------------------------------- */

if ($already_used) {

?>
    <!DOCTYPE html>
    <html>

    <head>

        <title>Already Used</title>

        <style>
            body {
                font-family: Arial;
                background: #f4f6f9;
                text-align: center;
                padding-top: 60px;
            }

            .box {
                max-width: 550px;
                margin: auto;
                background: white;
                padding: 35px;
                border-radius: 12px;
                box-shadow: 0 4px 15px rgba(0, 0, 0, .1);
            }

            h2 {
                color: #dc2626;
            }

            .used {
                background: #fee2e2;
                padding: 15px;
                border-radius: 8px;
                margin-top: 20px;
            }

            .time {
                font-size: 18px;
                font-weight: bold;
                margin-top: 10px;
            }

            a {
                display: inline-block;
                margin-top: 25px;
                padding: 12px 20px;
                background: #4413e5;
                color: white;
                text-decoration: none;
                border-radius: 6px;
            }
        </style>

    </head>

    <body>

        <div class="box">

            <h2>Already Used</h2>

            <div class="used">

                <p>
                    This ticket has already been scanned.
                </p>

                <?php if ($first_scanned_at): ?>

                    <div class="time">
                        First Scanned:
                        <?= htmlspecialchars(date("d M Y, h:i A", strtotime($first_scanned_at))) ?>
                    </div>

                <?php else: ?>

                    <div class="time">
                        First scan time not available
                    </div>

                <?php endif; ?>

            </div>

            <a href="scan_ticket.php">
                Scan Another Ticket
            </a>

        </div>

    </body>

    </html>

<?php

    exit;
}


/* ---------------------------------------
   MARK TICKET AS USED
--------------------------------------- */

$update_sql = "UPDATE bookings
               SET ticket_status = 'used',
                   scanned_at = NOW(),
                   scanned_by = ?
               WHERE booking_group_id = ?
               AND ticket_status = 'active'
               AND (status = 'confirmed' OR status = 'paid')";

$update_stmt = mysqli_prepare($conn, $update_sql);

if (!$update_stmt) {
    die("Database error: " . mysqli_error($conn));
}

mysqli_stmt_bind_param(
    $update_stmt,
    "is",
    $driver_id,
    $group_id
);

if (!mysqli_stmt_execute($update_stmt)) {

    mysqli_stmt_close($update_stmt);

    die("Unable to verify ticket: " . mysqli_error($conn));
}

mysqli_stmt_close($update_stmt);


/* ---------------------------------------
   GET FIRST SCAN TIME
--------------------------------------- */

$time_sql = "SELECT MIN(scanned_at) AS first_scan
             FROM bookings
             WHERE booking_group_id = ?
             AND ticket_status = 'used'
             AND scanned_at IS NOT NULL";

$time_stmt = mysqli_prepare($conn, $time_sql);

if (!$time_stmt) {
    die("Database error: " . mysqli_error($conn));
}

mysqli_stmt_bind_param(
    $time_stmt,
    "s",
    $group_id
);

mysqli_stmt_execute($time_stmt);

$time_result = mysqli_stmt_get_result($time_stmt);

$time_row = mysqli_fetch_assoc($time_result);

$first_scan = $time_row['first_scan'] ?? null;

mysqli_stmt_close($time_stmt);


/* ---------------------------------------
   DISPLAY SUCCESS
--------------------------------------- */

?>

<!DOCTYPE html>
<html>

<head>

    <title>Ticket Verified</title>

    <style>
        body {
            font-family: Arial;
            background: #f4f6f9;
            text-align: center;
            padding-top: 60px;
        }

        .box {
            max-width: 600px;
            margin: auto;
            background: white;
            padding: 35px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, .1);
        }

        h2 {
            color: #16a34a;
        }

        .success {
            background: #dcfce7;
            padding: 18px;
            border-radius: 8px;
            margin: 20px 0;
        }

        .info {
            text-align: left;
            margin-top: 20px;
        }

        .info p {
            padding: 8px;
            border-bottom: 1px solid #eee;
        }

        .time {
            font-size: 20px;
            font-weight: bold;
            color: #166534;
        }

        a {
            display: inline-block;
            margin-top: 25px;
            padding: 12px 20px;
            background: #4413e5;
            color: white;
            text-decoration: none;
            border-radius: 6px;
        }
    </style>

</head>

<body>

    <div class="box">

        <h2>✓ Ticket Verified</h2>

        <div class="success">

            <strong>Ticket successfully verified.</strong>

            <p>
                This ticket is now marked as USED.
            </p>

            <?php if ($first_scan): ?>

                <div class="time">
                    Scanned:
                    <?= htmlspecialchars(date("d M Y, h:i A", strtotime($first_scan))) ?>
                </div>

            <?php endif; ?>

        </div>


        <div class="info">

            <p>
                <strong>Booking Group:</strong>
                <?= htmlspecialchars($group_id) ?>
            </p>

            <p>
                <strong>Bus:</strong>
                <?= htmlspecialchars($bookings[0]['bus_name'] ?? '') ?>
            </p>

            <p>
                <strong>Bus Number:</strong>
                <?= htmlspecialchars($bookings[0]['bus_number'] ?? '') ?>
            </p>

            <p>
                <strong>Route:</strong>
                <?= htmlspecialchars($bookings[0]['route'] ?? '') ?>
            </p>

            <p>
                <strong>Travel Date:</strong>
                <?= htmlspecialchars($bookings[0]['travel_date'] ?? '') ?>
            </p>

        </div>


        <a href="scan_ticket.php">
            Scan Another Ticket
        </a>

    </div>

</body>

</html>