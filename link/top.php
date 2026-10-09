<?php
// top.php
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}

// Custom cursor, naka-embed sa mismong HTML para hindi na hinihintay ang image file sa bawat page
$cursorFile = ROOT_PATH . '/icon/cur4.png';
$cursorUri  = is_file($cursorFile)
    ? 'data:image/png;base64,' . base64_encode(file_get_contents($cursorFile))
    : '';
?>

<?php if ($cursorUri !== ''): ?>
<style>
    *,
    *::before,
    *::after {
        cursor: url('<?= $cursorUri ?>') 0 0, auto !important;
    }
</style>
<?php endif; ?>

<!-- link this page to cdn-->
<script src="https://cdn.tailwindcss.com"></script>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet" />

<link rel="icon" type="image/png" href="<?= BASE_URL ?>/icon/logo.png">

<style>
    * {
        font-family: 'Plus Jakarta Sans', sans-serif;
    }
</style>