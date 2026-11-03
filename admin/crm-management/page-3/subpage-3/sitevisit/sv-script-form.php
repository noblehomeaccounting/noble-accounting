<?php
// sitevisit/sv-script-form.php — JS ng form: file boxes, space list, Whole view, draft, validation, steps
// Kasama ng sitevisitform.php (include). Huwag buksan nang direkta.
?>
<script>
    // ═══════════════ NEW RECORD FORM ═══════════════
    (function () {
        const form = document.getElementById('svForm');
        if (!form) return;

        const fmtSize = b => b >= 1048576 ? (b / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB';
        const MAX_PDF = 15 * 1024 * 1024;

        <?php include ROOT_PATH . '/admin/crm-management/page-3/subpage-3/sitevisit/js/form-files.php'; ?>
        <?php include ROOT_PATH . '/admin/crm-management/page-3/subpage-3/sitevisit/js/form-spaces.php'; ?>
        <?php include ROOT_PATH . '/admin/crm-management/page-3/subpage-3/sitevisit/js/form-whole-view.php'; ?>
        <?php include ROOT_PATH . '/admin/crm-management/page-3/subpage-3/sitevisit/js/form-checks.php'; ?>
        <?php include ROOT_PATH . '/admin/crm-management/page-3/subpage-3/sitevisit/js/form-steps.php'; ?>
    })();
</script>