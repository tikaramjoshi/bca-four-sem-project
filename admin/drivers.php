<?php
session_start();
require_once "../db.php";
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../login.php");
    exit;
}
if (isset($_GET['action'], $_GET['id'])) {
    $id = (int)$_GET['id'];
    $action = $_GET['action'];
    if (in_array($action, ['verify', 'reject', 'delete'])) {
        if ($action === 'delete') {
            $stmt = $conn->prepare("DELETE FROM users WHERE user_id=? AND role='driver'");
        } else {
            $status_value = $action === 'verify' ? 'verified' : 'rejected';
            $stmt = $conn->prepare("UPDATE users SET verification_status=? WHERE user_id=? AND role='driver'");
            $stmt->bind_param("si", $status_value, $id);
        }
        if ($action === 'delete') $stmt->bind_param("i", $id);
        $stmt->execute();
        header("Location: drivers.php?msg=" . ($action === 'verify' ? 'verified' : ($action === 'reject' ? 'rejected' : 'deleted')));
        exit;
    }
}
$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? 'all';
$sql = "SELECT u.user_id,u.name,u.email,u.phone,u.profile_image,u.verification_status,u.created_at,b.bus_id,b.bus_number,b.bus_name,b.bus_type,b.status AS bus_status FROM users u LEFT JOIN bus_driver bd ON u.user_id=bd.driver_id LEFT JOIN bus b ON bd.bus_id=b.bus_id WHERE u.role='driver'";
$params = [];
$types = "";
if ($search !== '') {
    $sql .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR b.bus_number LIKE ? OR b.bus_name LIKE ?)";
    $v = "%$search%";
    $params = [$v, $v, $v, $v, $v];
    $types = "sssss";
}
if (in_array($status, ['pending', 'verified', 'rejected'])) $sql .= " AND u.verification_status='$status'";
$sql .= " ORDER BY u.user_id DESC";
$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$drivers = [];
while ($row = $result->fetch_assoc()) $drivers[] = $row;
$total_drivers = count($drivers);
$verified_drivers = $unverified_drivers = $assigned_drivers = 0;
foreach ($drivers as $driver) {
    $driver['verification_status'] === 'verified' ? $verified_drivers++ : $unverified_drivers++;
    if (!empty($driver['bus_id'])) $assigned_drivers++;
}
$message = match ($_GET['msg'] ?? '') {
    'verified' => 'Driver verified successfully.',
    'rejected' => 'Driver verification rejected.',
    'deleted' => 'Driver deleted successfully.',
    default => ''
};
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Manage Drivers</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="drivers.css">
    <link rel="stylesheet" href="side.css">
</head>

