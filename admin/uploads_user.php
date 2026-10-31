<?php
session_start();
require_once "../db.php";
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../login.php");
    exit;
}
$uploadDir = realpath(__DIR__ . "/../uploads");
function e(string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
function userName(array $user): string
{
    foreach (['name', 'full_name', 'username'] as $field) {
        if (!empty($user[$field])) return $user[$field];
    }
    return trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: 'Unknown User';
}
function scanFiles(string $folder): array
{
    global $uploadDir;
    $files = [];
    if (!$uploadDir) return $files;
    $dir = realpath($uploadDir . DIRECTORY_SEPARATOR . $folder);
    if (!$dir || strpos($dir, $uploadDir . DIRECTORY_SEPARATOR) !== 0) return $files;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if ($file->isFile() && !$file->isLink()) {
            $files[] = str_replace('\\', '/', substr($file->getPathname(), strlen($uploadDir) + 1));
        }
    }
    return $files;
}
function getUserFiles(int $userId): array
{
    global $conn, $uploadDir;
    $files = [];
    if (!$uploadDir) return $files;
    $files = scanFiles("profile/" . $userId);
    foreach (['driver/license', 'driver/profile'] as $folder) {
        foreach (scanFiles($folder) as $file) {
            $relative = substr($file, strlen($folder) + 1);
            $filename = basename($file);
            if (
                in_array((string)$userId, explode('/', $relative)) ||
                preg_match('/^' . preg_quote((string)$userId, '/') . '([_.-])/', $filename)
            ) {
                $files[] = $file;
            }
        }
    }
    $stmt = $conn->prepare("SELECT bus_image FROM bus WHERE owner_id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $image = str_replace('\\', '/', trim($row['bus_image'] ?? ''));
            if (!$image) continue;
            $image = preg_replace('#^(\.\./|uploads/)+#', '', $image);
            foreach (array_unique([$image, 'bus/' . basename($image)]) as $candidate) {
                $path = realpath($uploadDir . DIRECTORY_SEPARATOR . $candidate);
                if (
                    $path &&
                    strpos($path, $uploadDir . DIRECTORY_SEPARATOR) === 0 &&
                    is_file($path) &&
                    !is_link($path)
                ) {
                    $files[] = str_replace('\\', '/', substr($path, strlen($uploadDir) + 1));
                    break;
                }
            }
        }
        $stmt->close();
    }
    return array_values(array_unique($files));
}
function isImageFile(string $file): bool
{
    return in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), [
        'jpg',
        'jpeg',
        'png',
        'gif',
        'webp',
        'bmp'
    ]);
}
function fileIcon(string $file): string
{
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'])) {
        return '<i class="fa-regular fa-image"></i>';
    }
    if ($ext === 'pdf') {
        return '<i class="fa-solid fa-file-pdf"></i>';
    }
    if (in_array($ext, ['doc', 'docx'])) {
        return '<i class="fa-solid fa-file-word"></i>';
    }
    if (in_array($ext, ['xls', 'xlsx', 'csv'])) {
        return '<i class="fa-solid fa-file-excel"></i>';
    }
    return '<i class="fa-solid fa-file"></i>';
}
if (isset($_GET['action'], $_GET['file'])) {
    $action = $_GET['action'];
    $relative = str_replace('\\', '/', $_GET['file']);
    if (!in_array($action, ['view', 'download']) || !$uploadDir) {
        http_response_code(400);
        exit("Invalid request.");
    }
    $path = realpath($uploadDir . DIRECTORY_SEPARATOR . $relative);
    if (
        !$path ||
        strpos($path, $uploadDir . DIRECTORY_SEPARATOR) !== 0 ||
        !is_file($path) ||
        is_link($path)
    ) {
        http_response_code(404);
        exit("File not found.");
    }
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if (in_array($ext, ['php', 'phtml', 'phar', 'php5', 'html', 'htm', 'js', 'exe', 'sh', 'svg'])) {
        http_response_code(403);
        exit("File type not allowed.");
    }
    $mime = mime_content_type($path) ?: 'application/octet-stream';
    $inline = $action === 'view' && (
        strpos($mime, 'image/') === 0 || $mime === 'application/pdf'
    );
    header("Content-Type: " . ($inline ? $mime : 'application/octet-stream'));
    header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . basename($path) . '"');
    header("X-Content-Type-Options: nosniff");
    header("Content-Length: " . filesize($path));
    readfile($path);
    exit;
}
$users = [];

