<?php
// index-designer-main.php


include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_DESIGNER];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';


?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Graphic Design Dashboard</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/sidebar.php'; ?>
</head>

<body class="bg-slate-100">
   <main class="md:ml-56 min-h-screen px-3 pt-20 pb-6 sm:px-5 md:p-8">

        <?php include ROOT_PATH . '/admin/requestcentral/requests-view.php'; ?>

    </main>
</body>

</html>