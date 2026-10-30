<?php
declare(strict_types=1);

$layout = $layout ?? 'app';
?>
<?php if ($layout === 'app'): ?>
        </main>

        <footer class="page-footer">
            <span><?= e(setting('business_name', (string) config('name'))) ?> — laundry control room</span>
            <span class="page-footer__meta">
                <?= e(db_is_mysql() ? 'MySQL' : 'SQLite') ?> ·
                <?= e(date('D d M Y')) ?> ·
                <?= e(auth_user()['name'] ?? '') ?>
            </span>
        </footer>
    </div>
</div>
<div class="scrim" data-menu-toggle hidden></div>
<?php elseif ($layout === 'auth'): ?>
    </main>
<?php else: ?>
    </main>
<?php endif; ?>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
