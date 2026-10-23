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
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #d7dbe2d6;
            color: #263238;
        }

        .notification-container {
            margin: 30px 50px;
            padding: 30px;
            background-color: #edefe6ec;
            min-height: 75vh;
            border-radius: 15px;
        }

        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 25px;
        }

        .notification-header h2 {
            margin: 0;
            color: #1560bd;
            font-size: 27px;
        }

        .unread-total {
            margin-top: 8px;
            color: #666;
            font-size: 14px;
        }

        .mark-all-btn,
        .action-btn {
            border: none;
            background: #1560bd;
            color: white;
            padding: 10px 14px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
        }

        .mark-all-btn:hover,
        .action-btn:hover {
            background: #104b94;
        }

        .filter-tabs {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .filter-tabs a {
            text-decoration: none;
            color: #444;
            background: white;
            border: 1px solid #cfc8c8d9;
            padding: 10px 18px;
            border-radius: 6px;
            font-size: 14px;
        }

        .filter-tabs a.active {
            background: #1560bd;
            border-color: #1560bd;
            color: white;
        }

        .notification-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .notification-card {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            background: white;
            border: 1px solid #bac2ce;
            border-left: 5px solid #1560bd;
            border-radius: 8px;
            padding: 18px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
        }

        .notification-card.read {
            border-left-color: #c6ccd5;
        }

        .notification-icon {
            width: 43px;
            height: 43px;
            min-width: 43px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: #bcc0c6;
            color: #1560bd;
            font-size: 18px;
        }

        .notification-content {
            flex: 1;
            min-width: 0;
        }

        .notification-title {
            margin: 0 0 7px;
            color: #222;
            font-size: 16px;
        }

        .notification-message {
            margin: 0 0 10px;
            color: #5d6673;
            font-size: 14px;
            line-height: 1.6;
            overflow-wrap: anywhere;
        }

        .notification-date {
            color: #89919c;
            font-size: 12px;
        }

        .unread-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            margin-left: 7px;
            background: #1560bd;
            border-radius: 50%;
        }

        .notification-actions {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

        .empty-notifications {
            background: #f4f1f1f0;
            padding: 55px 20px;
            text-align: center;
            border: 1px solid #e0e5ec;
            border-radius: 8px;
            color: #777;
        }

        .empty-notifications i {
            display: block;
            font-size: 42px;
            margin-bottom: 15px;
            color: #aab4c2;
        }

        @media (max-width: 600px) {
            .notification-container {
                margin: 15px auto;
                padding: 12px;
            }

            .notification-card {
                padding: 13px;
                gap: 10px;
                flex-wrap: wrap;
            }

            .notification-icon {
                width: 35px;
                height: 35px;
                min-width: 35px;
                font-size: 15px;
            }

            .notification-header h2 {
                font-size: 23px;
            }

            .notification-actions {
                width: 100%;
                padding-left: 45px;
            }
        }
    </style>
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