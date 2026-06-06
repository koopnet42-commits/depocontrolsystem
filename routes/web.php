<?php

declare(strict_types=1);

use App\Controllers\DashboardController;
use App\Controllers\CompanyController;
use App\Controllers\ProductController;
use App\Controllers\DeliveryNotificationController;
use App\Controllers\IncomingProductController;
use App\Controllers\WeighbridgeEntryController;
use App\Controllers\SampleAnalysisController;
use App\Controllers\SiloController;
use App\Controllers\SiloRuleController;
use App\Controllers\BarcodeTicketController;
use App\Controllers\UnloadingOperationController;
use App\Controllers\OutboundLoadingController;
use App\Controllers\SecondWeighingController;
use App\Controllers\ReportController;
use App\Controllers\AuthController;
use App\Controllers\SettingsController;
use App\Controllers\SecurityRecoveryController;
use App\Controllers\ProcessRepairController;
use App\Controllers\VehicleProcessController;
use App\Controllers\OutboundProcessController;
use App\Controllers\DriverVehicleController;
use App\Core\Router;

/** @var Router $router */
$router->get('/login', [AuthController::class, 'loginForm']);
$router->post('/login', [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout']);
$router->get('/password/change', [AuthController::class, 'changePasswordForm']);
$router->post('/password/change', [AuthController::class, 'changePassword']);

$router->get('/', [DashboardController::class, 'index']);
$router->get('/dashboard', [DashboardController::class, 'index']);
$router->get('/dashboard/scale-status', [DashboardController::class, 'scaleStatus']);

$router->get('/companies', [CompanyController::class, 'index']);
$router->get('/companies/create', [CompanyController::class, 'create']);
$router->get('/companies/edit', [CompanyController::class, 'edit']);
$router->post('/companies/store', [CompanyController::class, 'store']);
$router->post('/companies/update', [CompanyController::class, 'update']);
$router->post('/companies/toggle-status', [CompanyController::class, 'toggleStatus']);

$router->get('/products', [ProductController::class, 'index']);
$router->get('/products/create', [ProductController::class, 'create']);
$router->get('/products/edit', [ProductController::class, 'edit']);
$router->get('/products/official-criteria-preview', [ProductController::class, 'officialCriteriaPreview']);
$router->post('/products/store', [ProductController::class, 'store']);
$router->post('/products/update', [ProductController::class, 'update']);
$router->post('/products/toggle-status', [ProductController::class, 'toggleStatus']);

$router->get('/delivery-notifications', [DeliveryNotificationController::class, 'index']);
$router->get('/delivery-notifications/create', [DeliveryNotificationController::class, 'create']);
$router->get('/delivery-notifications/edit', [DeliveryNotificationController::class, 'edit']);
$router->post('/delivery-notifications/store', [DeliveryNotificationController::class, 'store']);
$router->post('/delivery-notifications/update', [DeliveryNotificationController::class, 'update']);
$router->post('/delivery-notifications/notify-company', [DeliveryNotificationController::class, 'notifyCompany']);
$router->post('/delivery-notifications/add-note', [DeliveryNotificationController::class, 'addNote']);
$router->post('/delivery-notifications/cancel', [DeliveryNotificationController::class, 'cancel']);

$router->get('/product-operations', [IncomingProductController::class, 'productOperations']);
$router->get('/product-operations/pre-notifications', [IncomingProductController::class, 'productPreNotifications']);
$router->get('/product-operations/entry', [IncomingProductController::class, 'productEntry']);
$router->get('/incoming-products', [IncomingProductController::class, 'index']);
$router->post('/incoming-products/start-pre-notified', [IncomingProductController::class, 'startPreNotified']);
$router->post('/incoming-products/direct', [IncomingProductController::class, 'storeDirect']);

$router->get('/weighbridge-entry', [WeighbridgeEntryController::class, 'index']);
$router->post('/weighbridge-entry/open-barrier', [WeighbridgeEntryController::class, 'openBarrier']);
$router->post('/weighbridge-entry/mark-on-scale', [WeighbridgeEntryController::class, 'markOnScale']);
$router->post('/weighbridge-entry/save-first-weight', [WeighbridgeEntryController::class, 'saveFirstWeight']);
$router->post('/weighbridge-entry/rollback', [WeighbridgeEntryController::class, 'rollback']);

$router->get('/sample-analysis', [SampleAnalysisController::class, 'index']);
$router->get('/sample-analysis/edit', [SampleAnalysisController::class, 'edit']);
$router->get('/sample-analysis/rejection-print', [SampleAnalysisController::class, 'rejectionPrint']);
$router->post('/sample-analysis/save', [SampleAnalysisController::class, 'save']);
$router->post('/sample-analysis/manual-silo', [SampleAnalysisController::class, 'manualSilo']);

$router->get('/silos', [SiloController::class, 'index']);
$router->get('/silos/create', [SiloController::class, 'create']);
$router->get('/silos/edit', [SiloController::class, 'edit']);
$router->get('/silos/next-code', [SiloController::class, 'nextCode']);
$router->post('/silos/store', [SiloController::class, 'store']);
$router->post('/silos/update', [SiloController::class, 'update']);
$router->post('/silos/toggle-status', [SiloController::class, 'toggleStatus']);
$router->post('/silos/delete', [SiloController::class, 'delete']);
$router->post('/silos/destroy', [SiloController::class, 'destroy']);

$router->get('/silo-rules', [SiloRuleController::class, 'index']);
$router->get('/silo-rules/create', [SiloRuleController::class, 'create']);
$router->get('/silo-rules/edit', [SiloRuleController::class, 'edit']);
$router->post('/silo-rules/store', [SiloRuleController::class, 'store']);
$router->post('/silo-rules/update', [SiloRuleController::class, 'update']);
$router->post('/silo-rules/toggle-status', [SiloRuleController::class, 'toggleStatus']);
$router->post('/silo-rules/delete', [SiloRuleController::class, 'delete']);

$router->get('/barcode-tickets', [BarcodeTicketController::class, 'index']);
$router->get('/barcode-tickets/print', [BarcodeTicketController::class, 'print']);
$router->get('/barcode-tickets/lookup', [BarcodeTicketController::class, 'lookup']);
$router->post('/barcode-tickets/generate', [BarcodeTicketController::class, 'generate']);
$router->post('/barcode-tickets/assign-silo', [BarcodeTicketController::class, 'assignSilo']);

$router->get('/unloading-operations', [UnloadingOperationController::class, 'index']);
$router->get('/outbound-loadings', [OutboundLoadingController::class, 'index']);
$router->post('/outbound-loadings/store', [OutboundLoadingController::class, 'store']);
$router->post('/outbound-loadings/first-weight', [OutboundLoadingController::class, 'firstWeight']);
$router->post('/outbound-loadings/assign-silo', [OutboundLoadingController::class, 'assignSilo']);
$router->post('/outbound-loadings/start-arrived', [OutboundLoadingController::class, 'startArrived']);
$router->post('/outbound-loadings/send-to-second-weighing', [OutboundLoadingController::class, 'sendToSecondWeighing']);
$router->post('/outbound-loadings/cancel', [OutboundLoadingController::class, 'cancel']);

$router->get('/second-weighing', [SecondWeighingController::class, 'index']);
$router->post('/second-weighing/complete', [SecondWeighingController::class, 'complete']);

$router->get('/reports', [ReportController::class, 'index']);
$router->get('/reports/data', [ReportController::class, 'data']);
$router->get('/reports/export', [ReportController::class, 'export']);

$router->get('/settings', [SettingsController::class, 'index']);
$router->post('/settings/company', [SettingsController::class, 'saveCompany']);
$router->post('/settings/system', [SettingsController::class, 'saveSystem']);
$router->post('/settings/camera', [SettingsController::class, 'saveCamera']);
$router->post('/settings/scale', [SettingsController::class, 'saveScale']);
$router->post('/settings/barrier', [SettingsController::class, 'saveBarrier']);
$router->post('/settings/toggle', [SettingsController::class, 'toggle']);
$router->post('/settings/test', [SettingsController::class, 'test']);

$router->get('/vehicle-process/detail', [VehicleProcessController::class, 'detail']);
$router->get('/vehicle-process/list', [VehicleProcessController::class, 'list']);
$router->get('/outbound-process/detail', [OutboundProcessController::class, 'detail']);
$router->get('/outbound-process/list', [OutboundProcessController::class, 'list']);
$router->get('/driver-vehicle/lookup', [DriverVehicleController::class, 'lookup']);

$router->get('/process-repair', [ProcessRepairController::class, 'index']);
$router->post('/process-repair/repair', [ProcessRepairController::class, 'repair']);

$router->get('/security-recovery', [SecurityRecoveryController::class, 'index']);
$router->post('/security-recovery/reset-password', [SecurityRecoveryController::class, 'resetPassword']);
$router->post('/security-recovery/toggle-status', [SecurityRecoveryController::class, 'toggleStatus']);
$router->post('/security-recovery/unlock', [SecurityRecoveryController::class, 'unlock']);
$router->post('/security-recovery/assign-role', [SecurityRecoveryController::class, 'assignRole']);
