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
    'sidebarpoll',
    'adminhome',
    'verifyid',
    'adminprofile',

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
    'accountingfollowups',           
    'download-pdfbudgetrequest',        
    'accountingdashboard',              
    'announcementdashboard',            
    'announcement', 
    'accountingmonitoring', 
    'accountinggraph',
    'accountingpurchaseorder',  
    'accountingpurchaseorderajax',
    'fetch-budget-graph-data',    
    'accountingsignatured',     
    'accountingtracking',     
    'accountinggeneralsheet',     
    'accountingpettycash',  
    'accountingpettycashtwo',   
    'fetchnoblepettycashdepartment',
    'fetchnoblepettycashdepartment',
    'savenoblegeneralsheet',
    'fetchnoblegeneralsheet',
    'deletenoblegeneralsheet',

    // crm accounting page 1
    'crmaccounting',
    'accountingcrmlistajax',
    'accountingcrmdetail',
    'accountingpaymentmethodsajax',
    'accountingpaymentmethods',
    'accountingbilling',
    'accountingbillingajax',

    //staffaccounting
    'accountingstaff',                  
    'accountingstaffdashboard',     
    'accountingstaffpurchase',      
    'accountingstaffpurchaseajax',  
    'fetchapproved',                    
    'markreceived',                     
    'fetchacknowledged',                
    'accountingstaffannouncement',      

    // custodian
    'accountingcustodian',              
    'accountingcustodiandashboard',   
    'accountingcustodianpurchaseorder',
    'custodianpurchaseajax',            
    'fetchreceived',                    
    'fetchreceiveddates',
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
    'accountingcustodianassistantpurchaseorder',          

    //cashvoucher
    'cashvoucherdashboard',             
    'cashvoucherapproved',              
    'cashvoucherprepared',              
    'cashvoucherfetchall',              
    'releasevoucher',                   

    // hr
    'humanresourcerequest',  
    'hrprofile',  
    'profileqrregen',
    'hrannouncement',
    'listaccount',
    'payrollannouncement',
    'profileajax', 
    'profilelinkajax',     
    'profileupdateajax',    
    'profileofficialajax', 
    'listaccountajax',

    // purchase order
    'crmpurchaseorder',
    'purchaseorderajax',
    'transmittal',
    'transmittalajax',
    'clientseries',
    'clientseriesajax',
    'transmittalsummary',
    'transmittalsummaryajax',
    'purchaserequest',
    'purchaserequestajax',

    // project coordinator
    'coordinator',
    'coordinatorajax',
    'coordinatorlabor',
    'coordinatorlaborajax',
    'coordinatortimeline',
    'coordinatortimelineajax',
    'coordinatorbilling',
    'coordinatorbillingajax',
    'coordinatorturnover',
    'coordinatorturnoverajax',
    
    // operation
    'operation',                        

    // sales page 1
    'salesmarket', 

    // sales page 2
    'crmsales',

    // sale page 3
    'crmsaleslist',

    // sale page 4
    'quotation',
    'quotationpdf',
    'quotationbuilder',

    // sale page 5
    'quotationcrud',
  

    // sale page 6
    'quotationpricelist',
    'quotationpricelistajax',

    // graphic design
    'graphicdesign',

    // designer page 1
    'designer', 

    // designer page 2
    'crmdesigner',   

    // designer page 3
    'checkdesignerquotation',
    'checkdesigner2dquotationajax',

    // cutting list
    'cuttinglist',     
    'crmcuttinglist',
    'crmcuttinglistdetail',
    'cuttinglistajax',
    'crmewood',
    'bomajax',
    'crmewoodajax',
    'crmsupplier',
    'crmsupplierajax',
    
    // super admin page 1
    'crm-main',
    'check2dquotationajax',
    'monitoring',
    'monitoringcrmview',
    'monitoringcrmajax',
    'monitoringcrmviewajax',
    'crmewoodapproval',
    'crmwoodapprovalajax',
    'purchaseorderapproval',
    'purchaseorderapprovalajax',
    'superadmin-dr',
    'superadmindrajax',
    'superadminlabor',
    'superadminlaborajax',
    'superadminbilling',
    'superadminbillingajax',
    'superadminturnover',
    'superadminturnoverajax',

    'unauthorized',                    
    'fetchrequests',                   
    'actionrequest',    
    'fetchrequestdates' ,               
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

    //profile
    'profile',

    // crm management page 1
    'crmajax',
    'crmlistajax',
    'crm2dquotationajax',
    'crmdesignerajax',
    'crmsitevisitajax',
    'crm2dquotationajaxfinal',
    'crm2dquotationfinal',
    'crmsitevisit',     
    'crm2dquotation',
    'crm2dcustomerreview',
    'crm2drevisionajax',
    'crmdesignerfeedbackajax',
    'crmdesignerprogressajax',
    'crmlistresetajax',

];

