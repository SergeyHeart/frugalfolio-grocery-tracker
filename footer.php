<?php // footer.php ?>

            <?php // Page specific content ends before this line ?>
        </div> <?php // <!-- END: .content-below-topbar --> ?>

    </div> <?php // <!-- END: .main-content --> ?>

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

</div> <?php // <!-- END: .app-container --> ?>

    <?php // --- App Specific Scripts --- ?>
    <?php
        if (isset($pageScripts) && is_array($pageScripts)) {
            foreach ($pageScripts as $script) {
                 $jsPath = '/Frugalfolio' . (strpos($script, '/') === 0 ? '' : '/') . $script;
                echo '<script src="' . htmlspecialchars($jsPath) . '?v=' . time() . '"></script>' . "\n";
            }
        }
    ?>
    <script src="/Frugalfolio/js/app_layout.js?v=<?= time() ?>"></script>

</body>
</html>