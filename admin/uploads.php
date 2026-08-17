<?php
session_start();
require_once "../db.php";

if (!isset($_SESSION['user_id'], $_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$uploadRoot = realpath(__DIR__ . "/../uploads");
$message = "";
$messageType = "";

function getFiles($dir, $root)
{
    $files = [];
    if (!is_dir($dir)) return $files;

    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === "." || $item === "..") continue;

        $path = $dir . DIRECTORY_SEPARATOR . $item;

        if (is_dir($path)) {
            $files = array_merge($files, getFiles($path, $root));
        } elseif (is_file($path)) {
            $files[] = [
                "name" => $item,
                "path" => $path,
                "relative" => str_replace("\\", "/", ltrim(str_replace($root, "", $path), "\\/")),
                "size" => filesize($path),
                "modified" => filemtime($path),
                "extension" => strtolower(pathinfo($item, PATHINFO_EXTENSION))
            ];
        }
    }

    return $files;
}

if (!$uploadRoot || !is_dir($uploadRoot)) {
    die("Uploads folder not found.");
}

if (isset($_GET['delete'])) {
    $relative = urldecode($_GET['delete']);
    $relative = str_replace("\\", "/", $relative);
    $relative = ltrim($relative, "/");

    $filePath = realpath($uploadRoot . DIRECTORY_SEPARATOR . $relative);

    if (
        $filePath &&
        is_file($filePath) &&
        strpos($filePath, $uploadRoot . DIRECTORY_SEPARATOR) === 0
    ) {
        if (unlink($filePath)) {
            header("Location: uploads.php?msg=deleted");
            exit;
        } else {
            $message = "Unable to delete the file.";
            $messageType = "error";
        }
    } else {
        $message = "Invalid file.";
        $messageType = "error";
    }
}

if (isset($_GET['msg']) && $_GET['msg'] === "deleted") {
    $message = "File deleted successfully.";
    $messageType = "success";
}

$files = getFiles($uploadRoot, $uploadRoot);

$search = trim($_GET['search'] ?? "");

if ($search !== "") {
    $files = array_filter($files, function ($file) use ($search) {
        return stripos($file["name"], $search) !== false ||
            stripos($file["relative"], $search) !== false;
    });
}

usort($files, function ($a, $b) {
    return $b["modified"] <=> $a["modified"];
});

$totalFiles = count($files);
$imageExtensions = ["jpg", "jpeg", "png", "gif", "webp", "bmp", "svg"];
$documentExtensions = ["pdf", "doc", "docx", "xls", "xlsx", "txt", "csv"];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
                    <h1>Uploaded Files</h1>
                    <p>View and manage all files uploaded to the system.</p>
                </div>

                <a href="dashboard.php" class="home-btn">Home</a>
            </div>

            <?php if ($message): ?>
                <div class="message <?= $messageType ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <div class="stats">
                <div class="stat-card">
                    <span>Total Files</span>
                    <strong><?= $totalFiles ?></strong>
                </div>

                <div class="stat-card">
                    <span>Images</span>
                    <strong>
                        <?= count(array_filter($files, function ($f) use ($imageExtensions) {
                            return in_array($f["extension"], $imageExtensions);
                        })) ?>
                    </strong>
                </div>

                <div class="stat-card">
                    <span>Documents</span>
                    <strong>
                        <?= count(array_filter($files, function ($f) use ($documentExtensions) {
                            return in_array($f["extension"], $documentExtensions);
                        })) ?>
                    </strong>
                </div>

                <div class="stat-card">
                    <span>Other Files</span>
                    <strong>
                        <?= count(array_filter($files, function ($f) use ($imageExtensions, $documentExtensions) {
                            return !in_array($f["extension"], $imageExtensions) &&
                                !in_array($f["extension"], $documentExtensions);
                        })) ?>
                    </strong>
                </div>
            </div>

            <div class="toolbar">
                <form method="GET" class="search-form">
                    <input
                        type="text"
                        name="search"
                        placeholder="Search file or folder..."
                        value="<?= htmlspecialchars($search) ?>">
                    <button type="submit">Search</button>

                    <?php if ($search !== ""): ?>
                        <a href="uploads.php" class="clear-btn">Clear</a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="files-box">

                <div class="box-header">
                    <div>
                        <h2>All Uploaded Files</h2>
                        <span><?= $totalFiles ?> file(s)</span>
                    </div>
                </div>

                <?php if ($files): ?>

                    <div class="file-grid">

                        <?php foreach ($files as $file): ?>

                            <?php
                            $ext = $file["extension"];
                            $relativeUrl = "../uploads/" . implode(
                                "/",
                                array_map("rawurlencode", explode("/", $file["relative"]))
                            );

                            $isImage = in_array($ext, $imageExtensions);
                            $isDocument = in_array($ext, $documentExtensions);

                            $size = $file["size"];

                            if ($size >= 1048576) {
                                $sizeText = number_format($size / 1048576, 2) . " MB";
                            } elseif ($size >= 1024) {
                                $sizeText = number_format($size / 1024, 2) . " KB";
                            } else {
                                $sizeText = $size . " B";
                            }
                            ?>

                            <div class="file-card">

                                <div class="preview">

                                    <?php if ($isImage): ?>

                                        <a href="<?= htmlspecialchars($relativeUrl) ?>" target="_blank">
                                            <img
                                                src="<?= htmlspecialchars($relativeUrl) ?>"
                                                alt="<?= htmlspecialchars($file["name"]) ?>"
                                                onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                                        </a>

                                        <div class="file-icon fallback">
                                            IMG
                                        </div>

                                    <?php elseif ($isDocument): ?>

                                        <div class="file-icon document">
                                            <?= strtoupper($ext ?: "FILE") ?>
                                        </div>

                                    <?php else: ?>

                                        <div class="file-icon other">
                                            FILE
                                        </div>

                                    <?php endif; ?>

                                </div>

                                <div class="file-info">

                                    <h3 title="<?= htmlspecialchars($file["name"]) ?>">
                                        <?= htmlspecialchars($file["name"]) ?>
                                    </h3>

                                    <p class="file-path">
                                        <?= htmlspecialchars($file["relative"]) ?>
                                    </p>

                                    <div class="file-meta">
                                        <span><?= strtoupper($ext ?: "FILE") ?></span>
                                        <span><?= $sizeText ?></span>
                                    </div>

                                    <div class="file-actions">

                                        <a
                                            href="<?= htmlspecialchars($relativeUrl) ?>"
                                            target="_blank"
                                            class="view-btn">
                                            View
                                        </a>

                                        <a
                                            href="<?= htmlspecialchars($relativeUrl) ?>"
                                            download
                                            class="download-btn">
                                            Download
                                        </a>

                                        <a
                                            href="uploads.php?delete=<?= rawurlencode($file["relative"]) ?>"
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
                        <div class="empty-icon">📁</div>
                        <h3>No Files Found</h3>
                        <p>
                            <?= $search !== ""
                                ? "No uploaded file matches your search."
                                : "There are no files inside the uploads folder." ?>
                        </p>
                    </div>

                <?php endif; ?>

            </div>

        </div>
    </div>
</body>

</html>