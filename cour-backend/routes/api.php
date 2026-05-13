<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\UserController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\ResetPasswordController;

use App\Http\Controllers\Api\CustomerShipmentController;
use App\Http\Controllers\Api\BranchController;
use App\Http\Controllers\Api\Admin\AdminShipmentController;

use App\Http\Controllers\Api\AssignmentController;
use App\Http\Controllers\Api\AgentController;
use App\Http\Controllers\Api\Admin\InvoiceController;
use App\Http\Controllers\Api\Admin\NotificationController;
use App\Http\Controllers\Api\Admin\ReportController;




/*
|--------------------------------------------------------------------------
| PUBLIC
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return response()->json([
        'message' => 'CourierXpress API is running'
    ]);
});

/*
|--------------------------------------------------------------------------
| AUTH
|--------------------------------------------------------------------------
*/

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/forgot-password', [ForgotPasswordController::class, 'forgotPassword']);
Route::post('/reset-password', [ResetPasswordController::class, 'resetPassword']);

/*
|--------------------------------------------------------------------------
| CUSTOMER
|--------------------------------------------------------------------------
*/

Route::post('/customer/shipments', [CustomerShipmentController::class, 'store']);
Route::get('/tracking/{trackingCode}', [CustomerShipmentController::class, 'trackShipment']);

/*
|--------------------------------------------------------------------------
| ADMIN
|--------------------------------------------------------------------------
*/

Route::prefix('admin')->group(function () {

    Route::get('/shipments', [AdminShipmentController::class, 'index']);
    Route::post('/shipments', [AdminShipmentController::class, 'store']);
    Route::get('/shipments-show/{id}', [AdminShipmentController::class, 'show']);
    Route::get('/customers/phone/{phone}', [AdminShipmentController::class, 'lookupCustomerByPhone']);

    Route::put('/shipments/{id}', [AdminShipmentController::class, 'update']);
    // Route::delete('/shipments/{id}', [AdminShipmentController::class, 'destroy']);

    Route::put('/shipments/{id}/status', [AdminShipmentController::class, 'updateStatus']);
    Route::prefix('agents')->group(function () {
        Route::get('/', [AgentController::class, 'index']);
        Route::post('/', [AgentController::class, 'store']);
        Route::put('/{id}', [AgentController::class, 'update']);
        Route::put('/{id}/toggle', [AgentController::class, 'toggleStatus']);
        Route::delete('/{id}', [AgentController::class, 'destroy']);
    });
    Route::get('/notifications/templates', [NotificationController::class, 'index']);
    Route::post('/notifications/templates', [NotificationController::class, 'store']);
    Route::put('notifications/templates/{id}', [NotificationController::class, 'updateTemplate']);
    Route::delete('/notifications/templates/{id}', [NotificationController::class, 'destroyTemplate']);
    Route::patch('/notifications/templates/{id}/toggle', [NotificationController::class, 'toggle']);


    Route::get('/invoices', [InvoiceController::class, 'index']); // Thêm route để lấy danh sách hóa đơn
    Route::get('/reports/shipments', [ReportController::class, 'getShipments']);

    Route::get('/customers', [UserController::class, 'getCustomers']);
    Route::get('/customers/{id}', [UserController::class, 'getCustomerDetail']);
    Route::put('/customers/{id}', [UserController::class, 'updateCustomer']);

    Route::prefix('assignments')->group(function () {

        // Admin xem danh sách assign
        Route::get('/', [AssignmentController::class, 'index']);

        // Admin assign tay
        Route::post('/', [AssignmentController::class, 'store']);

        // Admin xóa assign
        Route::delete('/{id}', [AssignmentController::class, 'destroy']);

        // Auto suggest agent
        Route::post('/suggest', [AssignmentController::class, 'suggestAgent']);

        // Agent accept job
        Route::put('/{id}/accept', [AssignmentController::class, 'accept']);

        // Agent reject job
        Route::put('/{id}/reject', [AssignmentController::class, 'reject']);
    });
});

/*
|--------------------------------------------------------------------------
| AUTH PROTECTED
|--------------------------------------------------------------------------
*/

Route::middleware('auth:api')->group(function () {
    Route::get('/me', [UserController::class, 'getProfile']);
    Route::put('/me', [UserController::class, 'updateProfile']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/customer/my-shipments', [CustomerShipmentController::class, 'myShipments']);
    Route::put('/customer/shipments/{id}/cancel', [CustomerShipmentController::class, 'destroy']);

    Route::get('/my-notifications', [NotificationController::class, 'myNotifications']);

    Route::get('/notifications/{id}', [NotificationController::class, 'show']);

    Route::put('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);

    Route::put('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);


    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);

    // Admin
    Route::get('/admin/notifications', [NotificationController::class, 'allNotifications']);

    // Agent
    Route::get('/agent/my-shipments', [AdminShipmentController::class, 'agentShipments']);
});

Route::get('/branches', [BranchController::class, 'index']);
Route::post('/branches', [BranchController::class, 'store']);
Route::put('/branches/{id}', [BranchController::class, 'update']);
Route::delete('/branches/{id}', [BranchController::class, 'destroy']);

//Route::get('/shipments', [AdminShipmentController::class, 'index']); //admin/shipments
// Route::get('/branches', [BranchController::class, 'index']);
// Route::get('/agents', [AgentController::class, 'index']);

// Route::get('/assignments', [AssignmentController::class, 'index']);
// Route::post('/assignments', [AssignmentController::class, 'store']);
// Route::delete('/assignments/{id}', [AssignmentController::class, 'destroy']);

/*
|--------------------------------------------------------------------------
|  ASSIGNMENTS
|--------------------------------------------------------------------------
*/
// Route::get('/agents', [AgentController::class, 'index']);


// Route::prefix('assignments')->group(function () {
//     Route::get('/', [AssignmentController::class, 'index']);
//     Route::post('/', [AssignmentController::class, 'store']);
//     Route::delete('/{id}', [AssignmentController::class, 'destroy']);
//     Route::post('/suggest', [AssignmentController::class, 'suggestAgent']); // ← NEW
// });
/*
|--------------------------------------------------------------------------
|  AGENTS
|--------------------------------------------------------------------------
*/


