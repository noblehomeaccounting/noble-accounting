<?php
// index.php
define('ROOT_PATH', __DIR__);

require_once ROOT_PATH . '/vendor/autoload.php';  

// ─── Load .env ────────────────────────────────────────────────────────────────
$envFile = ROOT_PATH . '/.env';

if (!file_exists($envFile)) {
    die('.env file not found.');
}

foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#'))
        continue;  // skip comments/blanks
    if (!str_contains($line, '='))
        continue;                    // skip invalid lines

    [$key, $value] = explode('=', $line, 2);
    $_ENV[trim($key)] = trim($value);
}

// ─── Constants ────────────────────────────────────────────────────────────────
define('GOOGLE_CLIENT_ID', $_ENV['GOOGLE_CLIENT_ID'] ?? '');
define('GOOGLE_CLIENT_SECRET', $_ENV['GOOGLE_CLIENT_SECRET'] ?? '');

// ─── Base URL ─────────────────────────────────────────────────────────────────
$isLocalhost = (
    strpos($_SERVER['HTTP_HOST'], 'localhost') !== false ||
    strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false
);

define(
    'BASE_URL',
    $isLocalhost
    ? 'http://localhost/nobleaccounting'
    : $_ENV['APP_URL']
);


// ─── Routing ──────────────────────────────────────────────────────────────────
$request = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$request = trim($request, '/');
$request = preg_replace('#^nobleaccounting/?#', '', $request);
$request = trim($request, '/');

if ($request === '' || $request === 'home') {
    $request = 'home';
}

