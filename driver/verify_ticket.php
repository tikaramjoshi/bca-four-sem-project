<?php
session_start();
require_once "../db.php";

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'driver') {
    header("Location: ../login.php");
    exit;
}

$driver_id = (int) $_SESSION['user_id'];
$group_id = trim($_GET['group_id'] ?? '');
$message = "";
$message_type = "";
$bookings = [];

if ($group_id === '') {
    header("Location: scan_ticket.php");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function getBookings($conn, $group_id, $driver_id)
{
    $sql = "SELECT bk.booking_id, bk.booking_group_id, bk.user_id,        bk.bus_name, bk.bus_number, bk.route, bk.travel_date,        bk.seat_number, bk.amount, bk.status, bk.ticket_status,        bk.scanned_at, u.name AS passenger_name FROM bookings bk INNER JOIN users u ON bk.user_id = u.user_id WHERE bk.booking_group_id = ?   AND EXISTS (       SELECT 1       FROM bus b       INNER JOIN bus_driver bd ON bd.bus_id = b.bus_id       WHERE b.bus_number = bk.bus_number         AND bd.driver_id = ?   ) ORDER BY CAST(bk.seat_number AS UNSIGNED) ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $group_id, $driver_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $rows;
}

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['confirm_payment_checkin'])
) {

    if (!hash_equals(
        $_SESSION['csrf_token'],
        $_POST['csrf_token'] ?? ''
    )) {
        $message = "Invalid request. Please scan the ticket again.";
        $message_type = "error";
    } else {
        try {
            $conn->begin_transaction();

            $bookings = getBookings($conn, $group_id, $driver_id);

            if (empty($bookings)) {
                throw new Exception("Booking not found or not assigned to you.");
            }

            $has_confirmed = false;
            $has_pending = false;
            $has_active_confirmed = false;

            foreach ($bookings as $booking) {
                $status = strtolower(trim($booking['status'] ?? ''));
                $ticket_status = strtolower(trim($booking['ticket_status'] ?? ''));

                if ($status === 'confirmed') {
                    $has_confirmed = true;

                    if ($ticket_status !== 'used') {
                        $has_active_confirmed = true;
                    }
                }

                if ($status === 'pending') {
                    $has_pending = true;
                }
            }

            if (!$has_confirmed) {
                throw new Exception("No confirmed tickets available for check-in.");
            }

            if (!$has_active_confirmed) {
                throw new Exception("Confirmed tickets in this booking are already checked in.");
            }

            $updateConfirmed = $conn->prepare(
                "UPDATE bookings  SET ticket_status = 'used',      scanned_at = NOW(),      scanned_by = ?  WHERE booking_group_id = ?    AND status = 'confirmed'    AND ticket_status = 'active'    AND EXISTS (        SELECT 1        FROM bus b        INNER JOIN bus_driver bd ON bd.bus_id = b.bus_id        WHERE b.bus_number = bookings.bus_number          AND bd.driver_id = ?    )"
            );

            $updateConfirmed->bind_param(
                "isi",
                $driver_id,
                $group_id,
                $driver_id
            );
            $updateConfirmed->execute();
            $checkedInCount = $updateConfirmed->affected_rows;
            $updateConfirmed->close();

            if ($checkedInCount < 1) {
                throw new Exception("No active confirmed tickets available for check-in.");
            }

            $updatePending = $conn->prepare(
                "UPDATE bookings SET status = 'cancelled' WHERE booking_group_id = ?   AND status = 'pending'   AND EXISTS (       SELECT 1       FROM bus b       INNER JOIN bus_driver bd ON bd.bus_id = b.bus_id       WHERE b.bus_number = bookings.bus_number         AND bd.driver_id = ?   )"
            );

            $updatePending->bind_param("si", $group_id, $driver_id);
            $updatePending->execute();
            $updatePending->close();

            $conn->commit();

            header(
                "Location: verify_ticket.php?group_id="
                    . urlencode($group_id)
                    . "&result=success"
            );
            exit;
        } catch (Throwable $e) {
            $conn->rollback();
            $message = $e->getMessage();
            $message_type = "error";
        }
    }
}

if (($_GET['result'] ?? '') === 'success') {
    $message = "Payment Successful! Confirmed tickets checked in successfully.";
    $message_type = "success";
}

$bookings = getBookings($conn, $group_id, $driver_id);

if (empty($bookings) && $message_type !== 'success') {
    $message = "Booking not found or not assigned to you.";
    $message_type = "error";
}

