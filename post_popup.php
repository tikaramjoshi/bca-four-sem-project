<?php
if (!isset($_SESSION['post_popup_shown'])) {
    $_SESSION['post_popup_shown'] = true;
    $show_post_popup = true;
} else {
    $show_post_popup = false;
}
$posts = [];
$result = $conn->query("SELECT * FROM posts WHERE status='active' ORDER BY post_id DESC");
while ($result && $row = $result->fetch_assoc()) {
    $posts[] = $row;
}
?>

<style>
    .post-modal {
        display: none;
        position: fixed;
        z-index: 9999;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, .7);
        justify-content: center;
        align-items: center;
    }

    .post-box {
        position: relative;
        width: 450px;
        max-width: 90%;
        background: white;
        padding: 20px;
        border-radius: 10px;
        text-align: center;
    }

    .post-box img {
        width: 100%;
        max-height: 250px;
        object-fit: cover;
        border-radius: 8px;
    }

    .post-box h2 {
        margin: 15px 0 8px;
    }

    .post-box p {
        margin-bottom: 20px;
    }

    .post-box button {
        padding: 10px 25px;
        border: 0;
        border-radius: 5px;
        cursor: pointer;
    }

    #closePost {
        position: absolute;
        right: 15px;
        top: 5px;
        font-size: 30px;
        cursor: pointer;
    }
</style>

<div id="postModal" class="post-modal">
    <div class="post-box">
        <span id="closePost">&times;</span>
        <img id="postImage" src="" alt="Post Image">
        <h2 id="postTitle"></h2>
        <p id="postMessage"></p>
        <button id="nextPost" style="background-color: red; color: #fff;">Next</button>
    </div>
</div>
<script>
    const posts = <?= json_encode($posts) ?>;
    let postIndex = 0;

    function showPost() {
        if (postIndex >= posts.length) {
            document.getElementById("postModal").style.display = "none";
            return;
        }
        const post = posts[postIndex];
        document.getElementById("postTitle").innerText = post.title;
        document.getElementById("postMessage").innerText = post.message;
        if (post.image) {
            document.getElementById("postImage").src = "../uploads/posts/" + post.image;
            document.getElementById("postImage").style.display = "block";
        } else {
            document.getElementById("postImage").style.display = "none";
        }
        document.getElementById("nextPost").innerText =
            postIndex === posts.length - 1 ? "Close" : "Next";
        document.getElementById("postModal").style.display = "flex";
    }
    document.getElementById("nextPost").onclick = function() {
        postIndex++;
        showPost();
    };
    document.getElementById("closePost").onclick = function() {
        document.getElementById("postModal").style.display = "none";
    };
    <?php if ($show_post_popup && !empty($posts)) { ?>
        showPost();
    <?php } ?>
</script>