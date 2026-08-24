<?php
$records_per_page = 10;
$current_page = max(1, (int)($_GET['page'] ?? 1));
$total_records = (int)($total_records ?? 0);
$total_pages = max(1, (int)ceil($total_records / $records_per_page));
if ($current_page > $total_pages) $current_page = $total_pages;
$offset = ($current_page - 1) * $records_per_page;
$search_value = trim($_GET['search'] ?? '');
?>
<style>
    .pagination {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 6px;
        margin: 25px 0 5px;
        flex-wrap: wrap
    }

    .pagination a {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 38px;
        height: 38px;
        padding: 0 12px;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        background: #fff;
        color: #334155;
        text-decoration: none;
        font-weight: bold;
        font-size: 14px
    }

    .pagination a:hover {
        background: #e2e8f0
    }

    .pagination a.active {
        background: #1560bd;
        color: #fff;
        border-color: #1560bd
    }

    .pagination a.disabled {
        color: #94a3b8;
        background: #f8fafc;
        pointer-events: none
    }

    .pagination-info {
        text-align: center;
        color: #64748b;
        font-size: 13px;
        margin-top: 10px
    }
</style>
<div class="pagination">
    <?php if ($current_page > 1): ?>
        <a href="?page=<?= $current_page - 1 ?><?= $search_value !== '' ? '&search=' . urlencode($search_value) : '' ?>"><i class="fa fa-angle-left"></i> Previous</a>
    <?php else: ?>
        <a class="disabled"><i class="fa fa-angle-left"></i> Previous</a>
    <?php endif; ?>
    <?php
    $start_page = max(1, $current_page - 2);
    $end_page = min($total_pages, $current_page + 2);
    if ($start_page > 1):
    ?>
        <a href="?page=1<?= $search_value !== '' ? '&search=' . urlencode($search_value) : '' ?>">1</a>
        <?php if ($start_page > 2): ?>
            <span>...</span>
        <?php endif; ?>
    <?php endif; ?>
    <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
        <a href="?page=<?= $i ?><?= $search_value !== '' ? '&search=' . urlencode($search_value) : '' ?>" class="<?= $i === $current_page ? 'active' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
    <?php if ($end_page < $total_pages): ?>
        <?php if ($end_page < $total_pages - 1): ?>
            <span>...</span>
        <?php endif; ?>
        <a href="?page=<?= $total_pages ?><?= $search_value !== '' ? '&search=' . urlencode($search_value) : '' ?>"><?= $total_pages ?></a>
    <?php endif; ?>
    <?php if ($current_page < $total_pages): ?>
        <a href="?page=<?= $current_page + 1 ?><?= $search_value !== '' ? '&search=' . urlencode($search_value) : '' ?>">Next <i class="fa fa-angle-right"></i></a>
    <?php else: ?>
        <a class="disabled">Next <i class="fa fa-angle-right"></i></a>
    <?php endif; ?>
</div>
<div class="pagination-info">
    Showing <?= $total_records > 0 ? $offset + 1 : 0 ?> - <?= min($offset + $records_per_page, $total_records) ?> of <?= $total_records ?> records
</div>