// ─── Define Admin Routes ──────────────────────────────────────────────────────
$adminRoutes = [

    // authentication admin
    'logout',                           
    'loginadmin',                       

    // annoucnement             
    'saveannouncement',             
    'deleteannouncement',             
    'fetchannouncementsadmin',         
    'generalannouncement',          
    'cleanupannouncements',   

     // IT page 1
    'createaccount',            
    'superad',                          
    'department', 
    // IT backend page1
    'hrfetch',                          
    'hrposition',
    'hrupdate', 
    'crudbranches',
    'cruddepartments',    
    'updateitbranch', 
    
    // IT page 2
    'it', 

    // accounting
    'accounting',                       
    'download-pdfbudgetrequest',        
    'accountingdashboard',              
    'announcementdashboard',            
    'announcement', 
    'accountingmonitoring', 
    'accountinggraph',         
    'fetch-budget-graph-data',    
    'accountingsignatured',     
    'accountingtracking',     
    'accountinggeneralsheet',     
    'accountingpettycash',  
    'accountingpettycashtwo',   
    'fetchnoblepettycashdepartment',

    // crm accounting page 1
    'crmaccounting',
    'accountingcrmlistajax',
    'accountingcrmdetail',
    'accountingpaymentmethodsajax',
    'accountingpaymentmethods',

    //staffaccounting
    'accountingstaff',                  
    'accountingstaffdashboard',         
    'fetchapproved',                    
    'markreceived',                     
    'fetchacknowledged',                
    'accountingstaffannouncement',      

    // custodian
    'accountingcustodian',              
    'accountingcustodiandashboard',     
    'fetchreceived',                    
    'submitrequestvoucher',             
    'accountingcustodianassistant',     
    'announcementcustodian',            
    'accountingcustodianpettycash',  
    'accountingcustodianpettycashtwo',  
    'savegeneralsheet',                 
    'fetchgeneralsheet',                
    'deletegeneralsheet',   
    'savecustodiansheetpettycashtwo',   
    'fetchcustodiansheetpettycashtwo',  
    'deletecustodiansheetpettycashtwo', 
    'fetchpettycashaccounttitles',
    'savepettycashaccounttitle',
    'deletepettycashaccounttitle',
    'fetchpettycashdepartment',
    'savepettycashdepartment',
    'deletepettycashdepartment',
    'pettycashdepartment',
    'fetchsalespersons',
    'savesalesperson',
    'fetchprojectnames',
    'saveprojectname',
    'deleteprojectname',

    // custiodiansublink
    'projectmonitor',                   
    'projectdetail',                    
    'fetchprojects',                    
    'saveproject',                      
    'fetchprojectbilling',              
    'saveprojectbilling',               
    'deleteprojectbilling',             
    'fetchprojectexpense',              
    'saveprojectexpense',               
    'deleteprojectexpense',             
    'exportprojectexcel',               
    'saveincomeloss',                   

    //cashvoucher
    'cashvoucherdashboard',             
    'cashvoucherapproved',              
    'cashvoucherprepared',              
    'cashvoucherfetchall',              
    'releasevoucher',                   

    // hr
    'humanresourcerequest',    
           

    // operation
    'operation',                        

    // sales page 1
    'salesmarket', 

    // sales page 2
    'crmsales',

    // sale page 3
    'crmsaleslist',
    
    // graphic design
    'graphicdesign',

    // designer page 1
    'designer', 

    // designer page 2
    'crmdesigner',   
    
    
    // cutting list
    'cuttinglist',     

    // crm cuttinglist page 2
    'crmcuttinglist',
    'crmcuttinglistdetail',
    'cuttinglistajax',

    // cuttinglist page 3
    'crmewood',
    'bomajax',
    'crmewoodajax',
    
    // super admin page 1
    'crm-main',

    // super admin backend page 1
    'check2dquotationajax',

    // super admin page 2
    'monitoring',
    'monitoringcrmview',

    // super admin backend page 2
    'monitoringcrmajax',

    // super admin page 3
    'crmewoodapproval',

    // super admin backend page 3
    'crmwoodapprovalajax',

    
    'unauthorized',                    
    'fetchrequests',                   
    'actionrequest',                   
    'fetchnotifications',              
    'marknotificationsread',           
    'notificationstream',              
    'pingrequest',                     
    'fetchstaff',                      
    'heartbeat', 
    
    //general
    'signatureadd',
    'uploadsignature',
    'fetchsignatures',
    'setactivesignature',
    'deletesignature',
    'updatesignature',

    // crm management page 1
    'crmajax',

    // crm management page 2
    'crmlistajax',
    'crm2dquotationajax',

    // crm management page 3
    'crmdesignerajax',
    'crm2dquotationajaxfinal',

    // crm management sub page 3
    'crm2dquotationfinal',
    'crmsitevisit',     
    'crm2dquotation',


];

if (in_array($request, $adminRoutes)) {
    session_name("nobleadmin");
} else {
    session_name("nobleuser");
}

session_start();