$result = $conn->query("SELECT * FROM users ORDER BY user_id DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}
$search = trim($_GET['search'] ?? '');
if (isset($_GET['search']) && $search === '') {
    header("Location: uploads_user.php");
    exit;
}
if ($search !== '') {
    $users = array_filter($users, function ($user) use ($search) {
        return stripos((string)$user['user_id'], $search) !== false ||
            stripos(userName($user), $search) !== false ||
            stripos((string)($user['email'] ?? ''), $search) !== false ||
            stripos((string)($user['role'] ?? ''), $search) !== false;
    });
}

$selectedUser = null;
$userFiles = [];
if (isset($_GET['user_id'])) {
    $id = (int)$_GET['user_id'];
    $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $selectedUser = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($selectedUser) {
        $userFiles = getUserFiles($id);
    }
}
$category = $_GET['category'] ?? '';
$categoryFiles = in_array($category, ['bus', 'posts']) ? scanFiles($category) : [];
$fields = [
    'user_id' => 'User ID',
    'name' => 'Name',
    'full_name' => 'Full Name',
    'username' => 'Username',
    'email' => 'Email',
    'phone' => 'Phone',
    'role' => 'Role',
    'verification_status' => 'Verification Status',
    'created_at' => 'Registered Date'
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Uploaded Files</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="uploads_user.css">

</head>

<body>
    <?php include "admin_header.php" ?>
    <div class="container">
        <?php if ($selectedUser): ?>
            <a href="uploads_user.php" class="back"> <i class="fa-solid fa-arrow-left"></i> Back to Users </a>
            <h2>User Uploaded Files</h2>
            <div class="info-box">
                <h3>User Information</h3>
                <div class="info-grid">
                    <?php foreach ($fields as $field => $label): ?>
                        <?php if (isset($selectedUser[$field]) && $selectedUser[$field] !== ''): ?>
                            <div class="info-item"> <strong><?= e($label) ?></strong> <?= $field === 'created_at' ? date('d M Y', strtotime($selectedUser[$field])) : e($selectedUser[$field]) ?> </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="files-box">
                <h3>Images & Documents (<?= count($userFiles) ?>)</h3>
                <?php if (!$userFiles): ?>
                    <p class="empty">No uploaded files found for this user.</p>
                <?php else: ?>
                    <div class="file-list">
                        <?php foreach ($userFiles as $file): ?>
                            <?php $query = http_build_query(['file' => $file]); ?>
                            <div class="file-card">
                                <?php if (isImageFile($file)): ?>
                                    <a href="?action=view&<?= e($query) ?>" target="_blank"> <img class="preview" src="?action=view&<?= e($query) ?>" alt="Uploaded Image"> </a>
                                <?php else: ?>
                                    <div class="file-icon"><?= fileIcon($file) ?></div>
                                <?php endif; ?>
                                <div class="file-name"><?= e(basename($file)) ?></div>
                                <div class="file-actions">
                                    <a class="view-btn" href="?action=view&<?= e($query) ?>" target="_blank"> <i class="fa-solid fa-eye"></i> View </a>
                                    <a class="download-btn" href="?action=download&<?= e($query) ?>"> <i class="fa-solid fa-download"></i> Download </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php elseif ($category): ?>
            <a href="uploads_user.php" class="back"> <i class="fa-solid fa-arrow-left"></i> Back to Users </a>
            <h2><?= e(ucfirst($category)) ?> Files (<?= count($categoryFiles) ?>)</h2>
            <?php if (!$categoryFiles): ?>
                <p class="empty">No files found.</p>
            <?php else: ?>
                <div class="file-list">
                    <?php foreach ($categoryFiles as $file): ?>
                        <?php $query = http_build_query(['file' => $file]); ?>
                        <div class="file-card">
                            <?php if (isImageFile($file)): ?>
                                <a href="?action=view&<?= e($query) ?>" target="_blank"><img class="preview" src="?action=view&<?= e($query) ?>" alt="Uploaded Image"> </a>
                            <?php else: ?>
                                <div class="file-icon"><?= fileIcon($file) ?></div>
                            <?php endif; ?>
                            <div class="file-name"><?= e(basename($file)) ?></div>
                            <div class="file-actions">
                                <a class="view-btn" href="?action=view&<?= e($query) ?>" target="_blank"> <i class="fa-solid fa-eye"></i> View </a>
                                <a class="download-btn" href="?action=download&<?= e($query) ?>"><i class="fa-solid fa-download"></i> Download </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <form method="GET" class="top-search">
                <input type="text" name="search" placeholder="Search by ID, Name, Email or Role..." value="<?= e($search) ?>" required>
                <button type="submit"><i class="fa-solid fa-search"></i> Search</button>
                <a href="uploads_user.php"><i class="fa fa-close"></i> Clear</a>
                <a href="uploads.php"> <i class="fa-solid fa-arrow-left"></i> Back to Users </a>
            </form>
            <h2>Uploaded Bus and Post Files</h2>
            <div class="category-links">

                <a href="?category=bus"> <i class="fa-solid fa-bus"></i> Bus Uploads </a>
                <a href="?category=posts"> <i class="fa-solid fa-images"></i> Post Uploads </a>
            </div><br>
            <h2>Uploaded User Files</h2><br>
            <div class="user-grid">
                <?php foreach ($users as $user): ?>
                    <?php
                    $id = (int)$user['user_id'];
                    $files = getUserFiles($id);
                    ?>
                    <a class="user-folder" href="?user_id=<?= $id ?>">
                        <div class="folder-icon"> <i class="fa-solid fa-folder"></i> &nbsp; ID: <?= $id ?></div>
                        <!-- <h3><?= e(userName($user)) ?></h3> -->
                        <p><?= count($files) ?> Files</p>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>