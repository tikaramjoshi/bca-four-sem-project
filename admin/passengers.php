<?php
session_start();
require_once "../db.php";
if (!isset($_SESSION['user_id'], $_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}
if (isset($_GET['action'], $_GET['id'])) {
    $id = (int)$_GET['id'];
    $action = $_GET['action'];
    if ($action === 'verify') $status = 'verified';
    elseif ($action === 'unverify') $status = 'pending';
    if (isset($status)) {
        $stmt = $conn->prepare("UPDATE users SET verification_status=? WHERE user_id=? AND role='passenger'");
        $stmt->bind_param("si", $status, $id);
        $stmt->execute();
        header("Location: passengers.php?msg=" . ($status === 'verified' ? 'verified' : 'unverified'));
        exit;
    }
    if ($action === 'delete') {
        $stmt = $conn->prepare("DELETE FROM users WHERE user_id=? AND role='passenger'");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        header("Location: passengers.php?msg=deleted");
        exit;
    }
}
$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? 'all');
$sql = "SELECT u.user_id,u.name,u.email,u.phone,u.profile_image,u.verification_status,u.created_at,COUNT(bk.booking_id) total_bookings FROM users u LEFT JOIN bookings bk ON u.user_id=bk.user_id WHERE u.role='passenger'";
$params = [];
$types = "";
if ($search !== '') {
    $sql .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $v = "%$search%";
    $params = [$v, $v, $v];
    $types = "sss";
}
if ($status === 'verified' || $status === 'pending') $sql .= " AND u.verification_status='$status'";
$sql .= " GROUP BY u.user_id,u.name,u.email,u.phone,u.profile_image,u.verification_status,u.created_at ORDER BY u.user_id DESC";
$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$passengers = [];
while ($row = $result->fetch_assoc()) $passengers[] = $row;
$total_passengers = count($passengers);
$verified_passengers = $unverified_passengers = $total_bookings = 0;
foreach ($passengers as $p) {
    if ($p['verification_status'] === 'verified') $verified_passengers++;
    else $unverified_passengers++;
    $total_bookings += (int)$p['total_bookings'];
}
$message = '';
if (isset($_GET['msg'])) {
    $message = [
        'verified' => 'Passenger verified successfully.',
        'unverified' => 'Passenger verification removed.',
        'deleted' => 'Passenger deleted successfully.'
    ][$_GET['msg']] ?? '';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Manage Passengers</title>
    <link rel="stylesheet" href="passengers.css">
</head>

<body>
    <div class="page">
        <div class="page-header">
            <div>
                <h1>Passenger Management</h1>
                <p>Manage, verify and monitor all registered passengers.</p>
            </div>
            <a href="dashboard.php" class="back-btn">← Dashboard</a>
        </div>

        <?php if ($message): ?><div class="message"><?= htmlspecialchars($message) ?></div><?php endif; ?>

        <div class="stats">
            <div class="stat-card">
                <div class="stat-title">Total Passengers</div>
                <div class="stat-value"><?= $total_passengers ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-title">Verified Passengers</div>
                <div class="stat-value"><?= $verified_passengers ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-title">Unverified Passengers</div>
                <div class="stat-value"><?= $unverified_passengers ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-title">Total Bookings</div>
                <div class="stat-value"><?= $total_bookings ?></div>
            </div>
        </div>

        <div class="filters">
            <form method="GET" class="filter-form">
                <input type="text" name="search" class="search-box" placeholder="Search by name, email or phone..." value="<?= htmlspecialchars($search) ?>">
                <select name="status" class="status-select">
                    <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All Passengers</option>
                    <option value="verified" <?= $status === 'verified' ? 'selected' : '' ?>>Verified</option>
                    <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                </select>
                <button type="submit" class="filter-btn">Search</button>
                <a href="passengers.php" class="clear-btn">Clear</a>
            </form>
        </div>

        <div class="passengers-box">
            <div class="table-header">
                <h2>All Passengers</h2><span class="passenger-count"><?= $total_passengers ?> passenger(s)</span>
            </div>

            <?php if ($passengers): ?>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Passenger</th>
                                <th>Phone</th>
                                <th>Verification</th>
                                <th>Bookings</th>
                                <th>Registered</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($passengers as $p): $image = $p['profile_image'] ?: 'default.png'; ?>
                                <tr>
                                    <td>
                                        <div class="passenger-info">
                                            <img src="../uploads/profile/<?= htmlspecialchars($image) ?>" class="passenger-image" alt="Passenger" onerror="this.onerror=null;this.src='../images/default.png';">
                                            <div>
                                                <div class="passenger-name"><?= htmlspecialchars($p['name']) ?></div>
                                                <div class="passenger-email"><?= htmlspecialchars($p['email']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($p['phone']) ?></td>
                                    <td><span class="badge <?= $p['verification_status'] === 'verified' ? 'verified' : 'unverified' ?>"><?= $p['verification_status'] === 'verified' ? 'Verified' : 'Unverified' ?></span></td>
                                    <td><span class="badge booking-badge"><?= ((int)$p['total_bookings']) ?> Booking(s)</span></td>
                                    <td><?= date("d M Y", strtotime($p['created_at'])) ?></td>
                                    <td>
                                        <div class="actions">
                                            <button type="button" class="action-btn view" onclick='openPassengerModal(<?= json_encode($p) ?>)'>View</button>
                                            <a href="view_passenger.php?id=<?= (int)$p['user_id'] ?>" class="action-btn view">Details</a>
                                            <?php if ($p['verification_status'] !== 'verified'): ?>
                                                <a href="passengers.php?action=verify&id=<?= (int)$p['user_id'] ?>" class="action-btn approve" onclick="return confirm('Verify this passenger?')">Verify</a>
                                            <?php else: ?>
                                                <a href="passengers.php?action=unverify&id=<?= (int)$p['user_id'] ?>" class="action-btn reject" onclick="return confirm('Remove verification from this passenger?')">Unverify</a>
                                            <?php endif; ?>
                                            <a href="passengers.php?action=delete&id=<?= (int)$p['user_id'] ?>" class="action-btn delete" onclick="return confirm('Are you sure you want to delete this passenger?')">Delete</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty">
                    <div class="empty-icon">👤</div>
                    <h3>No Passengers Found</h3>
                    <p>No passenger matches your current search or filter.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="modal" id="passengerModal" onclick="closeModalOutside(event)">
        <div class="modal-box">
            <div class="modal-header">
                <h3>Passenger Details</h3><button class="close-modal" onclick="closePassengerModal()">×</button>
            </div>
            <div class="modal-body">
                <div class="modal-profile">
                    <img id="modalImage" src="../uploads/profile/default.png" alt="Passenger">
                    <h3 id="modalName">Passenger</h3>
                    <p id="modalEmail">-</p>
                </div>
                <div class="detail-grid">
                    <div class="detail-item"><small>Passenger ID</small><strong id="modalId">-</strong></div>
                    <div class="detail-item"><small>Phone</small><strong id="modalPhone">-</strong></div>
                    <div class="detail-item"><small>Verification</small><strong id="modalVerification">-</strong></div>
                    <div class="detail-item"><small>Total Bookings</small><strong id="modalBookings">-</strong></div>
                    <div class="detail-item"><small>Registered Date</small><strong id="modalDate">-</strong></div>
                    <div class="detail-item"><small>Account Role</small><strong>Passenger</strong></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openPassengerModal(p) {
            const m = document.getElementById("passengerModal"),
                i = document.getElementById("modalImage");
            i.src = p.profile_image && p.profile_image.trim() && p.profile_image !== "default.png" ? "../uploads/profile/" + p.profile_image.trim() : "../uploads/profile/default.png";
            i.onerror = function() {
                this.onerror = null;
                this.src = "../images/default.png";
            };
            document.getElementById("modalName").textContent = p.name || "-";
            document.getElementById("modalEmail").textContent = p.email || "-";
            document.getElementById("modalId").textContent = p.user_id || "-";
            document.getElementById("modalPhone").textContent = p.phone || "-";
            document.getElementById("modalVerification").textContent = p.verification_status === "verified" ? "Verified" : "Unverified";
            document.getElementById("modalBookings").textContent = (p.total_bookings || 0) + " Booking(s)";
            if (p.created_at) {
                const d = new Date(p.created_at.replace(" ", "T"));
                document.getElementById("modalDate").textContent = !isNaN(d) ? d.toLocaleDateString("en-GB", {
                    day: "2-digit",
                    month: "short",
                    year: "numeric"
                }) : p.created_at;
            } else document.getElementById("modalDate").textContent = "-";
            m.classList.add("active");
        }

        function closePassengerModal() {
            document.getElementById("passengerModal").classList.remove("active");
        }

        function closeModalOutside(e) {
            if (e.target === document.getElementById("passengerModal")) closePassengerModal();
        }
        document.addEventListener("keydown", e => {
            if (e.key === "Escape") closePassengerModal();
        });
    </script>
</body>

</html>