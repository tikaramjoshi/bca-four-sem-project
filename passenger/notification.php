<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once  '../db.php';
if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'passenger'
) {
    header("Location: ../login.php");
    exit;
}
$user_id = (int) $_SESSION['user_id'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $notification_id = (int) ($_POST['notification_id'] ?? 0);
    if (isset($_POST['mark_all_read'])) {
        $stmt = $conn->prepare(
            "UPDATE notifications SET is_read = 1 WHERE user_id = ?"
        );
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
        header("Location: notification.php");
        exit;
    }
    if (isset($_POST['mark_read']) && $notification_id > 0) {
        $stmt = $conn->prepare(
            "UPDATE notifications
             SET is_read = 1
             WHERE notification_id = ? AND user_id = ?"
        );
        $stmt->bind_param("ii", $notification_id, $user_id);
        $stmt->execute();
        $stmt->close();
        header("Location: notification.php");
        exit;
    }
    if (isset($_POST['open_notification']) && $notification_id > 0) {
        $stmt = $conn->prepare(
            "SELECT link FROM notifications
             WHERE notification_id = ? AND user_id = ?"
        );
        $stmt->bind_param("ii", $notification_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $notification = $result->fetch_assoc();
        $stmt->close();
        if ($notification) {
            $stmt = $conn->prepare(
                "UPDATE notifications SET is_read = 1
                 WHERE notification_id = ? AND user_id = ?"
            );
            $stmt->bind_param("ii", $notification_id, $user_id);
            $stmt->execute();
            $stmt->close();
            $link = trim($notification['link'] ?? '');
            if (
                $link !== '' &&
                !preg_match('/^(https?:)?\/\//i', $link) &&
                strpos($link, '..') === false
            ) {
                header("Location: " . $link);
                exit;
            }
        }
        header("Location: notification.php");
        exit;
    }
}
$filter = $_GET['filter'] ?? 'all';
if (!in_array($filter, ['all', 'unread', 'read'], true)) {
    $filter = 'all';
}
$stmt = $conn->prepare(
    "SELECT COUNT(*) AS total
     FROM notifications
     WHERE user_id = ? AND is_read = 0"
);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$count_result = $stmt->get_result();
$count_row = $count_result->fetch_assoc();
$unread_count = (int) ($count_row['total'] ?? 0);
$stmt->close();
$sql = "SELECT notification_id, type, title, message, link, is_read, created_at
        FROM notifications
        WHERE user_id = ?";
if ($filter === 'unread') {
    $sql .= " AND is_read = 0";
} elseif ($filter === 'read') {
    $sql .= " AND is_read = 1";
}
$sql .= " ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$notifications = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Notifications</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="notification.css">
</head>

<body>
    <?php include "pass_header.php" ?>
    <div class="notification-container">
        <div class="notification-header">
            <div>
                <h2><i class="fas fa-bell"></i> My Notifications</h2>
                <div class="unread-total">
                    <?= $unread_count ?> unread notification(s)
                </div>
            </div>
            <?php if ($unread_count > 0): ?>
                <form method="POST">
                    <button type="submit" name="mark_all_read" class="mark-all-btn">
                        <i class="fas fa-check-double"></i> Mark All as Read
                    </button>
                </form>
            <?php endif; ?>
        </div>
        <div class="filter-tabs">
            <a href="notification.php?filter=all"
                class="<?= $filter === 'all' ? 'active' : '' ?>">All</a>
            <a href="notification.php?filter=unread"
                class="<?= $filter === 'unread' ? 'active' : '' ?>">Unread</a>
            <a href="notification.php?filter=read"
                class="<?= $filter === 'read' ? 'active' : '' ?>">Read</a>
        </div>
        <div class="notification-list">
            <?php if ($notifications->num_rows > 0): ?>
                <?php while ($row = $notifications->fetch_assoc()): ?>
                    <?php
                    $icons = [
                        'booking' => 'fa-ticket',
                        'approval' => 'fa-circle-check',
                        'system' => 'fa-circle-info',
                        'ticket' => 'fa-bus'
                    ];
                    $icon = $icons[$row['type']] ?? 'fa-bell';
                    ?>
                    <div class="notification-card <?= $row['is_read'] ? 'read' : '' ?>">
                        <div class="notification-icon">
                            <i class="fas <?= htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') ?>"></i>
                        </div>
                        <div class="notification-content">
                            <h3 class="notification-title">
                                <?= htmlspecialchars($row['title'], ENT_QUOTES, 'UTF-8') ?>
                                <?php if (!$row['is_read']): ?>
                                    <span class="unread-dot"></span>
                                <?php endif; ?>
                            </h3>
                            <p class="notification-message">
                                <?= nl2br(htmlspecialchars($row['message'], ENT_QUOTES, 'UTF-8')) ?>
                            </p>
                            <div class="notification-date">
                                <i class="far fa-clock"></i>
                                <?= date('d M Y, h:i A', strtotime($row['created_at'])) ?>
                            </div>
                        </div>
                        <div class="notification-actions">
                            <?php if (!empty($row['link'])): ?>
                                <form method="POST">
                                    <input type="hidden" name="notification_id"
                                        value="<?= (int) $row['notification_id'] ?>">
                                    <button type="submit" name="open_notification" class="action-btn">
                                        <i class="fas fa-arrow-up-right-from-square"></i> Open
                                    </button>
                                </form>
                            <?php endif; ?>
                            <?php if (!$row['is_read']): ?>
                                <form method="POST">
                                    <input type="hidden" name="notification_id"
                                        value="<?= (int) $row['notification_id'] ?>">
                                    <button type="submit" name="mark_read" class="action-btn">
                                        <i class="fas fa-check"></i> Read
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-notifications">
                    <i class="far fa-bell-slash"></i>
                    <h3>No notifications found</h3>
                    <p>You don't have any notifications in this section.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php include "pass_footer.php" ?>
</body>

</html>