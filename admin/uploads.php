<?php
session_start();
require_once "../db.php";
if (!isset($_SESSION['user_id'], $_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}
$root = realpath(__DIR__ . "/../uploads");
if (!$root || !is_dir($root)) die("Uploads folder not found.");
$msg = $_GET['msg'] ?? '';
$message = $msg === 'deleted' ? 'File deleted successfully.' : '';
$type = $message ? 'success' : '';
function files($dir, $root)
{
    $list = [];
    if (!is_dir($dir)) return $list;
    foreach (scandir($dir) as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = "$dir/$item";
        if (is_dir($path)) $list = array_merge($list, files($path, $root));
        elseif (is_file($path)) $list[] = [
            'name' => $item,
            'path' => $path,
            'relative' => str_replace('\\', '/', ltrim(str_replace($root, '', $path), '\\/')),
            'size' => filesize($path),
            'modified' => filemtime($path),
            'extension' => strtolower(pathinfo($item, PATHINFO_EXTENSION))
        ];
    }
    return $list;
}
if (isset($_GET['delete'])) {
    $relative = ltrim(str_replace('\\', '/', urldecode($_GET['delete'])), '/');
    $path = realpath("$root/$relative");
    if ($path && is_file($path) && strpos($path, $root . DIRECTORY_SEPARATOR) === 0) {
        if (unlink($path)) {
            header("Location: uploads.php?msg=deleted");
            exit;
        }
        $message = "Unable to delete the file.";
        $type = "error";
    } else {
        $message = "Invalid file.";
        $type = "error";
    }
}
$files = files($root, $root);
$search = trim($_GET['search'] ?? '');
if ($search) {
    $files = array_filter(
        $files,
        fn($f) =>
        stripos($f['name'], $search) !== false ||
            stripos($f['relative'], $search) !== false
    );
}
usort($files, fn($a, $b) => $b['modified'] <=> $a['modified']);
$images = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'];
$docs = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'csv'];
$imageCount = count(array_filter($files, fn($f) => in_array($f['extension'], $images)));
$docCount = count(array_filter($files, fn($f) => in_array($f['extension'], $docs)));
$otherCount = count($files) - $imageCount - $docCount;
$total = count($files);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Uploaded Files</title>
    <link rel="stylesheet" href="uploads.css">
    <link rel="stylesheet" href="side.css">
</head>

<body>
    <?php include "admin_header.php"; ?>
    <div class="content">
        <div class="page">
            <div class="page-header">
                <div>
                    <h1>Users Uploaded Files</h1>
                    <p>View and manage all files uploaded to the system.</p>
                </div>
            </div>
            <?php if ($message): ?>
                <div class="message <?= $type ?>"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
            <div class="stats">
                <div class="stat-card">
                    <span>Total Files</span>
                    <strong><i class="fa fa-folder"></i> &nbsp; &nbsp; <?= $total ?></strong>
                </div>
                <div class="stat-card">
                    <span>Images</span>
                    <strong><i class="fa fa-image"></i> &nbsp; &nbsp; <?= $imageCount ?></strong>
                </div>
                <div class="stat-card">
                    <span>Documents</span>
                    <strong><i class="fa fa-file-text"></i> &nbsp; &nbsp; <?= $docCount ?></strong>
                </div>
                <div class="stat-card">
                    <span>Other Files</span>
                    <strong><i class="fa fa-file"></i> &nbsp; &nbsp;<?= $otherCount ?></strong>
                </div>
            </div>
            <div class="toolbar">
                <form method="GET" class="search-form">
                    <input type="text" name="search" placeholder="Search file..." value="<?= htmlspecialchars($search) ?>">
                    <button type="submit"><i class="fa fa-search"></i> Search</button>
                    <?php if ($search): ?>
                        <a href="uploads.php" class="clear-btn">Clear</a>
                    <?php endif; ?>
                </form>
            </div>
            <div class="files-box">
                <div class="box-header">
                    <div>
                        <h2>All Uploaded Files</h2>
                        <span><?= $total ?> file(s)</span>
                    </div>
                </div>
                <?php if ($files): ?>
                    <div class="file-grid">
                        <?php foreach ($files as $file):
                            $ext = $file['extension'];
                            $url = "../uploads/" . implode("/", array_map("rawurlencode", explode("/", $file['relative'])));
                            $image = in_array($ext, $images);
                            $document = in_array($ext, $docs);
                            $size = $file['size'] >= 1048576
                                ? number_format($file['size'] / 1048576, 2) . " MB"
                                : ($file['size'] >= 1024
                                    ? number_format($file['size'] / 1024, 2) . " KB"
                                    : $file['size'] . " B");
                        ?>
                            <div class="file-card">
                                <div class="preview">
                                    <?php if ($image): ?>
                                        <a href="<?= htmlspecialchars($url) ?>" target="_blank">
                                            <img src="<?= htmlspecialchars($url) ?>" alt="<?= htmlspecialchars($file['name']) ?>">
                                        </a>
                                    <?php elseif ($document): ?>
                                        <div class="file-icon document"><?= strtoupper($ext ?: 'FILE') ?></div>
                                    <?php else: ?>
                                        <div class="file-icon other">FILE</div>
                                    <?php endif; ?>
                                </div>
                                <div class="file-info">
                                    <h3 title="<?= htmlspecialchars($file['name']) ?>">
                                        <?= htmlspecialchars($file['name']) ?>
                                    </h3>
                                    <p class="file-path"><?= htmlspecialchars($file['relative']) ?></p>
                                    <div class="file-meta">
                                        <span><?= strtoupper($ext ?: 'FILE') ?></span>
                                        <span><?= $size ?></span>
                                    </div>
                                    <div class="file-actions">
                                        <a href="<?= htmlspecialchars($url) ?>" target="_blank" class="view-btn">View</a>
                                        <a href="<?= htmlspecialchars($url) ?>" download class="download-btn">Download</a>
                                        <a href="uploads.php?delete=<?= rawurlencode($file['relative']) ?>"
                                            class="delete-btn"
                                            onclick="return confirm('Are you sure you want to delete this file?')">
                                            Delete
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty">
                        <div class="empty-icon"><i class="fa fa-folder-open"></i></div>
                        <h3>No Files Found</h3>
                        <p><?= $search ? 'No uploaded file matches your search.' : 'There are no files inside the uploads folder.' ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>

</html>