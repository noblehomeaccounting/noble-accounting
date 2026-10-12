<?php
// index-logout.php
session_destroy();
?>
<!DOCTYPE html>
<html>
<head></head>
<body>
<script>
    sessionStorage.clear();
    window.location.href = '<?= BASE_URL ?>/adminhome';
</script>
</body>
</html>