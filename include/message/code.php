<div id="postModal" class="post-modal">
    <div class="post-box">
        <span id="closePost">&times;</span>
        <img id="postImage" src="" alt="Post Image">
        <h2 id="postTitle"></h2>
        <p id="postMessage"></p>
        <button id="nextPost">Next</button>
    </div>
</div>
<script>
    const showPostsOnLogin = <?= empty($_SESSION['posts_shown']) ? 'true' : 'false' ?>;
</script>