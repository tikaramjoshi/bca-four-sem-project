<?php
session_start();
require_once "../db.php";
if (!isset($_SESSION['user_id'], $_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id'])) {
    $id = (int)$_POST['id'];
    $action = $_POST['action'];
    if ($id > 0 && in_array($action, ['verify', 'reject', 'pending', 'delete'], true)) {
        if ($action === 'delete') {
            $stmt = $conn->prepare("DELETE FROM users WHERE user_id=? AND role='passenger'");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            header("Location: passengers.php?msg=deleted");
            exit;
        }
        $stmt = $conn->prepare("SELECT verification_status FROM users WHERE user_id=? AND role='passenger' LIMIT 1");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        if ($user) {
            $current = $user['verification_status'];
            $newStatus = null;
            if ($current === 'pending' && $action === 'verify') {
                $newStatus = 'verified';
            } elseif ($current === 'pending' && $action === 'reject') {
                $newStatus = 'rejected';
            } elseif ($current === 'verified' && $action === 'reject') {
                $newStatus = 'rejected';
            } elseif ($current === 'verified' && $action === 'pending') {
                $newStatus = 'pending';
            } elseif ($current === 'rejected' && $action === 'verify') {
                $newStatus = 'verified';
            } elseif ($current === 'rejected' && $action === 'pending') {
                $newStatus = 'pending';
            }
            if ($newStatus !== null) {
                $stmt = $conn->prepare("UPDATE users SET verification_status=? WHERE user_id=? AND role='passenger' AND verification_status=?");
                $stmt->bind_param("sis", $newStatus, $id, $current);
                $stmt->execute();
                header("Location: passengers.php?msg=" . $newStatus);
                exit;
            }
        }
    }
    header("Location: passengers.php");
    exit;
}
$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? 'all');
$sql = "SELECT u.user_id,u.name,u.email,u.phone,u.profile_image,u.verification_status,u.created_at,COUNT(bk.booking_id) total_bookings FROM users u LEFT JOIN bookings bk ON u.user_id=bk.user_id WHERE u.role='passenger'";
$params = [];
$types = "";
if ($search !== '') {
    $sql .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $v = "%" . $search . "%";
    $params = [$v, $v, $v];
    $types = "sss";
}
if (in_array($status, ['verified', 'pending', 'rejected'], true)) {
    $sql .= " AND u.verification_status='" . $conn->real_escape_string($status) . "'";
}
$sql .= " GROUP BY u.user_id,u.name,u.email,u.phone,u.profile_image,u.verification_status,u.created_at ORDER BY u.user_id DESC";
$stmt = $conn->prepare($sql);
if ($params) {
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
$pending_passengers = 0;
$rejected_passengers = 0;
$total_bookings = 0;
foreach ($passengers as $p) {
    if ($p['verification_status'] === 'verified') {
        $verified_passengers++;
    } elseif ($p['verification_status'] === 'rejected') {
        $rejected_passengers++;
    } else {
        $pending_passengers++;
    }
    $total_bookings += (int)$p['total_bookings'];
}
$message = '';
if (isset($_GET['msg'])) {
    $message = [
        'verified' => 'Passenger verified successfully.',
        'rejected' => 'Passenger rejected successfully.',
        'pending' => 'Passenger moved to pending successfully.',
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
    <link rel="stylesheet" href="passenger.css">
    <link rel="stylesheet" href="side.css">
    <style>
        .action-btn.disabled {
            opacity: .45;
            cursor: not-allowed;
            pointer-events: none;
            filter: grayscale(1)
        }

        .action-btn.current {
            box-shadow: 0 0 0 2px #111;
            font-weight: 700;
            cursor: not-allowed
        }

        .modal-status-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 20px;
            flex-wrap: wrap
        }

        .modal-status-actions .action-btn {
            border: 0
        }
    </style>
</head>

<body>
    <?php include "admin_header.php"; ?>
    <div class="content">
        <div class="page">
            <div class="page-header">
                <div>
                    <h1>Passenger Management</h1>
                    <p>Manage, verify and monitor all registered passengers.</p>
                </div>
            </div>
            <?php if ($message): ?>
                <div class="message"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
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
                    <div class="stat-title">Pending Passengers</div>
                    <div class="stat-value"><?= $pending_passengers ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-title">Rejected Passengers</div>
                    <div class="stat-value"><?= $rejected_passengers ?></div>
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
                        <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="verified" <?= $status === 'verified' ? 'selected' : '' ?>>Verified</option>
                        <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                    </select>
                    <button type="submit" class="filter-btn">Search</button>
                    <a href="passengers.php" class="clear-btn">Clear</a>
                </form>
            </div>
            <div class="passengers-box">
                <div class="table-header">
                    <h2>All Passengers</h2>
                    <span class="passenger-count"><?= $total_passengers ?> passenger(s)</span>
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
                                <?php foreach ($passengers as $p): ?>
                                    <?php $image = $p['profile_image'] ?: 'default.png'; ?>
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
                                        <td>
                                            <?php if ($p['verification_status'] === 'verified'): ?>
                                                <span class="badge verified">Verified</span>
                                            <?php elseif ($p['verification_status'] === 'rejected'): ?>
                                                <span class="badge rejected">Rejected</span>
                                            <?php else: ?>
                                                <span class="badge pending">Pending</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="badge booking-badge"><?= (int)$p['total_bookings'] ?> Booking(s)</span></td>
                                        <td><?= date("d M Y", strtotime($p['created_at'])) ?></td>
                                        <td>
                                            <div class="actions">
                                                <button type="button" class="action-btn view" onclick='openPassengerModal(<?= htmlspecialchars(json_encode($p), ENT_QUOTES, "UTF-8") ?>)'>View</button>
                                                <?php if ($p['verification_status'] === 'pending'): ?>
                                                    <form method="POST" class="inline-action" onsubmit="return confirmAction(this,'Approve this passenger?')">
                                                        <input type="hidden" name="action" value="verify">
                                                        <input type="hidden" name="id" value="<?= (int)$p['user_id'] ?>">
                                                        <button type="submit" class="action-btn approve">Approve</button>
                                                    </form>
                                                    <form method="POST" class="inline-action" onsubmit="return confirmAction(this,'Reject this passenger?')">
                                                        <input type="hidden" name="action" value="reject">
                                                        <input type="hidden" name="id" value="<?= (int)$p['user_id'] ?>">
                                                        <button type="submit" class="action-btn reject">Reject</button>
                                                    </form>
                                                <?php elseif ($p['verification_status'] === 'verified'): ?>
                                                    <form method="POST" class="inline-action" onsubmit="return confirmAction(this,'Approve this passenger?')">
                                                        <input type="hidden" name="action" value="verify">
                                                        <input type="hidden" name="id" value="<?= (int)$p['user_id'] ?>">
                                                        <button type="submit" class="action-btn approve">Approve</button>
                                                    </form>
                                                    <span class="action-btn reject current disabled">Reject</span>
                                                <?php elseif ($p['verification_status'] === 'rejected'): ?>
                                                    <span class="action-btn approve current disabled">Approve</span>
                                                    <form method="POST" class="inline-action" onsubmit="return confirmAction(this,'Reject this passenger?')">
                                                        <input type="hidden" name="action" value="reject">
                                                        <input type="hidden" name="id" value="<?= (int)$p['user_id'] ?>">
                                                        <button type="submit" class="action-btn reject">Reject</button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty">
                        <h3>No Passengers Found</h3>
                        <p>No passenger matches your current search or filter.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="modal" id="passengerModal" onclick="closeModalOutside(event)">
            <div class="modal-box">
                <div class="modal-header">
                    <h3>Passenger Details</h3>
                    <button type="button" class="close-modal" onclick="closePassengerModal()">×</button>
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
                    <div class="modal-status-actions">
                        <form method="POST" class="modal-action-form" id="modalPendingForm" onsubmit="return confirmAction(this,'Move this passenger to pending?')">
                            <input type="hidden" name="action" value="pending">
                            <input type="hidden" name="id" id="modalPendingId">
                            <button type="submit" id="modalPending" class="action-btn pending">Pending</button>
                        </form>
                        <form method="POST" class="modal-action-form" id="modalApproveForm" onsubmit="return confirmAction(this,'Approve this passenger?')">
                            <input type="hidden" name="action" value="verify">
                            <input type="hidden" name="id" id="modalApproveId">
                            <button type="submit" id="modalApprove" class="action-btn approve">Approve</button>
                        </form>
                        <form method="POST" class="modal-action-form" id="modalRejectForm" onsubmit="return confirmAction(this,'Reject this passenger?')">
                            <input type="hidden" name="action" value="reject">
                            <input type="hidden" name="id" id="modalRejectId">
                            <button type="submit" id="modalReject" class="action-btn reject">Reject</button>
                        </form>
                    </div>
                    <div class="modal-actions">
                        <a id="modalDetails" href="#" class="action-btn view">Details</a>
                        <a id="modalEdit" href="#" class="action-btn edit">Edit</a>
                        <form method="POST" class="inline-action" onsubmit="return confirmAction(this,'Are you sure you want to delete this passenger?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" id="modalDeleteId">
                            <button type="submit" class="action-btn delete">Delete</button>
                        </form>
                        <button type="button" class="action-btn close-btn" onclick="closePassengerModal()">Close</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
        let actionProcessing = false;

        function confirmAction(form, message) {
            if (actionProcessing || form.dataset.processing === "1") return false;
            if (!confirm(message)) return false;
            actionProcessing = true;
            form.dataset.processing = "1";
            const button = form.querySelector('button[type="submit"]');
            if (button) {
                button.disabled = true;
                button.style.pointerEvents = "none";
                button.style.opacity = ".5";
            }
            return true;
        }

        function openPassengerModal(p) {
            const modal = document.getElementById("passengerModal");
            const image = document.getElementById("modalImage");
            if (p.profile_image && p.profile_image.trim() && p.profile_image !== "default.png") {
                image.src = "../uploads/profile/" + p.profile_image.trim();
            } else {
                image.src = "../uploads/profile/default.png";
            }
            image.onerror = function() {
                this.onerror = null;
                this.src = "../images/default.png";
            };
            document.getElementById("modalName").textContent = p.name || "-";
            document.getElementById("modalEmail").textContent = p.email || "-";
            document.getElementById("modalId").textContent = p.user_id || "-";
            document.getElementById("modalPhone").textContent = p.phone || "-";
            document.getElementById("modalVerification").textContent = p.verification_status === "verified" ? "Verified" : p.verification_status === "rejected" ? "Rejected" : "Pending";
            document.getElementById("modalBookings").textContent = (p.total_bookings || 0) + " Booking(s)";
            if (p.created_at) {
                const d = new Date(p.created_at.replace(" ", "T"));
                document.getElementById("modalDate").textContent = !isNaN(d) ? d.toLocaleDateString("en-GB", {
                    day: "2-digit",
                    month: "short",
                    year: "numeric"
                }) : p.created_at;
            } else {
                document.getElementById("modalDate").textContent = "-";
            }
            const pending = document.getElementById("modalPending");
            const approve = document.getElementById("modalApprove");
            const reject = document.getElementById("modalReject");
            document.getElementById("modalPendingId").value = p.user_id;
            document.getElementById("modalApproveId").value = p.user_id;
            document.getElementById("modalRejectId").value = p.user_id;
            pending.className = "action-btn pending";
            approve.className = "action-btn approve";
            reject.className = "action-btn reject";
            pending.disabled = false;
            approve.disabled = false;
            reject.disabled = false;
            pending.textContent = "Pending";
            approve.textContent = "Approve";
            reject.textContent = "Reject";
            if (p.verification_status === "pending") {
                pending.classList.add("current", "disabled");
                pending.disabled = true;
            } else if (p.verification_status === "verified") {
                approve.classList.add("current", "disabled");
                approve.disabled = true;
            } else if (p.verification_status === "rejected") {
                reject.classList.add("current", "disabled");
                reject.disabled = true;
            }
            document.getElementById("modalDetails").href = "view_passenger.php?id=" + p.user_id;
            document.getElementById("modalEdit").href = "edit_passenger.php?id=" + p.user_id;
            modal.classList.add("active");
        }

        function closePassengerModal() {
            document.getElementById("passengerModal").classList.remove("active");
        }

        function closeModalOutside(e) {
            if (e.target === document.getElementById("passengerModal")) closePassengerModal();
        }
        document.addEventListener("keydown", function(e) {
            if (e.key === "Escape") closePassengerModal();
        });
    </script>
</body>

</html>