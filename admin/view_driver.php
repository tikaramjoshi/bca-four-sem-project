<?php
session_start();
require_once "../db.php";

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'admin'
) {
    header("Location: ../login.php");
    exit;
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: drivers.php");
    exit;
}

$driver_id = (int) $_GET['id'];

$stmt = $conn->prepare("
    SELECT
        u.user_id,
        u.name,
        u.email,
        u.phone,
        u.profile_image,
        u.verification_status,
        u.created_at,

        b.bus_id,
        b.bus_number,
        b.bus_name,
        b.bus_type,
        b.seats,
        b.status AS bus_status

    FROM users u

    LEFT JOIN bus_driver bd
        ON u.user_id = bd.driver_id

    LEFT JOIN bus b
        ON bd.bus_id = b.bus_id

    WHERE u.user_id = ?
      AND u.role = 'driver'

    LIMIT 1
");

$stmt->bind_param("i", $driver_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Driver Not Found");
}

$driver = $result->fetch_assoc();

$profile_image = !empty($driver['profile_image'])
    ? $driver['profile_image']
    : 'default.png';

$today = date("Y-m-d");

$today_trips = [];
$total_trips = 0;
$total_bookings = 0;
$today_bookings = 0;

if (!empty($driver['bus_id'])) {

    $stmt = $conn->prepare("
        SELECT
            schedule_id,
            from_city,
            to_city,
            travel_date,
            departure_time,
            arrival_time
        FROM schedules
        WHERE bus_id = ?
        ORDER BY travel_date DESC, departure_time DESC
    ");

    $stmt->bind_param(
        "i",
        $driver['bus_id']
    );

    $stmt->execute();

    $schedule_result = $stmt->get_result();

    $total_trips = $schedule_result->num_rows;

    while ($row = $schedule_result->fetch_assoc()) {

        if ($row['travel_date'] === $today) {
            $today_trips[] = $row;
        }
    }

    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM bookings bk
        INNER JOIN schedules s
            ON bk.schedule_id = s.schedule_id
        WHERE s.bus_id = ?
    ");

    $stmt->bind_param(
        "i",
        $driver['bus_id']
    );

    $stmt->execute();

    $booking_result = $stmt->get_result();
    $booking_data = $booking_result->fetch_assoc();

    $total_bookings = (int) ($booking_data['total'] ?? 0);

    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM bookings bk
        INNER JOIN schedules s
            ON bk.schedule_id = s.schedule_id
        WHERE s.bus_id = ?
        AND s.travel_date = ?
    ");

    $stmt->bind_param(
        "is",
        $driver['bus_id'],
        $today
    );

    $stmt->execute();

    $today_booking_result = $stmt->get_result();
    $today_booking_data = $today_booking_result->fetch_assoc();

    $today_bookings = (int) ($today_booking_data['total'] ?? 0);
}

$driver_status = "Available";

if (!empty($today_trips)) {

    $current_time = date("H:i:s");

    foreach ($today_trips as $trip) {

        if (
            $current_time >= $trip['departure_time'] &&
            $current_time <= $trip['arrival_time']
        ) {
            $driver_status = "On Trip";
            break;
        }
    }
}

if (
    !empty($driver['verification_status']) &&
    $driver['verification_status'] === 'verified'
) {
    $verification_class = "verified";
    $verification_text = "Verified ✓";
} else {
    $verification_class = "unverified";
    $verification_text = "Unverified";
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>
        Driver Details - <?= htmlspecialchars($driver['name']) ?>
    </title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, Helvetica, sans-serif;
        }

        body {
            background: #f4f6f9;
            color: #222;
        }

        .page {
            width: 94%;
            max-width: 1200px;
            margin: 30px auto;
        }

        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            gap: 15px;
        }

        .top-bar h1 {
            font-size: 27px;
            color: #222;
        }

        .top-bar p {
            color: #777;
            font-size: 14px;
            margin-top: 5px;
        }

        .back-btn {
            text-decoration: none;
            background: #1560bd;
            color: white;
            padding: 11px 18px;
            border-radius: 7px;
            font-size: 14px;
        }

        .back-btn:hover {
            background: #0d4f9c;
        }

        .profile-card {
            background: white;
            border-radius: 14px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, .07);
            display: flex;
            align-items: center;
            gap: 25px;
            margin-bottom: 25px;
        }

        .profile-card img {
            width: 125px;
            height: 125px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #1560bd;
        }

        .profile-info {
            flex: 1;
        }

        .profile-info h2 {
            font-size: 25px;
            margin-bottom: 7px;
        }

        .profile-info .email {
            color: #777;
            margin-bottom: 12px;
        }

        .status-row {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .badge {
            display: inline-block;
            padding: 7px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .verified {
            background: #d1e7dd;
            color: #0f5132;
        }

        .unverified {
            background: #f8d7da;
            color: #842029;
        }

        .available {
            background: #d1e7dd;
            color: #0f5132;
        }

        .on-trip {
            background: #cff4fc;
            color: #055160;
        }

        .action-area {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 15px;
        }

        .btn {
            border: none;
            text-decoration: none;
            padding: 9px 14px;
            border-radius: 6px;
            font-size: 13px;
            cursor: pointer;
            display: inline-block;
        }

        .btn-verify {
            background: #198754;
            color: white;
        }

        .btn-verify:hover {
            background: #146c43;
        }

        .btn-reject {
            background: #ffc107;
            color: #212529;
        }

        .btn-delete {
            background: #dc3545;
            color: white;
        }

        .btn-delete:hover {
            background: #b02a37;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 11px;
            box-shadow: 0 3px 12px rgba(0, 0, 0, .06);
        }

        .stat-title {
            color: #777;
            font-size: 13px;
            margin-bottom: 9px;
        }

        .stat-value {
            font-size: 27px;
            font-weight: bold;
            color: #1560bd;
        }

        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
        }

        .box {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 3px 12px rgba(0, 0, 0, .06);
            margin-bottom: 25px;
        }

        .box h2 {
            font-size: 19px;
            margin-bottom: 20px;
            color: #333;
        }

        .details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .detail {
            background: #f7f9fc;
            padding: 14px;
            border-radius: 8px;
        }

        .detail small {
            display: block;
            color: #777;
            font-size: 12px;
            margin-bottom: 6px;
        }

        .detail strong {
            font-size: 14px;
            color: #222;
        }

        .no-bus {
            background: #fff3cd;
            color: #856404;
            padding: 17px;
            border-radius: 8px;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
        }

        th {
            background: #f7f9fc;
            color: #555;
            padding: 13px;
            text-align: left;
            font-size: 13px;
        }

        td {
            padding: 13px;
            border-bottom: 1px solid #eee;
            font-size: 13px;
        }

        .route {
            color: #1560bd;
            font-weight: 600;
        }

        .empty {
            padding: 20px;
            text-align: center;
            color: #777;
            background: #f7f9fc;
            border-radius: 8px;
        }

        .danger-box {
            border-left: 4px solid #dc3545;
        }

        .danger-box p {
            color: #777;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 15px;
        }

        @media (max-width: 900px) {

            .stats {
                grid-template-columns: repeat(2, 1fr);
            }

            .grid {
                grid-template-columns: 1fr;
            }

        }

        @media (max-width: 650px) {

            .page {
                width: 94%;
                margin: 20px auto;
            }

            .top-bar {
                flex-direction: column;
                align-items: flex-start;
            }

            .profile-card {
                flex-direction: column;
                text-align: center;
            }

            .status-row {
                justify-content: center;
            }

            .action-area {
                justify-content: center;
            }

            .stats {
                grid-template-columns: 1fr;
            }

            .details {
                grid-template-columns: 1fr;
            }

        }
    </style>

</head>

<body>

    <div class="page">

        <div class="top-bar">

            <div>

                <h1>
                    Driver Details
                </h1>

                <p>
                    Complete information about the selected driver.
                </p>

            </div>

            <a
                href="drivers.php"
                class="back-btn">
                ← Back to Drivers
            </a>

        </div>

        <div class="profile-card">

            <img
                src="../uploads/profile/<?= htmlspecialchars($profile_image) ?>"
                alt="Driver Profile"
                onerror="this.onerror=null; this.src='../images/default.png';">

            <div class="profile-info">

                <h2>
                    <?= htmlspecialchars($driver['name']) ?>
                </h2>

                <div class="email">
                    <?= htmlspecialchars($driver['email']) ?>
                </div>

                <div class="status-row">

                    <span class="badge <?= $verification_class ?>">
                        <?= $verification_text ?>
                    </span>

                    <?php if ($driver_status === "On Trip"): ?>

                        <span class="badge on-trip">
                            ● On Trip
                        </span>

                    <?php else: ?>

                        <span class="badge available">
                            ● Available
                        </span>

                    <?php endif; ?>

                </div>

                <div class="action-area">

                    <?php if (
                        $driver['verification_status'] !== 'verified'
                    ): ?>

                        <a
                            href="drivers.php?action=verify&id=<?= $driver_id ?>"
                            class="btn btn-verify"
                            onclick="return confirm('Verify this driver?')">
                            ✓ Verify Driver
                        </a>

                    <?php else: ?>

                        <a
                            href="drivers.php?action=reject&id=<?= $driver_id ?>"
                            class="btn btn-reject"
                            onclick="return confirm('Reject this driver verification?')">
                            Reject Verification
                        </a>

                    <?php endif; ?>

                    <a
                        href="drivers.php?action=delete&id=<?= $driver_id ?>"
                        class="btn btn-delete"
                        onclick="return confirm('Are you sure you want to delete this driver?')">
                        Delete Driver
                    </a>

                </div>

            </div>

        </div>

        <div class="stats">

            <div class="stat-card">

                <div class="stat-title">
                    Driver ID
                </div>

                <div class="stat-value">
                    #<?= $driver['user_id'] ?>
                </div>

            </div>

            <div class="stat-card">

                <div class="stat-title">
                    Today's Trips
                </div>

                <div class="stat-value">
                    <?= count($today_trips) ?>
                </div>

            </div>

            <div class="stat-card">

                <div class="stat-title">
                    Today's Passengers
                </div>

                <div class="stat-value">
                    <?= $today_bookings ?>
                </div>

            </div>

            <div class="stat-card">

                <div class="stat-title">
                    Total Bookings
                </div>

                <div class="stat-value">
                    <?= $total_bookings ?>
                </div>

            </div>

        </div>

        <div class="grid">

            <div>

                <div class="box">

                    <h2>
                        Driver Information
                    </h2>

                    <div class="details">

                        <div class="detail">

                            <small>
                                Driver ID
                            </small>

                            <strong>
                                <?= htmlspecialchars($driver['user_id']) ?>
                            </strong>

                        </div>

                        <div class="detail">

                            <small>
                                Full Name
                            </small>

                            <strong>
                                <?= htmlspecialchars($driver['name']) ?>
                            </strong>

                        </div>

                        <div class="detail">

                            <small>
                                Email
                            </small>

                            <strong>
                                <?= htmlspecialchars($driver['email']) ?>
                            </strong>

                        </div>

                        <div class="detail">

                            <small>
                                Phone
                            </small>

                            <strong>
                                <?= htmlspecialchars($driver['phone']) ?>
                            </strong>

                        </div>

                        <div class="detail">

                            <small>
                                Verification
                            </small>

                            <strong>
                                <?= htmlspecialchars($verification_text) ?>
                            </strong>

                        </div>

                        <div class="detail">

                            <small>
                                Driver Status
                            </small>

                            <strong>
                                <?= htmlspecialchars($driver_status) ?>
                            </strong>

                        </div>

                        <div class="detail">

                            <small>
                                Registered Date
                            </small>

                            <strong>
                                <?= date(
                                    "d M Y",
                                    strtotime($driver['created_at'])
                                ) ?>
                            </strong>

                        </div>

                        <div class="detail">

                            <small>
                                Account Role
                            </small>

                            <strong>
                                Driver
                            </strong>

                        </div>

                    </div>

                </div>

                <div class="box">

                    <h2>
                        Today's Trips
                    </h2>

                    <?php if (!empty($today_trips)): ?>

                        <div class="table-wrapper">

                            <table>

                                <thead>

                                    <tr>
                                        <th>Route</th>
                                        <th>Departure</th>
                                        <th>Arrival</th>
                                        <th>Date</th>
                                    </tr>

                                </thead>

                                <tbody>

                                    <?php foreach ($today_trips as $trip): ?>

                                        <tr>

                                            <td class="route">

                                                <?= htmlspecialchars(
                                                    $trip['from_city']
                                                ) ?>

                                                →

                                                <?= htmlspecialchars(
                                                    $trip['to_city']
                                                ) ?>

                                            </td>

                                            <td>

                                                <?= date(
                                                    "h:i A",
                                                    strtotime(
                                                        $trip['departure_time']
                                                    )
                                                ) ?>

                                            </td>

                                            <td>

                                                <?= date(
                                                    "h:i A",
                                                    strtotime(
                                                        $trip['arrival_time']
                                                    )
                                                ) ?>

                                            </td>

                                            <td>

                                                <?= date(
                                                    "d M Y",
                                                    strtotime(
                                                        $trip['travel_date']
                                                    )
                                                ) ?>

                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                </tbody>

                            </table>

                        </div>

                    <?php else: ?>

                        <div class="empty">
                            No trips scheduled for today.
                        </div>

                    <?php endif; ?>

                </div>

            </div>

            <div>

                <div class="box">

                    <h2>
                        Assigned Bus
                    </h2>

                    <?php if (!empty($driver['bus_id'])): ?>

                        <div class="details">

                            <div class="detail">

                                <small>
                                    Bus Number
                                </small>

                                <strong>
                                    <?= htmlspecialchars(
                                        $driver['bus_number']
                                    ) ?>
                                </strong>

                            </div>

                            <div class="detail">

                                <small>
                                    Bus Name
                                </small>

                                <strong>
                                    <?= htmlspecialchars(
                                        $driver['bus_name']
                                    ) ?>
                                </strong>

                            </div>

                            <div class="detail">

                                <small>
                                    Bus Type
                                </small>

                                <strong>
                                    <?= htmlspecialchars(
                                        $driver['bus_type']
                                    ) ?>
                                </strong>

                            </div>

                            <div class="detail">

                                <small>
                                    Total Seats
                                </small>

                                <strong>
                                    <?= htmlspecialchars(
                                        $driver['seats']
                                    ) ?>
                                </strong>

                            </div>

                            <div class="detail">

                                <small>
                                    Bus Status
                                </small>

                                <strong>
                                    <?= ucfirst(
                                        htmlspecialchars(
                                            $driver['bus_status']
                                        )
                                    ) ?>
                                </strong>

                            </div>

                            <div class="detail">

                                <small>
                                    Total Trips
                                </small>

                                <strong>
                                    <?= $total_trips ?>
                                </strong>

                            </div>

                        </div>

                    <?php else: ?>

                        <div class="no-bus">
                            No bus has been assigned to this driver.
                        </div>

                    <?php endif; ?>

                </div>

                <div class="box">

                    <h2>
                        Passenger & Booking Summary
                    </h2>

                    <div class="details">

                        <div class="detail">

                            <small>
                                Today's Passengers
                            </small>

                            <strong>
                                <?= $today_bookings ?>
                            </strong>

                        </div>

                        <div class="detail">

                            <small>
                                Total Bookings
                            </small>

                            <strong>
                                <?= $total_bookings ?>
                            </strong>

                        </div>

                        <div class="detail">

                            <small>
                                Total Scheduled Trips
                            </small>

                            <strong>
                                <?= $total_trips ?>
                            </strong>

                        </div>

                        <div class="detail">

                            <small>
                                Current Status
                            </small>

                            <strong>
                                <?= htmlspecialchars($driver_status) ?>
                            </strong>

                        </div>

                    </div>

                </div>

                <div class="box danger-box">

                    <h2>
                        Driver Account
                    </h2>

                    <p>
                        Deleting this driver will permanently remove the
                        driver account and its related assignments.
                    </p>

                    <a
                        href="drivers.php?action=delete&id=<?= $driver_id ?>"
                        class="btn btn-delete"
                        onclick="return confirm('Are you sure you want to permanently delete this driver?')">
                        Delete Driver Account
                    </a>

                </div>

            </div>

        </div>

    </div>

</body>

</html>