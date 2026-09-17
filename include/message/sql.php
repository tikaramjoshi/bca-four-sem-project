<?php $posts = [];
$result = $conn->query("SELECT * FROM posts WHERE status='active' ORDER BY post_id DESC");
while ($result && $row = $result->fetch_assoc()) {
    $posts[] = $row;
}
