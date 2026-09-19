<?php
// index-it-main.php

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_ACCOUNTING, ROLE_SUPERADMIN];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';


?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IT Dashboard</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/sidebar.php'; ?>
</head>

<body class="bg-slate-100">

    <main id="main-content" class="md:ml-56 pt-20 md:pt-5 min-h-screen p-4 md:p-8 transition-all duration-300">


        <?php include ROOT_PATH . '/admin/navigation/settings/signatureadd.php'; ?>

    </main>
    
</body>

</html>