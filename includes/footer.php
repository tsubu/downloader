    <footer class="site-footer">
        <p class="site-footer__copy"><?= h(__('footer_copyright', ['year' => date('Y')])) ?></p>
    </footer>
</div>
<script type="application/json" id="app-i18n-data"><?= json_encode(js_translations(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="<?= h(app_base_url()) ?>/assets/js/app.js"></script>
</body>
</html>
