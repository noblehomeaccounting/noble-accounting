<?php
// routes/user-routes.php

defined('ROOT_PATH') || exit;   // index.php lang ang puwedeng mag-load nito

return [
    // login, home & api
    'apilogin'                              => 'api/login.php',
    'apinoblerole'                          => 'api/noblerole.php',
    'home'                                  => 'animation.php',
    'home1'                                 => 'user/authentication/index-login.php',
    'loginuser'                             => 'user/authentication/index-login.php',
    'callback'                              => 'user/authentication/index-callback.php',
    'userhome'                              => 'user/ui/index-main-page-1.php',
    'register'                              => 'user/authentication/index-register.php',
    'verify'                                => 'user/authentication/index-verify.php',
    'logoutuser'                            => 'user/authentication/index-logout.php',
    'fetchannouncements'                    => 'user/navigation/backend/backend-announcement/fetchannouncements.php',
    'userprofile'                           => 'user/ui/index-userprofile.php',

    //user
    'myvouchers'                            => 'user/ui/index-my-vouchers.php',
    'followupattachments'                   => 'user/ui/index-followup-attachments.php',
    'fetchmyvouchers'                       => 'user/ui/backend/index-fetch-my-vouchers.php',
    'acceptvoucher'                         => 'user/ui/backend/index-accept-voucher.php',
    'fetchheads'                            => 'user/ui/backend/index-fetch-heads.php',
    'submitrequest'                         => 'user/ui/backend/index-submit-request.php',
    'fetchmyrequests'                       => 'user/ui/backend/index-fetch-my-requests.php',
    'uploadfollowup'                        => 'user/ui/backend/index-followup-upload.php',
    'requesthistory'                        => 'user/ui/index-my-request-history.php',
    'dashboard'                             => 'user/ui/index-main-dashboard.php',
    'rejectedrequests'                      => 'user/ui/index-rejected-requests.php',
    'fetchrejectedrequests'                 => 'user/ui/backend/fetch-rejected-requests.php',
    'resubmitrequest'                       => 'user/ui/backend/index-resubmit-request.php',
    'pushsubscribeuser'                     => 'user/navigation/push-subscribe-user.php',

    //user
    'fetchnotificationsuser'                => 'user/navigation/backend/backend-notification/fetchnotifications.php',
    'readnotificationuser'                  => 'user/navigation/backend/backend-notification/readnotification.php',
    'notificationsuser'                     => 'user/ui/index-notifications.php',
    'memouser'                              => 'user/profile/memouser.php',
    'memouserajax'                          => 'user/profile/backend/memouserajax.php',
    'contractsign'                          => 'admin/crm-management/contract/contract-sign.php',
    'contractsignajax'                      => 'admin/crm-management/contract/backend/contractsignajax.php',
];