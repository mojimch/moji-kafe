<?php

declare(strict_types=1);
?>
</main>
<footer class="site-footer mt-5">
    <div class="container py-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
        <div>
            <strong><?= sanitize(APP_NAME) ?></strong>
            <p class="small text-white-50 mb-0">Sajian favorit, suasana hangat.</p>
        </div>
        <p class="small text-white-50 mb-0">&copy; <?= date('Y') ?> <?= sanitize(APP_NAME) ?></p>
    </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= sanitize(BASE_URL) ?>/assets/js/app.js"></script>
</body>
</html>
