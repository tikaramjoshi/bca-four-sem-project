<style>
    .pagination-btn-container button {
        background-color: orange;
        height: 50px;
        width: 60px;
        margin: 0px 20px;
        text-align: center;
        align-items: center;
        justify-content: center;

    }

    .pagination-btn-container {
        display: flex;
        gap: 10px;

        a {
            padding: 10px 16px;
            background-color: orange;
        }
    }

    .pagination-btn {
        justify-content: center;
        align-items: center;
        display: flex;
        gap: 10px;
    }
</style>
<?php


function PaginationBtn($props)
{

    $limit = $props['limit'];
    $len = $props['noOfData'];
    $noOfBtn = ceil($len / $limit);
?>
    <div class="pagination-btn-container">
        <a> Previous <?php echo $len ?></a>
        <div class="pagination-btn">
            <?php
            for ($i = 1; $i <= $noOfBtn; $i++) {
                $offset = $i * $limit - $limit;
            ?>
                <a href="?offset=<?php echo $offset ?>"><?php echo $i ?></a>

            <?php
            }
            ?>
        </div>
        <a>Next</button><br>
    </div>

<?php
}


?>