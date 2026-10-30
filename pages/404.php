<?php
declare(strict_types=1);
?>
<section class="empty-state">
    <p class="eyebrow">Error 404</p>
    <h1 class="empty-state__code">404</h1>
    <h2>This basket went missing.</h2>
    <p class="empty-state__text">
        The page you asked for is not on the route list. Happens to the best of us — like a lone sock.
    </p>
    <div class="btn-row">
        <a class="btn btn--primary" href="<?= e(url('dashboard')) ?>"><?= icon('grid', 16) ?><span>Back to dashboard</span></a>
        <a class="btn btn--ghost" href="<?= e(url('orders')) ?>">See orders</a>
    </div>
</section>