$routes = [
    'home'                             => 'user/authentication/index-login.php',
    'logout'                           => 'admin/authentication/index-logout.php',
    'loginadmin'                       => 'admin/authentication/index-login.php',
    'loginuser'                        => 'user/authentication/index-login.php',
    'callback'                         => 'user/authentication/index-callback.php',
    'userhome'                         => 'user/ui/index-main-page-1.php',
    'register'                         => 'user/authentication/index-register.php',
    'verify'                           => 'user/authentication/index-verify.php',
    'logoutuser'                       => 'user/authentication/index-logout.php',
    'fetchannouncements'               => 'user/navigation/backend/backend-announcement/fetchannouncements.php',
    'saveannouncement'                 => 'admin/announcementcentral/save-announcement.php',
    'deleteannouncement'               => 'admin/announcementcentral/deleteannouncement.php',
    'fetchannouncementsadmin'          => 'admin/announcementcentral/fetchannouncementsadmin.php',
    'generalannouncement'              => 'admin/announcementcentral/announcement-view.php',
    'cleanupannouncements'             => 'admin/announcementcentral/cleanup-announcements.php',

    //user
    'myvouchers'                       => 'user/ui/index-my-vouchers.php',
    'fetchmyvouchers'                  => 'user/ui/backend/index-fetch-my-vouchers.php',
    'acceptvoucher'                    => 'user/ui/backend/index-accept-voucher.php',
    'fetchheads'                       => 'user/ui/backend/index-fetch-heads.php',
    'submitrequest'                    => 'user/ui/backend/index-submit-request.php',
    'fetchmyrequests'                  => 'user/ui/backend/index-fetch-my-requests.php',
    'requesthistory'                   => 'user/ui/index-my-request-history.php',
    'dashboard'                        => 'user/ui/index-main-dashboard.php',

    //IT page 1
    'superad'                          => 'admin/ui-informationtech/page-1/it-registration-account.php',
    'createaccount'                    => 'admin/ui-informationtech/page-1/createaccount-main.php',
    'department'                       => 'admin/ui-informationtech/page-1/it-registration-department.php',

    //IT backend page 1
    'hrfetch'                          => 'admin/ui-informationtech/backend/page-1/itfetch.php',
    'hrposition'                       => 'admin/ui-informationtech/backend/page-1/itposition.php',
    'hrupdate'                         => 'admin/ui-informationtech/backend/page-1/itupdate.php',
    'crudbranches'                     => 'admin/ui-informationtech/backend/page-1/crudbranches.php',
    'cruddepartments'                  => 'admin/ui-informationtech/backend/page-1/cruddepartments.php',
    'updateitbranch'                   => 'admin/ui-informationtech/backend/page-1/itbranch.php',

    //IT page 2
    'it'                               => 'admin/ui-informationtech/page-2/index-it-main.php',
   
    // accounting
    'accounting'                       => 'admin/ui-accounting/page-1/index-accounting-main.php',
    'download-pdfbudgetrequest'        => 'admin/ui-accounting/page-1/backend/backend-page1/download-pdfbudgetrequest.php',
    'accountingdashboard'              => 'admin/ui-accounting/page-1/index-accounting-dashboard.php',
    'announcementdashboard'            => 'admin/ui-accounting/page-1/index-accounting-dashboard.php',
    'announcement'                     => 'admin/ui-accounting/page-1/index-accounting-announcement.php',
    'accountingmonitoring'             => 'admin/ui-accounting/page-1/index-accounting-monitoring.php',
    'accountinggraph'                  => 'admin/ui-accounting/page-1/index-accounting-graph.php',
    'fetch-budget-graph-data'          => 'admin/ui-accounting/backend/backend-page1/fetch-budget-graph-data.php',
    'accountingsignatured'             => 'admin/ui-accounting/page-1/index-accounting-signatured.php',
    'accountingtracking'               => 'admin/ui-accounting/page-1/index-accounting-tracking.php',
    'accountinggeneralsheet'           => 'admin/ui-accounting/page-1/index-accounting-generalsheet.php',
    'accountingpettycash'              => 'admin/ui-accounting/page-1/index-accounting-pettycash.php',
    'savenoblegeneralsheet'            => 'admin/ui-accounting/backend/backend-page1/noble-save-generalsheet.php',
    'fetchnoblegeneralsheet'           => 'admin/ui-accounting/backend/backend-page1/noble-fetch-generalsheet.php',
    'deletenoblegeneralsheet'          => 'admin/ui-accounting/backend/backend-page1/noble-delete-generalsheet.php',

    // crm accounting page 1
    'crmaccounting'                    => 'admin/ui-accounting/page-1/index-accounting-crmlist.php',
    'accountingcrmlistajax'            => 'admin/ui-accounting/backend/backend-page1/index-accounting-crmlistajax.php',
    'accountingcrmdetail'              => 'admin/ui-accounting/page-1/index-accounting-crmdetail.php',
    'accountingpaymentmethods'         => 'admin/ui-accounting/page-1/index-accounting-paymentmethods.php',
    'accountingpaymentmethodsajax'     => 'admin/ui-accounting/backend/backend-page1/index-accounting-paymentmethodsajax.php',
    
    //staffaccounting
    'accountingstaff'                  => 'admin/ui-accounting/page-4/index-staff-main.php',
    'accountingstaffdashboard'         => 'admin/ui-accounting/page-4/index-staff-dashboard.php',
    'fetchapproved'                    => 'admin/ui-accounting/backend/backend-staff/index-staff-fetch-approved.php',
    'markreceived'                     => 'admin/ui-accounting/backend/backend-staff/index-staff-mark-received.php',
    'fetchacknowledged'                => 'admin/ui-accounting/backend/backend-staff/index-staff-acknowledge.php',
    'accountingstaffannouncement'      => 'admin/ui-accounting/page-4/index-staff-announcement.php',
    'fetchnoblepettycashdepartment'    => 'admin/ui-accounting/backend/backend-page1/noble-fetch-pettycash-department.php',

    // custodian
    'accountingcustodian'              => 'admin/ui-accounting/page-3/index-custodian-main.php',
    'accountingcustodiandashboard'     => 'admin/ui-accounting/page-3/index-custodian-dashboard.php',
    'fetchreceived'                    => 'admin/ui-accounting/backend/backend-custodian/index-custodian-fetch-received.php',
    'submitrequestvoucher'             => 'admin/ui-accounting/backend/backend-custodian/index-custodian-submit-voucher.php',
    'savegeneralsheet'                 => 'admin/ui-accounting/backend/backend-custodian/index-custodian-pettycashsavegeneralsheet.php',
    'fetchgeneralsheet'                => 'admin/ui-accounting/backend/backend-custodian/index-custodian-pettycashfetchgeneralsheet.php',
    'deletegeneralsheet'               => 'admin/ui-accounting/backend/backend-custodian/index-custodian-pettycashdeletegeneralsheet.php',
    'accountingcustodianassistant'     => 'admin/ui-accounting/page-3/index-custodianassist-main.php',
    'announcementcustodian'            => 'admin/ui-accounting/page-3/index-custodian-dashboard.php',
    'accountingcustodianpettycash'     => 'admin/ui-accounting/page-3/index-custodian-pettycash.php',
    'accountingcustodianpettycashtwo'  => 'admin/ui-accounting/page-3/index-custodian-pettycashtwo.php',
    'savecustodiansheetpettycashtwo'   => 'admin/ui-accounting/backend/backend-custodian/index-custodian-pettycashtwosavegeneralsheet.php',
    'fetchcustodiansheetpettycashtwo'  => 'admin/ui-accounting/backend/backend-custodian/index-custodian-pettycashtwofetchgeneralsheet.php',
    'deletecustodiansheetpettycashtwo' => 'admin/ui-accounting/backend/backend-custodian/index-custodian-pettycashtwodeletegeneralsheet.php',
    'fetchpettycashaccounttitles'      => 'admin/ui-accounting/backend/backend-custodian/pettycashtwo-fetch-account-title.php',
    'savepettycashaccounttitle'        => 'admin/ui-accounting/backend/backend-custodian/pettycashtwo-save-account-title.php',
    'deletepettycashaccounttitle'      => 'admin/ui-accounting/backend/backend-custodian/pettycashtwo-delete-account-title.php',
    'fetchpettycashdepartment'         => 'admin/ui-accounting/backend/backend-custodian/pettycashtwo-fetch-department.php',
    'savepettycashdepartment'          => 'admin/ui-accounting/backend/backend-custodian/pettycashtwo-save-department.php',
    'deletepettycashdepartment'        => 'admin/ui-accounting/backend/backend-custodian/pettycashtwo-delete-department.php',
    'pettycashdepartment'              => 'admin/ui-accounting/page-3/index-custodian-pettycash-department.php',
    'fetchprojectnames'                => 'admin/ui-accounting/backend/backend-custodian/index-projectmonitor-fetchprojectname.php',
    'saveprojectname'                  => 'admin/ui-accounting/backend/backend-custodian/index-projectmonitor-saveprojectname.php',
    'deleteprojectname'                => 'admin/ui-accounting/backend/backend-custodian/index-projectmonitor-deleteprojectname.php',

    // custiodiansublink
    'projectmonitor'                   => 'admin/ui-accounting/page-3/index-projectmonitor-main.php',
    'projectdetail'                    => 'admin/ui-accounting/page-3/index-projectmonitor-details.php',
    'fetchprojects'                    => 'admin/ui-accounting/backend/backend-custodian/index-projectmonitor-fetchprojects.php',
    'saveproject'                      => 'admin/ui-accounting/backend/backend-custodian/index-projectmonitor-saveproject.php',
    'fetchprojectbilling'              => 'admin/ui-accounting/backend/backend-custodian/index-projectmonitor-fetchprojectbilling.php',
    'saveprojectbilling'               => 'admin/ui-accounting/backend/backend-custodian/index-projectmonitor-saveprojectbilling.php',
    'deleteprojectbilling'             => 'admin/ui-accounting/backend/backend-custodian/index-projectmonitor-deleteprojectbilling.php',
    'fetchprojectexpense'              => 'admin/ui-accounting/backend/backend-custodian/index-projectmonitor-fetchprojectexpense.php',
    'saveprojectexpense'               => 'admin/ui-accounting/backend/backend-custodian/index-projectmonitor-saveprojectexpense.php',
    'deleteprojectexpense'             => 'admin/ui-accounting/backend/backend-custodian/index-projectmonitor-deleteprojectexpense.php',
    'exportprojectexcel'               => 'admin/ui-accounting/backend/backend-custodian/index-projectmonitor-exportprojectexcel.php',
    'saveincomeloss'                   => 'admin/ui-accounting/backend/backend-custodian/index-projectmonitor-saveincomeloss.php',

    //cashvoucher
    'cashvoucherdashboard'             => 'admin/ui-accounting/page-2/index-cashvoucherdashboard.php',
    'cashvoucherapproved'              => 'admin/ui-accounting/backend/backend-cashvoucher/index-cashvoucherapproved.php',
    'cashvoucherprepared'              => 'admin/ui-accounting/backend/backend-cashvoucher/index-cashvoucherprepared.php',
    'cashvoucherfetchall'              => 'admin/ui-accounting/backend/backend-cashvoucher/index-cashvoucherfetchall.php',
    'releasevoucher'                   => 'admin/ui-accounting/backend/backend-cashvoucher/index-cashreleasevoucher.php',

    // hr
    'humanresourcerequest'             => 'admin/ui-humanresource/humanresource-request.php',


    // operation
    'operation'                        => 'admin/ui-operation/index-operation-main.php',

    // sales page 1
    'salesmarket'                      => 'admin/ui-salesmarket/page-1/index-sales-main.php',

    //sales page 2
    'crmsales'                         => 'admin/ui-salesmarket/page-2/crmsales.php',

    // sales page 3
    'crmsaleslist'                     => 'admin/ui-salesmarket/page-3/crmsaleslist.php',

    // graphic design
    'graphicdesign'                    => 'admin/ui-graphicdesign/index-graphic-main.php',

    // designer page 1
    'designer'                         => 'admin/ui-designer/page-1/index-designer-main.php',

    // designer page 2
    'crmdesigner'                      => 'admin/ui-designer/page-2/crmdesigner.php',


    // cutting list
    'cuttinglist'                      => 'admin/ui-cuttinglist/page-1/index-cuttinglist-main.php',

    // crm cutting list page 2
    'crmcuttinglist'                   => 'admin/ui-cuttinglist/page-2/crm-cuttingmain.php',
    'crmcuttinglistdetail'             => 'admin/ui-cuttinglist/page-2/index-cuttinglist-detail.php',
    'cuttinglistajax'                  => 'admin/ui-cuttinglist/backend/page-2/cuttinglistajax.php',

    // cutting list page 3
    'crmewood'                         => 'admin/ui-cuttinglist/page-3/ewoodfile.php',
    'bomajax'                          => 'admin/ui-cuttinglist/backend/page-3/bomajax.php',
    'crmewoodajax'                     => 'admin/ui-cuttinglist/backend/page-3/cuttinglistprogressionajax.php',

    // super admin page 1
    'crm-main'                         => 'admin/ui-superad/page-1/check2dquotation.php',

    // super admin backend page 1
    'check2dquotationajax'             => 'admin/ui-superad/backend/page-1/check2dquotationajax.php',

    // super admin page 2
    'monitoring'                       => 'admin/ui-superad/page-2/monitoringcrm.php',
    'monitoringcrmview'                => 'admin/ui-superad/page-2/monitoringcrmview.php',
    
    // super admin backend page 2
    'monitoringcrmajax'                => 'admin/ui-superad/backend/page-2/monitoringcrmajax.php',

    // super admin page 3
    'crmewoodapproval'                 => 'admin/ui-superad/page-3/ewood.php',

    // super admin backend page 3
    'crmwoodapprovalajax'              => 'admin/ui-superad/backend/page-3/ewoodapprovalajax.php',

    'unauthorized'                     => 'admin/authentication/index-unauthorized.php',
    'fetchrequests'                    => 'admin/requestcentral/fetch-requests.php',
    'actionrequest'                    => 'admin/requestcentral/action-request.php',
    'fetchnotifications'               => 'admin/navigation/sidebar-fetch-notifications.php',
    'marknotificationsread'            => 'admin/navigation/sidebar-mark-notifications-read.php',
    'notificationstream'               => 'admin/navigation/sidebar-notification-stream.php',
    'pingrequest'                      => 'admin/requestcentral/ping-request.php',
    'fetchstaff'                       => 'admin/requestcentral/fetch-staff.php',
    'heartbeat'                        => 'admin/authentication/index-heartbeat.php',

    //user
    'fetchnotificationsuser'           => 'user/navigation/backend/backend-notification/fetchnotifications.php',
    'readnotificationuser'             => 'user/navigation/backend/backend-notification/readnotification.php',
    'notificationsuser'                => 'user/ui/index-notifications.php',

    //general
    'signatureadd'                     => 'admin/navigation/settings/signatureadd.php',
    'uploadsignature'                  => 'admin/navigation/settings/backend/backend-signatured/upload-signature.php',
    'fetchsignatures'                  => 'admin/navigation/settings/backend/backend-signatured/fetch-signatures.php',
    'setactivesignature'               => 'admin/navigation/settings/backend/backend-signatured/set-active-signature.php',
    'deletesignature'                  => 'admin/navigation/settings/backend/backend-signatured/delete-signature.php',
    'updatesignature'                  => 'admin/navigation/settings/backend/backend-signatured/update-signature.php',

    // crm management page 1
    'crmajax'                          => 'admin/crm-management/backend/page-1/crm-options-ajax.php',

    // crm management page 2
    'crmlistajax'                      => 'admin/crm-management/backend/page-2/crmlistajax.php',
    'crm2dquotationajax'               => 'admin/crm-management/backend/page-2/crm2dquotationajax.php',
    
    // crm management page 3
    'crmdesignerajax'                  => 'admin/crm-management/backend/page-3/crmdesignerajax.php',
    'crm2dquotationajaxfinal'          => 'admin/crm-management/backend/page-3/subpage-3/crm2dquotationajaxfinal.php',

    // crm management page 3
    'crm2dquotationfinal'              => 'admin/crm-management/page-3/subpage-3/2d-and-quotation-final.php',
    'crmsitevisit'                     => 'admin/crm-management/page-3/subpage-3/sitevisitform.php',
    'crm2dquotation'                   => 'admin/crm-management/page-3/subpage-3/2d-and-quotation.php',
];


$file = $routes[$request] ?? null;

if ($file === null) {
    http_response_code(404);
    include ROOT_PATH . '/404.php';
    exit;
}

$filepath = ROOT_PATH . '/' . $file;

if (file_exists($filepath)) {
    include $filepath;
} else {
    http_response_code(404);
    include ROOT_PATH . '/404.php';
    exit;
}