<body>

    <?php include "admin_header.php"; ?>
    <div class="content">
        <div class="page">
            <div class="page-header">
                <div>
                    <h1>Driver Management</h1>
                    <p>Manage, verify and monitor all registered drivers.</p>
                </div><a href="dashboard.php" class="back-btn"> <i class="fa fa-home"></i>&nbsp; Home &nbsp;</a>
            </div>
            <?php if ($message): ?><div class="message"><?= htmlspecialchars($message) ?></div><?php endif; ?>
            <div class="stats">
                <div class="stat-card">
                    <div class="stat-title">Total Drivers</div>
                    <div class="stat-value"><?= $total_drivers ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-title">Verified Drivers</div>
                    <div class="stat-value"><?= $verified_drivers ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-title">Unverified Drivers</div>
                    <div class="stat-value"><?= $unverified_drivers ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-title">Assigned Drivers</div>
                    <div class="stat-value"><?= $assigned_drivers ?></div>
                </div>
            </div>
            <div class="filters">
                <form method="GET" class="filter-form">
                    <input type="text" name="search" class="search-box" placeholder="Search by name, email, phone or bus..." value="<?= htmlspecialchars($search) ?>">
                    <select name="status" class="status-select">
                        <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All Drivers</option>
                        <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="verified" <?= $status === 'verified' ? 'selected' : '' ?>>Verified</option>
                        <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                    </select>
                    <button type="submit" class="filter-btn">Search</button>
                    <a href="drivers.php" class="clear-btn">Clear</a>
                </form>
            </div>
            <div class="drivers-box">
                <div class="table-header">
                    <h2>All Drivers</h2><span class="driver-count"><?= $total_drivers ?> driver(s)</span>
                </div>
                <?php if ($drivers): ?>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Driver</th>
                                    <th>Phone</th>
                                    <th>Verification</th>
                                    <th>Assigned Bus</th>
                                    <th>Registered</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($drivers as $driver): $image = $driver['profile_image'] ?: 'default.png'; ?>
                                    <tr>
                                        <td>
                                            <div class="driver-info"><img src="../uploads/profile/<?= htmlspecialchars($image) ?>" class="driver-image" alt="Driver" onerror="this.onerror=null;this.src='../uploads/default.png'">
                                                <div>
                                                    <div class="driver-name"><?= htmlspecialchars($driver['name']) ?></div>
                                                    <div class="driver-email"><?= htmlspecialchars($driver['email']) ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($driver['phone']) ?></td>
                                        <td><?php if ($driver['verification_status'] === 'verified'): ?><span class="badge verified">Verified</span><?php elseif ($driver['verification_status'] === 'pending'): ?><span class="badge unverified">Pending</span><?php else: ?><span class="badge unverified">Rejected</span><?php endif; ?></td>
                                        <td><?php if ($driver['bus_id']): ?><span class="badge assigned"><?= htmlspecialchars($driver['bus_number']) ?></span>
                                                <div class="bus-type"><?= htmlspecialchars($driver['bus_type']) ?></div><?php else: ?><span class="badge not-assigned">Not Assigned</span><?php endif; ?>
                                        </td>
                                        <td><?= date("d M Y", strtotime($driver['created_at'])) ?></td>
                                        <td>
                                            <div class="actions">
                                                <button type="button" class="action-btn view" onclick='openDriverModal(<?= json_encode($driver) ?>)'>View</button>
                                                <?php if ($driver['verification_status'] !== 'verified'): ?><a href="drivers.php?action=verify&id=<?= (int)$driver['user_id'] ?>" class="action-btn approve" onclick="return confirm('Verify this driver?')">Verify</a><?php else: ?><a href="drivers.php?action=reject&id=<?= (int)$driver['user_id'] ?>" class="action-btn reject" onclick="return confirm('Reject verification for this driver?')">Reject</a><?php endif; ?>
                                                <a href="view_driver.php?id=<?= (int)$driver['user_id'] ?>" class="action-btn view">Details</a>
                                                <a href="drivers.php?action=delete&id=<?= (int)$driver['user_id'] ?>" class="action-btn delete" onclick="return confirm('Are you sure you want to delete this driver?')">Delete</a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?><div class="empty">
                        <h3>No Drivers Found</h3>
                        <p>No driver matches your current search or filter.</p>
                    </div><?php endif; ?>
            </div>
        </div>
        <div class="modal" id="driverModal" onclick="closeModalOutside(event)">
            <div class="modal-box">
                <div class="modal-header">
                    <h3>Driver Details</h3><button class="close-modal" onclick="closeDriverModal()"> <i class=" fa fa-times"></i></button>
                </div>
                <div class="modal-body">
                    <div class="modal-profile"><img id="modalImage" src="../uploads/default.png" alt="Driver">
                        <h3 id="modalName">Driver</h3>
                        <p id="modalEmail">-</p>
                    </div>
                    <div class="detail-grid">
                        <div class="detail-item"><small>Driver ID</small><strong id="modalId">-</strong></div>
                        <div class="detail-item"><small>Phone</small><strong id="modalPhone">-</strong></div>
                        <div class="detail-item"><small>Verification</small><strong id="modalVerification">-</strong></div>
                        <div class="detail-item"><small>Bus Number</small><strong id="modalBus">-</strong></div>
                        <div class="detail-item"><small>Bus Name</small><strong id="modalBusName">-</strong></div>
                        <div class="detail-item"><small>Bus Type</small><strong id="modalBusType">-</strong></div>
                        <div class="detail-item"><small>Bus Status</small><strong id="modalBusStatus">-</strong></div>
                        <div class="detail-item"><small>Registered Date</small><strong id="modalDate">-</strong></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
        function openDriverModal(d) {
            let m = document.getElementById("driverModal"),
                i = document.getElementById("modalImage");
            i.src = d.profile_image ? "../uploads/profile/" + d.profile_image : "../uploads/default.png";
            i.onerror = function() {
                this.onerror = null;
                this.src = "../uploads/default.png"
            };
            document.getElementById("modalName").textContent = d.name || "-";
            document.getElementById("modalEmail").textContent = d.email || "-";
            document.getElementById("modalId").textContent = d.user_id || "-";
            document.getElementById("modalPhone").textContent = d.phone || "-";
            document.getElementById("modalVerification").textContent = d.verification_status === "verified" ? "Verified" : d.verification_status === "pending" ? "Pending" : d.verification_status === "rejected" ? "Rejected" : "-";
            document.getElementById("modalBus").textContent = d.bus_number || "Not Assigned";
            document.getElementById("modalBusName").textContent = d.bus_name || "-";
            document.getElementById("modalBusType").textContent = d.bus_type || "-";
            document.getElementById("modalBusStatus").textContent = d.bus_status || "-";
            document.getElementById("modalDate").textContent = d.created_at ? new Date(d.created_at.replace(" ", "T")).toLocaleDateString("en-GB", {
                day: "2-digit",
                month: "short",
                year: "numeric"
            }) : "-";
            m.classList.add("active");
        }

        function closeDriverModal() {
            document.getElementById("driverModal").classList.remove("active")
        }

        function closeModalOutside(e) {
            if (e.target === document.getElementById("driverModal")) closeDriverModal()
        }
        document.addEventListener("keydown", e => {
            if (e.key === "Escape") closeDriverModal()
        });
    </script>
</body>

</html>