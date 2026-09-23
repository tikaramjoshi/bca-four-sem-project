 function showPost() {
            if (postIndex >= posts.length) {
                document.getElementById("postModal").style.display = "none";
                return;
            }

            const post = posts[postIndex];

            document.getElementById("postTitle").innerText = post.title;
            document.getElementById("postMessage").innerText = post.message;

            if (post.image) {
                document.getElementById("postImage").src = "uploads/posts/" + post.image;
                document.getElementById("postImage").style.display = "block";
            } else {
                document.getElementById("postImage").style.display = "none";
            }

            document.getElementById("nextPost").innerText = postIndex === posts.length - 1 ? "Close" : "Next";
            document.getElementById("postModal").style.display = "flex";
        }

        document.getElementById("nextPost").addEventListener("click", function() {
            postIndex++;
            if (postIndex >= posts.length) {
        fetch("posts_shown.php");
        document.getElementById("postModal").style.display = "none";
        return;
    }
            showPost();
        });

        document.getElementById("closePost").addEventListener("click", function() {
            document.getElementById("postModal").style.display = "none";
             fetch("posts_shown.php");
        });

        if (posts.length > 0 && showPostOnLogin) {
            showPost();
        }