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

if (isset($_GET['action'], $_GET['id'])) {

    $passenger_id = (int) $_GET['id'];
    $action = $_GET['action'];

    if ($action === 'verify') {

        $stmt = $conn->prepare("
            UPDATE users
            SET verification_status = 'verified'
            WHERE user_id = ?
            AND role = 'passenger'
        ");

        $stmt->bind_param("i", $passenger_id);
        $stmt->execute();

        header("Location: passengers.php?msg=verified");
        exit;
    }

    if ($action === 'unverify') {

        $stmt = $conn->prepare("
        UPDATE users
        SET verification_status = 'pending'
        WHERE user_id = ?
        AND role = 'passenger'
    ");

        $stmt->bind_param("i", $passenger_id);
        $stmt->execute();

        header("Location: passengers.php?msg=unverified");
        exit;
    }
    if ($action === 'delete') {

        $stmt = $conn->prepare("
            DELETE FROM users
            WHERE user_id = ?
            AND role = 'passenger'
        ");

        $stmt->bind_param("i", $passenger_id);
        $stmt->execute();

        header("Location: passengers.php?msg=deleted");
        exit;
    }
}

$search = isset($_GET['search'])
    ? trim($_GET['search'])
    : '';

$status = isset($_GET['status'])
    ? trim($_GET['status'])
    : 'all';

$sql = "
    SELECT
        u.user_id,
        u.name,
        u.email,
        u.phone,
        u.profile_image,
        u.verification_status,
        u.created_at,

        COUNT(bk.booking_id) AS total_bookings

    FROM users u

    LEFT JOIN bookings bk
        ON u.user_id = bk.user_id

    WHERE u.role = 'passenger'
";

$params = [];
$types = "";

if ($search !== '') {

    $sql .= "
        AND (
            u.name LIKE ?
            OR u.email LIKE ?
            OR u.phone LIKE ?
        )
    ";

    $search_value = "%" . $search . "%";

    $params[] = $search_value;
    $params[] = $search_value;
    $params[] = $search_value;

    $types .= "sss";
}

if ($status === 'verified') {

    $sql .= "
        AND u.verification_status = 'verified'
    ";
} elseif ($status === 'pending') {

    $sql .= "
        AND u.verification_status = 'pending'
    ";
}

$sql .= "
    GROUP BY
        u.user_id,
        u.name,
        u.email,
        u.phone,
        u.profile_image,
        u.verification_status,
        u.created_at

    ORDER BY u.user_id DESC
";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();

$result = $stmt->get_result();

$passengers = [];

while ($row = $result->fetch_assoc()) {
    $passengers[] = $row;
}

$total_passengers = count($passengers);

$verified_passengers = 0;
$unverified_passengers = 0;
$total_bookings = 0;

foreach ($passengers as $passenger) {

    if ($passenger['verification_status'] === 'verified') {
        $verified_passengers++;
    } else {
        $unverified_passengers++;
    }

    $total_bookings += (int) $passenger['total_bookings'];
}

$message = '';

if (isset($_GET['msg'])) {

    if ($_GET['msg'] === 'verified') {
        $message = "Passenger verified successfully.";
    }

    if ($_GET['msg'] === 'unverified') {
        $message = "Passenger verification removed.";
    }

    if ($_GET['msg'] === 'deleted') {
        $message = "Passenger deleted successfully.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>Manage Passengers</title>

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
            max-width: 1400px;
            margin: 30px auto;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 25px;
        }

        .page-header h1 {
            font-size: 28px;
            color: #222;
            margin-bottom: 6px;
        }

        .page-header p {
            color: #777;
            font-size: 14px;
        }

        .back-btn {
            background: #1560bd;
            color: #fff;
            text-decoration: none;
            padding: 11px 18px;
            border-radius: 7px;
            font-size: 14px;
        }

        .back-btn:hover {
            background: #0d4f9c;
        }

        .message {
            background: #d1e7dd;
            color: #0f5132;
            padding: 13px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 3px 12px rgba(0, 0, 0, .06);
        }

        .stat-title {
            color: #777;
            font-size: 14px;
            margin-bottom: 9px;
        }

        .stat-value {
            color: #1560bd;
            font-size: 28px;
            font-weight: 700;
        }

        .filters {
            background: #fff;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(0, 0, 0, .06);
            margin-bottom: 25px;
        }

        .filter-form {
            display: grid;
            grid-template-columns: 1fr 220px auto auto;
            gap: 12px;
        }

        .search-box,
        .status-select {
            width: 100%;
            height: 43px;
            border: 1px solid #ddd;
            border-radius: 7px;
            padding: 0 13px;
            font-size: 14px;
            outline: none;
        }

        .search-box:focus,
        .status-select:focus {
            border-color: #1560bd;
        }

        .filter-btn {
            height: 43px;
            padding: 0 20px;
            border: none;
            background: #1560bd;
            color: white;
            border-radius: 7px;
            cursor: pointer;
            font-size: 14px;
        }

        .filter-btn:hover {
            background: #0d4f9c;
        }

        .clear-btn {
            height: 43px;
            padding: 0 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            background: #6c757d;
            color: white;
            border-radius: 7px;
            font-size: 14px;
        }

        .clear-btn:hover {
            background: #565e64;
        }

        .passengers-box {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(0, 0, 0, .06);
            overflow: hidden;
        }

        .table-header {
            padding: 20px 22px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-header h2 {
            font-size: 19px;
        }

        .passenger-count {
            color: #777;
            font-size: 13px;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1050px;
        }

        th {
            background: #f7f9fc;
            color: #555;
            font-size: 13px;
            font-weight: 600;
            text-align: left;
            padding: 15px;
            border-bottom: 1px solid #e8e8e8;
        }

        td {
            padding: 15px;
            border-bottom: 1px solid #eee;
            font-size: 14px;
            vertical-align: middle;
        }

        tr:hover td {
            background: #fafcff;
        }

        .passenger-info {
            display: flex;
            align-items: center;
            gap: 11px;
        }

        .passenger-image {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #e5e5e5;
        }

        .passenger-name {
            font-weight: 600;
            color: #222;
            margin-bottom: 3px;
        }

        .passenger-email {
            color: #888;
            font-size: 12px;
        }

        .badge {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 11px;
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

        .booking-badge {
            background: #cff4fc;
            color: #055160;
        }

        .actions {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }

        .action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 34px;
            padding: 0 10px;
            border-radius: 6px;
            text-decoration: none;
            border: none;
            font-size: 12px;
            cursor: pointer;
            white-space: nowrap;
        }

        .view {
            background: #1560bd;
            color: #fff;
        }

        .view:hover {
            background: #0d4f9c;
        }

        .approve {
            background: #198754;
            color: #fff;
        }

        .approve:hover {
            background: #146c43;
        }

        .reject {
            background: #ffc107;
            color: #212529;
        }

        .reject:hover {
            background: #e0a800;
        }

        .delete {
            background: #dc3545;
            color: #fff;
        }

        .delete:hover {
            background: #b02a37;
        }

        .empty {
            padding: 60px 20px;
            text-align: center;
            color: #777;
        }

        .empty-icon {
            font-size: 42px;
            margin-bottom: 12px;
        }

        .empty h3 {
            color: #444;
            margin-bottom: 6px;
        }

        .empty p {
            font-size: 14px;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            inset: 0;
            background: rgba(0, 0, 0, .55);
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal.active {
            display: flex;
        }

        .modal-box {
            width: 100%;
            max-width: 520px;
            background: #fff;
            border-radius: 13px;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(0, 0, 0, .25);
        }

        .modal-header {
            padding: 18px 20px;
            background: #1560bd;
            color: #fff;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            font-size: 18px;
        }

        .close-modal {
            border: none;
            background: transparent;
            color: #fff;
            font-size: 24px;
            cursor: pointer;
        }

        .modal-body {
            padding: 22px;
        }

        .modal-profile {
            text-align: center;
            margin-bottom: 20px;
        }

        .modal-profile img {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #1560bd;
            margin-bottom: 10px;
        }

        .modal-profile h3 {
            margin-bottom: 5px;
        }

        .modal-profile p {
            color: #777;
            font-size: 13px;
        }

        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .detail-item {
            background: #f7f9fc;
            padding: 12px;
            border-radius: 7px;
        }

        .detail-item small {
            display: block;
            color: #777;
            margin-bottom: 5px;
        }

        .detail-item strong {
            font-size: 14px;
        }

        @media (max-width: 1000px) {

            .stats {
                grid-template-columns: repeat(2, 1fr);
            }

            .filter-form {
                grid-template-columns: 1fr 1fr;
            }

        }

        @media (max-width: 650px) {

            .page {
                width: 94%;
                margin: 20px auto;
            }

            .page-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .stats {
                grid-template-columns: 1fr;
            }

            .filter-form {
                grid-template-columns: 1fr;
            }

            .filter-btn,
            .clear-btn {
                width: 100%;
            }

            .detail-grid {
                grid-template-columns: 1fr;
            }

        }
    </style>

</head>

<body>

    <div class="page">

        <div class="page-header">

            <div>

                <h1>
                    Passenger Management
                </h1>

                <p>
                    Manage, verify and monitor all registered passengers.
                </p>

            </div>

            <a
                href="dashboard.php"
                class="back-btn">
                ← Dashboard
            </a>

        </div>

        <?php if ($message): ?>

            <div class="message">
                <?= htmlspecialchars($message) ?>
            </div>

        <?php endif; ?>

        <div class="stats">

            <div class="stat-card">

                <div class="stat-title">
                    Total Passengers
                </div>

                <div class="stat-value">
                    <?= $total_passengers ?>
                </div>

            </div>

            <div class="stat-card">

                <div class="stat-title">
                    Verified Passengers
                </div>

                <div class="stat-value">
                    <?= $verified_passengers ?>
                </div>

            </div>

            <div class="stat-card">

                <div class="stat-title">
                    Unverified Passengers
                </div>

                <div class="stat-value">
                    <?= $unverified_passengers ?>
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

        <div class="filters">

            <form
                method="GET"
                class="filter-form">

                <input
                    type="text"
                    name="search"
                    class="search-box"
                    placeholder="Search by name, email or phone..."
                    value="<?= htmlspecialchars($search) ?>">

                <select
                    name="status"
                    class="status-select">

                    <option
                        value="all"
                        <?= $status === 'all' ? 'selected' : '' ?>>
                        All Passengers
                    </option>

                    <option
                        value="verified"
                        <?= $status === 'verified' ? 'selected' : '' ?>>
                        Verified
                    </option>

                    <option value="pending"
                        <?= $status === 'pending' ? 'selected' : '' ?>>
                        Pending
                    </option>

                </select>

                <button
                    type="submit"
                    class="filter-btn">
                    Search
                </button>

                <a
                    href="passengers.php"
                    class="clear-btn">
                    Clear
                </a>

            </form>

        </div>

        <div class="passengers-box">

            <div class="table-header">

                <h2>
                    All Passengers
                </h2>

                <span class="passenger-count">
                    <?= $total_passengers ?> passenger(s)
                </span>

            </div>

            <?php if (!empty($passengers)): ?>

                <div class="table-wrapper">

                    <table>

                        <thead>

                            <tr>

                                <th>
                                    Passenger
                                </th>

                                <th>
                                    Phone
                                </th>

                                <th>
                                    Verification
                                </th>

                                <th>
                                    Bookings
                                </th>

                                <th>
                                    Registered
                                </th>

                                <th>
                                    Actions
                                </th>

                            </tr>

                        </thead>

                        <tbody>

                            <?php foreach ($passengers as $passenger): ?>

                                <?php

                                $image = !empty($passenger['profile_image'])
                                    ? $passenger['profile_image']
                                    : 'default.png';

                                ?>

                                <tr>

                                    <td>

                                        <div class="passenger-info">

                                            <img
                                                src="../uploads/profile/<?= htmlspecialchars($image) ?>"
                                                class="passenger-image"
                                                alt="Passenger"
                                                onerror="this.onerror=null; this.src='../images/default.png';">

                                            <div>

                                                <div class="passenger-name">
                                                    <?= htmlspecialchars($passenger['name']) ?>
                                                </div>

                                                <div class="passenger-email">
                                                    <?= htmlspecialchars($passenger['email']) ?>
                                                </div>

                                            </div>

                                        </div>

                                    </td>

                                    <td>
                                        <?= htmlspecialchars($passenger['phone']) ?>
                                    </td>

                                    <td>

                                        <?php if (
                                            $passenger['verification_status'] === 'verified'
                                        ): ?>

                                            <span class="badge verified">
                                                Verified
                                            </span>

                                        <?php else: ?>

                                            <span class="badge unverified">
                                                Unverified
                                            </span>

                                        <?php endif; ?>

                                    </td>

                                    <td>

                                        <span class="badge booking-badge">
                                            <?= (int) $passenger['total_bookings'] ?>
                                            Booking(s)
                                        </span>

                                    </td>

                                    <td>

                                        <?= date(
                                            "d M Y",
                                            strtotime($passenger['created_at'])
                                        ) ?>

                                    </td>

                                    <td>

                                        <div class="actions">

                                            <button
                                                type="button"
                                                class="action-btn view"
                                                onclick='openPassengerModal(<?= json_encode($passenger) ?>)'>
                                                View
                                            </button>

                                            <a
                                                href="view_passenger.php?id=<?= (int) $passenger['user_id'] ?>"
                                                class="action-btn view">
                                                Details
                                            </a>

                                            <?php if (
                                                $passenger['verification_status'] !== 'verified'
                                            ): ?>

                                                <a
                                                    href="passengers.php?action=verify&id=<?= (int) $passenger['user_id'] ?>"
                                                    class="action-btn approve"
                                                    onclick="return confirm('Verify this passenger?')">
                                                    Verify
                                                </a>

                                            <?php else: ?>

                                                <a
                                                    href="passengers.php?action=unverify&id=<?= (int) $passenger['user_id'] ?>"
                                                    class="action-btn reject"
                                                    onclick="return confirm('Remove verification from this passenger?')">
                                                    Unverify
                                                </a>

                                            <?php endif; ?>

                                            <a
                                                href="passengers.php?action=delete&id=<?= (int) $passenger['user_id'] ?>"
                                                class="action-btn delete"
                                                onclick="return confirm('Are you sure you want to delete this passenger?')">
                                                Delete
                                            </a>

                                        </div>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <div class="empty">

                    <div class="empty-icon">
                        👤
                    </div>

                    <h3>
                        No Passengers Found
                    </h3>

                    <p>
                        No passenger matches your current search or filter.
                    </p>

                </div>

            <?php endif; ?>

        </div>

    </div>

    <div
        class="modal"
        id="passengerModal"
        onclick="closeModalOutside(event)">

        <div class="modal-box">

            <div class="modal-header">

                <h3>
                    Passenger Details
                </h3>

                <button
                    class="close-modal"
                    onclick="closePassengerModal()">
                    ×
                </button>

            </div>

            <div class="modal-body">

                <div class="modal-profile">

                    <img
                        id="modalImage"
                        src="../uploads/profile/default.png"
                        alt="Passenger">

                    <h3 id="modalName">
                        Passenger
                    </h3>

                    <p id="modalEmail">
                        -
                    </p>

                </div>

                <div class="detail-grid">

                    <div class="detail-item">

                        <small>
                            Passenger ID
                        </small>

                        <strong id="modalId">
                            -
                        </strong>

                    </div>

                    <div class="detail-item">

                        <small>
                            Phone
                        </small>

                        <strong id="modalPhone">
                            -
                        </strong>

                    </div>

                    <div class="detail-item">

                        <small>
                            Verification
                        </small>

                        <strong id="modalVerification">
                            -
                        </strong>

                    </div>

                    <div class="detail-item">

                        <small>
                            Total Bookings
                        </small>

                        <strong id="modalBookings">
                            -
                        </strong>

                    </div>

                    <div class="detail-item">

                        <small>
                            Registered Date
                        </small>

                        <strong id="modalDate">
                            -
                        </strong>

                    </div>

                    <div class="detail-item">

                        <small>
                            Account Role
                        </small>

                        <strong>
                            Passenger
                        </strong>

                    </div>

                </div>

            </div>

        </div>

    </div>

    <script>
        function openPassengerModal(passenger) {

            const modal =
                document.getElementById("passengerModal");

            const modalImage = document.getElementById("modalImage");

            modalImage.removeAttribute("src");

            if (
                passenger.profile_image &&
                passenger.profile_image.trim() !== "" &&
                passenger.profile_image !== "default.png"
            ) {

                modalImage.src =
                    "../uploads/profile/" +
                    passenger.profile_image.trim();

            } else {

                modalImage.src =
                    "../uploads/profile/default.png";
            }

            modalImage.onerror = function() {

                this.onerror = null;

                this.src =
                    "../uploads/profile/default.png";
            };
            document.getElementById("modalName").textContent =
                passenger.name || "-";

            document.getElementById("modalEmail").textContent =
                passenger.email || "-";

            document.getElementById("modalId").textContent =
                passenger.user_id || "-";

            document.getElementById("modalPhone").textContent =
                passenger.phone || "-";

            document.getElementById("modalVerification").textContent =
                passenger.verification_status === "verified" ?
                "Verified " :
                "Unverified";

            document.getElementById("modalBookings").textContent =
                (passenger.total_bookings || 0) + " Booking(s)";

            if (passenger.created_at) {

                const date = new Date(
                    passenger.created_at.replace(" ", "T")
                );

                if (!isNaN(date)) {

                    document.getElementById("modalDate").textContent =
                        date.toLocaleDateString(
                            "en-GB", {
                                day: "2-digit",
                                month: "short",
                                year: "numeric"
                            }
                        );

                } else {

                    document.getElementById("modalDate").textContent =
                        passenger.created_at;

                }

            } else {

                document.getElementById("modalDate").textContent =
                    "-";

            }

            modal.classList.add("active");
        }

        function closePassengerModal() {

            document
                .getElementById("passengerModal")
                .classList.remove("active");

        }

        function closeModalOutside(event) {

            if (
                event.target ===
                document.getElementById("passengerModal")
            ) {
                closePassengerModal();
            }

        }

        document.addEventListener(
            "keydown",
            function(event) {

                if (event.key === "Escape") {
                    closePassengerModal();
                }

            }
        );
    </script>

</body>

</html>