$has_confirmed = false;
$has_active_confirmed = false;
$all_pending = !empty($bookings);
$all_cancelled = !empty($bookings);
$total_amount = 0;

foreach ($bookings as $booking) {
    $status = strtolower(trim($booking['status'] ?? ''));
    $ticket_status = strtolower(trim($booking['ticket_status'] ?? ''));

    if ($status === 'confirmed') {
        $has_confirmed = true;
        $total_amount += (float) $booking['amount'];

        if ($ticket_status !== 'used') {
            $has_active_confirmed = true;
        }
    }

    if ($status !== 'pending') {
        $all_pending = false;
    }

    if ($status !== 'cancelled') {
        $all_cancelled = false;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Booking</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="verify_ticket.css">
</head>

<body>

    <div class="container">
        <h2><i class="fas fa-ticket-alt"></i> Verify Booking</h2>
        <?php if ($message !== ''): ?>
            <div class="message <?= htmlspecialchars($message_type) ?>">
                <?php if ($message_type === 'success'): ?>
                    <i class="fas fa-check-circle"></i>
                <?php else: ?>
                    <i class="fas fa-exclamation-circle"></i>
                <?php endif; ?>
                <?= htmlspecialchars($message) ?>
                <?php if ($message_type === 'success'): ?>
                    <p>Redirecting to Scan Ticket page in 3 seconds...</p>
                    <script>
                        setTimeout(function() {
                            window.location.href = "scan_ticket.php";
                        }, 3000);
                    </script>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($bookings)): ?>
            <div class="booking-info">
                <p><strong>Passenger:</strong> <?= htmlspecialchars($bookings[0]['passenger_name'] ?? '') ?> </p>
                <p><strong>Booking Group:</strong> <?= htmlspecialchars($bookings[0]['booking_group_id']) ?> </p>
                <p><strong>Bus:</strong> <?= htmlspecialchars($bookings[0]['bus_name'] ?? '') ?> (<?= htmlspecialchars($bookings[0]['bus_number'] ?? '') ?>) </p>
                <p><strong>Route:</strong> <?= htmlspecialchars($bookings[0]['route'] ?? '') ?> </p>
                <p><strong>Travel Date:</strong> <?= htmlspecialchars($bookings[0]['travel_date'] ?? '') ?> </p>
                <p><strong>Confirmed Ticket Amount:</strong> Rs. <?= number_format($total_amount, 2) ?> </p>
            </div>
            <h3>Ticket Details</h3>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Seat No.</th>
                            <th>Amount</th>
                            <th>Booking Status</th>
                            <th>Ticket Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $booking): ?>
                            <tr>
                                <td><?= htmlspecialchars($booking['seat_number']) ?></td>
                                <td>Rs. <?= number_format((float) $booking['amount'], 2) ?></td>
                                <td> <span class="status <?= htmlspecialchars(strtolower($booking['status'] ?? 'pending')) ?>"> <?= htmlspecialchars($booking['status'] ?? 'pending') ?> </span> </td>
                                <td> <span class="status <?= htmlspecialchars(strtolower($booking['ticket_status'] ?? 'active')) ?>"> <?= htmlspecialchars($booking['ticket_status'] ?? 'active') ?> </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($has_active_confirmed && !$all_pending && !$all_cancelled): ?>
                <form method="POST" class="actions" onsubmit="return confirm('Have you received the payment? Confirm payment and check in the confirmed tic Pending tickets in this booking group will be cancelled.');">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <button type="submit" name="confirm_payment_checkin" class="btn btn-success"> <i class="fas fa-check-circle"></i> Confirm Payment &amp; Check In </button>
                    <a href="scan_ticket.php" class="btn btn-primary"> <i class="fas fa-qrcode"></i> Back to Scan </a>
                </form>
            <?php elseif ($all_pending): ?>
                <p class="note">All tickets are pending. Check-in is not allowed.</p>
                <div class="actions">
                    <a href="scan_ticket.php" class="btn btn-primary">Back to Scan</a>
                </div>

            <?php elseif ($all_cancelled): ?>
                <p class="note">All tickets are cancelled. Check-in is not allowed.</p>
                <div class="actions">
                    <a href="scan_ticket.php" class="btn btn-primary">Back to Scan</a>
                </div>

            <?php else: ?>
                <p class="note">All confirmed tickets have already been checked in.</p>
                <div class="actions">
                    <a href="scan_ticket.php" class="btn btn-primary">Back to Scan</a>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="actions">
                <a href="scan_ticket.php" class="btn btn-primary">Back to Scan</a>
            </div>
        <?php endif; ?>
    </div>

</body>

</html>