if (in_array($request, $adminRoutes)) {
    session_name("nobleadmin");
} else {
    session_name("nobleuser");
}

session_start();

$routes = [
    'apilogin'                         => 'api/login.php',
    'apinoblerole'                     => 'api/noblerole.php',


    'home'                             => 'animation.php',
    'adminhome'                        => 'admin/authentication/index-animation.php',
    'adminprofile'                     => 'admin/navigation/ui-profile/adminprofile.php',
    

    'home1'                            => 'user/authentication/index-login.php',

    'logout'                           => 'admin/authentication/index-logout.php',
    'loginadmin'                       => 'admin/authentication/index-login.php',
    'sidebarpoll'                      => 'admin/navigation/sidebar-poll.php',

    'loginuser'                        => 'user/authentication/index-login.php',
    'callback'                         => 'user/authentication/index-callback.php',
    'userhome'                         => 'user/ui/index-main-page-1.php',
    'register'                         => 'user/authentication/index-register.php',
    'verify'                           => 'user/authentication/index-verify.php',
    'logoutuser'                       => 'user/authentication/index-logout.php',
    'fetchannouncements'               => 'user/navigation/backend/backend-announcement/fetchannouncements.php',
    'userprofile'                      => 'user/ui/index-userprofile.php',
    'verifyid'                           => 'verifyview_id.php',

    'saveannouncement'                 => 'admin/announcementcentral/save-announcement.php',
    'deleteannouncement'               => 'admin/announcementcentral/deleteannouncement.php',
    'fetchannouncementsadmin'          => 'admin/announcementcentral/fetchannouncementsadmin.php',
    'generalannouncement'              => 'admin/announcementcentral/announcement-view.php',
    'cleanupannouncements'             => 'admin/announcementcentral/cleanup-announcements.php',

    //user
    'myvouchers'                       => 'user/ui/index-my-vouchers.php',
    'followupattachments'              => 'user/ui/index-followup-attachments.php',
    'fetchmyvouchers'                  => 'user/ui/backend/index-fetch-my-vouchers.php',
    'acceptvoucher'                    => 'user/ui/backend/index-accept-voucher.php',
    'fetchheads'                       => 'user/ui/backend/index-fetch-heads.php',
    'submitrequest'                    => 'user/ui/backend/index-submit-request.php',
    'fetchmyrequests'                  => 'user/ui/backend/index-fetch-my-requests.php',
    'uploadfollowup'                   => 'user/ui/backend/index-followup-upload.php',
    'requesthistory'                   => 'user/ui/index-my-request-history.php',
    'dashboard'                        => 'user/ui/index-main-dashboard.php',
    'rejectedrequests'                 => 'user/ui/index-rejected-requests.php',
    'fetchrejectedrequests'            => 'user/ui/backend/fetch-rejected-requests.php',
    'resubmitrequest'                  => 'user/ui/backend/index-resubmit-request.php',

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
    'accountingfollowups'              => 'admin/ui-accounting/page-1/index-accounting-followups.php',
    'accountingdashboard'              => 'admin/ui-accounting/page-1/index-accounting-dashboard.php',
    'announcementdashboard'            => 'admin/ui-accounting/page-1/index-accounting-dashboard.php',
    'announcement'                     => 'admin/ui-accounting/page-1/index-accounting-announcement.php',
    'accountingmonitoring'             => 'admin/ui-accounting/page-1/index-accounting-monitoring.php',
    'accountinggraph'                  => 'admin/ui-accounting/page-1/index-accounting-graph.php',
    'accountingsignatured'             => 'admin/ui-accounting/page-1/index-accounting-signatured.php',
    'accountingtracking'               => 'admin/ui-accounting/page-1/index-accounting-tracking.php',
    'accountinggeneralsheet'           => 'admin/ui-accounting/page-1/index-accounting-generalsheet.php',
    'accountingpettycash'              => 'admin/ui-accounting/page-1/index-accounting-pettycash.php',
    'accountingpurchaseorder'          => 'admin/ui-accounting/page-1/index-accounting-purchaseorder.php',
    'accountingpurchaseorderajax'      => 'admin/ui-accounting/backend/backend-page1/accountingpurchaseorderajax.php',
    'download-pdfbudgetrequest'        => 'admin/ui-accounting/backend/backend-page1/download-pdfbudgetrequest.php',
    'fetch-budget-graph-data'          => 'admin/ui-accounting/backend/backend-page1/fetch-budget-graph-data.php',
    'savenoblegeneralsheet'            => 'admin/ui-accounting/backend/backend-page1/noble-save-generalsheet.php',
    'fetchnoblegeneralsheet'           => 'admin/ui-accounting/backend/backend-page1/noble-fetch-generalsheet.php',
    'deletenoblegeneralsheet'          => 'admin/ui-accounting/backend/backend-page1/noble-delete-generalsheet.php',

    // crm accounting page 1
    'crmaccounting'                    => 'admin/ui-accounting/page-1/index-accounting-crmlist.php',
    'accountingcrmlistajax'            => 'admin/ui-accounting/backend/backend-page1/index-accounting-crmlistajax.php',
    'accountingcrmdetail'              => 'admin/ui-accounting/page-1/index-accounting-crmdetail.php',
    'accountingpaymentmethods'         => 'admin/ui-accounting/page-1/index-accounting-paymentmethods.php',
    'accountingpaymentmethodsajax'     => 'admin/ui-accounting/backend/backend-page1/index-accounting-paymentmethodsajax.php',
    'accountingbilling'                => 'admin/ui-accounting/page-1/accounting-billing.php',
    'accountingbillingajax'            => 'admin/ui-accounting/backend/backend-page1/accountingbillingajax.php',
    
    
    //staffaccounting
    'accountingstaff'                  => 'admin/ui-accounting/page-4/index-staff-main.php',
    'accountingstaffdashboard'         => 'admin/ui-accounting/page-4/index-staff-dashboard.php',
    'accountingstaffpurchase'          => 'admin/ui-accounting/page-4/index-staff-purchase.php',
    'accountingstaffpurchaseajax'      => 'admin/ui-accounting/backend/backend-staff/accountingstaffpurchaseajax.php',
    'fetchapproved'                    => 'admin/ui-accounting/backend/backend-staff/index-staff-fetch-approved.php',
    'markreceived'                     => 'admin/ui-accounting/backend/backend-staff/index-staff-mark-received.php',
    'fetchacknowledged'                => 'admin/ui-accounting/backend/backend-staff/index-staff-acknowledge.php',
    'accountingstaffannouncement'      => 'admin/ui-accounting/page-4/index-staff-announcement.php',
    'fetchnoblepettycashdepartment'    => 'admin/ui-accounting/backend/backend-page1/noble-fetch-pettycash-department.php',

    // custodian
    'accountingcustodian'              => 'admin/ui-accounting/page-3/index-custodian-main.php',
    'accountingcustodiandashboard'     => 'admin/ui-accounting/page-3/index-custodian-dashboard.php',
    'accountingcustodianpurchaseorder' => 'admin/ui-accounting/page-3/index-custodian-purchaseorder.php',
    'custodianpurchaseajax'            => 'admin/ui-accounting/backend/backend-custodian/custodianpurchaseajax.php',
    'fetchreceived'                    => 'admin/ui-accounting/backend/backend-custodian/index-custodian-fetch-received.php',
    'fetchreceiveddates'                => 'admin/ui-accounting/backend/backend-custodian/index-custodian-fetch-received-dates.php',
    'submitrequestvoucher'             => 'admin/ui-accounting/backend/backend-custodian/index-custodian-submit-voucher.php',
    'savegeneralsheet'                 => 'admin/ui-accounting/backend/backend-custodian/index-custodian-pettycashsavegeneralsheet.php',
    'fetchgeneralsheet'                => 'admin/ui-accounting/backend/backend-custodian/index-custodian-pettycashfetchgeneralsheet.php',
    'deletegeneralsheet'               => 'admin/ui-accounting/backend/backend-custodian/index-custodian-pettycashdeletegeneralsheet.php',
    'accountingcustodianassistantpurchaseorder' => 'admin/ui-accounting/page-3/index-custodianassist-purchaseorder.php',
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

    // purchase order
    'crmpurchaseorder'                 => 'admin/ui-po/page-1/index-purchaseorder-main.php',
    'purchaserequest'                  => 'admin/ui-po/page-2/index-purchaserequest.php',
    'transmittal'                      => 'admin/ui-po/page-3/transmittal.php',
    'transmittalsummary'               => 'admin/ui-po/page-3/index-transmittalsummary.php',
    'clientseries'                     => 'admin/ui-po/page-2/index-clientseries.php',
    'transmittalajax'                  => 'admin/ui-po/backend/page-3/transmittalajax.php',
    'transmittalsummaryajax'           => 'admin/ui-po/backend/page-3/transmittalsummaryajax.php',
    'purchaseorderajax'                => 'admin/ui-po/backend/page-1/purchaseorderajax.php',
    'purchaserequestajax'              => 'admin/ui-po/backend/page-2/purchaserequestajax.php',
    'clientseriesajax'                 => 'admin/ui-po/backend/page-2/clientseriesajax.php',

    // project coordinator
    'coordinator'                      => 'admin/ui-pc/page-1/coordinator.php',
    'coordinatorajax'                  => 'admin/ui-pc/backend/page-1/coordinatorajax.php',
    'coordinatorlabor'                 => 'admin/ui-pc/page-2/coordinator-labor.php',
    'coordinatorlaborajax'             => 'admin/ui-pc/backend/page-2/coordinatorlaborajax.php',
    'coordinatortimeline'              => 'admin/ui-pc/page-3/coordinator-timeline.php',
    'coordinatortimelineajax'          => 'admin/ui-pc/backend/page-3/coordinatortimelineajax.php',
    'coordinatorbilling'               => 'admin/ui-pc/page-4/coordinator-billing.php',
    'coordinatorbillingajax'           => 'admin/ui-pc/backend/page-4/coordinatorbillingajax.php',
    'coordinatorturnover'              => 'admin/ui-pc/page-5/coordinator-turnover.php',
    'coordinatorturnoverajax'          => 'admin/ui-pc/backend/page-5/coordinatorturnoverajax.php',


    // hr
    'humanresourcerequest'             => 'admin/ui-humanresource/page-1/humanresource-request.php',
    'hrprofile'                        => 'admin/ui-humanresource/page-2/profileinsert.php',
    'profileqrregen'                   => 'admin/ui-humanresource/page-2/profileqrregen.php',
    'hrannouncement'                   => 'admin/ui-humanresource/page-3/hrannouncement.php',
    'listaccount'                      => 'admin/ui-humanresource/page-5/listaccount.php',
    'payrollannouncement'              => 'admin/ui-humanresource/page-4/payrollannouncement.php',
    'profileajax'                      => 'admin/ui-humanresource/backend/page-1/profileajax.php',
    'profilelinkajax'                  => 'admin/ui-humanresource/backend/page-1/profilelinkajax.php',
    'profileupdateajax'                => 'admin/ui-humanresource/backend/page-1/profileupdateajax.php',
    'profileofficialajax'              => 'admin/ui-humanresource/backend/page-1/profileofficialajax.php',
    'listaccountajax'                  => 'admin/ui-humanresource/backend/page-5/listaccountajax.php',

    // operation
    'operation'                        => 'admin/ui-operation/index-operation-main.php',

    // sales page 1
    'salesmarket'                      => 'admin/ui-salesmarket/page-1/index-sales-main.php',

    //sales page 2
    'crmsales'                         => 'admin/ui-salesmarket/page-2/crmsales.php',

    // sales page 3
    'crmsaleslist'                     => 'admin/ui-salesmarket/page-3/crmsaleslist.php',

    // sales page 4
    'quotation'                        => 'admin/ui-salesmarket/page-4/quotation.php',
    'quotationbuilder'                 => 'admin/ui-salesmarket/page-4/quotationbuilder.php',
    'quotationpdf'                     => 'admin/ui-salesmarket/backend/page-4/quotationpdf.php',

    // sales page 5
    'quotationcrud'                    => 'admin/ui-salesmarket/page-5/quotationcrud.php',

    // sales page 6
    'quotationpricelist'               => 'admin/ui-salesmarket/page-6/quotationpricelist.php',
    'quotationpricelistajax'             => 'admin/ui-salesmarket/backend/page-6/quotationpricelistajax.php',

    // graphic design
    'graphicdesign'                    => 'admin/ui-graphicdesign/index-graphic-main.php',

    // designer page 1
    'designer'                         => 'admin/ui-designer/page-1/index-designer-main.php',

    // designer page 2
    'crmdesigner'                      => 'admin/ui-designer/page-2/crmdesigner.php',

    // designer page 3
    'checkdesignerquotation'           => 'admin/ui-designer/page-3/checkdesigner2dquotation.php',
    'checkdesigner2dquotationajax'     => 'admin/ui-designer/backend/page-3/checkdesigner2dquotationajax.php',

    // cutting list
    'cuttinglist'                      => 'admin/ui-cuttinglist/page-1/index-cuttinglist-main.php',
    'crmcuttinglist'                   => 'admin/ui-cuttinglist/page-2/crm-cuttingmain.php',
    'crmcuttinglistdetail'             => 'admin/ui-cuttinglist/page-2/index-cuttinglist-detail.php',
    'cuttinglistajax'                  => 'admin/ui-cuttinglist/backend/page-2/cuttinglistajax.php',
    'crmewood'                         => 'admin/ui-cuttinglist/page-3/ewoodfile.php',
    'bomajax'                          => 'admin/ui-cuttinglist/backend/page-3/bomajax.php',
    'crmewoodajax'                     => 'admin/ui-cuttinglist/backend/page-3/cuttinglistprogressionajax.php',
    'crmsupplier'                      => 'admin/ui-cuttinglist/page-4/crmsupplier.php',
    'crmsupplierajax'                  => 'admin/ui-cuttinglist/backend/page-4/crmsupplierajax.php',

    // super admin
    'crm-main'                         => 'admin/ui-superad/page-1/check2dquotation.php',
    'check2dquotationajax'             => 'admin/ui-superad/backend/page-1/check2dquotationajax.php',
    'monitoring'                       => 'admin/ui-superad/page-2/monitoringcrm.php',
    'monitoringcrmview'                => 'admin/ui-superad/page-2/monitoringcrmview.php',
    'monitoringcrmajax'                => 'admin/ui-superad/backend/page-2/monitoringcrmajax.php',
    'monitoringcrmviewajax'            => 'admin/ui-superad/backend/page-2/monitoringcrmviewajax.php',
    'crmewoodapproval'                 => 'admin/ui-superad/page-3/ewood.php',
    'crmwoodapprovalajax'              => 'admin/ui-superad/backend/page-3/ewoodapprovalajax.php',
    'purchaseorderapproval'            => 'admin/ui-superad/page-4/PurchaseOrder.php',
    'purchaseorderapprovalajax'        => 'admin/ui-superad/backend/page-4/purchaseorderapprovalajax.php',
    'superadmin-dr'                  => 'admin/ui-superad/page-5/superadmin-dr.php',
    'superadmindrajax'               => 'admin/ui-superad/backend/page-5/superadmindrajax.php',
    'superadminlabor'                 => 'admin/ui-superad/page-6/superadmin-labor.php',
    'superadminlaborajax'             => 'admin/ui-superad/backend/page-6/superadminlaborajax.php',
    'superadminbilling'               => 'admin/ui-superad/page-7/superadmin-billing.php',
    'superadminbillingajax'           => 'admin/ui-superad/backend/page-7/superadminbillingajax.php',
    'superadminturnover'              => 'admin/ui-superad/page-8/superadmin-turnover.php',
    'superadminturnoverajax'          => 'admin/ui-superad/backend/page-8/superadminturnoverajax.php',


    'unauthorized'                     => 'admin/authentication/index-unauthorized.php',
    'fetchrequests'                    => 'admin/requestcentral/fetch-requests.php',
    'fetchrequestdates'                => 'admin/requestcentral/fetch-request-dates.php',
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

    //profile
    'profile'                          => 'admin/navigation/ui-profile/profile.php',

    // crm management
    'crmajax'                          => 'admin/crm-management/backend/page-1/crm-options-ajax.php',
    'crmlistajax'                      => 'admin/crm-management/backend/page-2/crmlistajax.php',
    'crm2dquotationajax'               => 'admin/crm-management/backend/page-2/crm2dquotationajax.php',
    'crmdesignerajax'                  => 'admin/crm-management/backend/page-3/crmdesignerajax.php',
    'crmsitevisitajax'                 => 'admin/crm-management/backend/page-3/subpage-3/crmsitevisitajax.php',
    'crm2dquotationajaxfinal'          => 'admin/crm-management/backend/page-3/subpage-3/crm2dquotationajaxfinal.php',
    'crm2dquotationfinal'              => 'admin/crm-management/page-3/subpage-3/2d-and-quotation-final.php',
    'crmsitevisit'                     => 'admin/crm-management/page-3/subpage-3/sitevisitform.php',
    'crm2dquotation'                   => 'admin/crm-management/page-3/subpage-3/2d-and-quotation.php',
    'crm2dcustomerreview'              => 'admin/crm-management/page-3/subpage-3/crm-2d-customer-review.php',
    'crm2drevisionajax'                => 'admin/crm-management/backend/page-3/subpage-3/crm2drevisionajax.php',
    'crmdesignerfeedbackajax'          => 'admin/crm-management/backend/page-3/crmdesignerfeedbackajax.php',
    'crmdesignerprogressajax'          => 'admin/crm-management/backend/page-3/crmdesignerprogressajax.php',
    'crmlistresetajax'                 => 'admin/crm-management/backend/page-2/crmlistresetajax.php',
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