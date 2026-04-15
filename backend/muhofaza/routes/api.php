<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\ExamResultController;
use App\Http\Controllers\Api\ExamTypeController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\OrganizationController;
use App\Http\Controllers\Api\PeriodicExamController;
use App\Http\Controllers\Api\PositionController;
use App\Http\Controllers\Api\RetakeController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

// Auth routes
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    // Dashboard
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
    Route::get('/dashboard/upcoming-exams', [DashboardController::class, 'upcomingExams']);
    Route::get('/dashboard/overdue-exams', [DashboardController::class, 'overdueExams']);
    Route::get('/dashboard/upcoming-employees', [DashboardController::class, 'upcomingEmployees']);

    // Organizations (admin only for create/update/delete)
    Route::get('/organizations', [OrganizationController::class, 'index']);
    Route::get('/organizations/{id}', [OrganizationController::class, 'show']);
    Route::get('/organizations/{id}/employees', [OrganizationController::class, 'employees']);
    Route::get('/organizations/{id}/exams', [OrganizationController::class, 'exams']);
    Route::middleware('role:admin')->group(function () {
        Route::post('/organizations', [OrganizationController::class, 'store']);
        Route::put('/organizations/{id}', [OrganizationController::class, 'update']);
        Route::delete('/organizations/{id}', [OrganizationController::class, 'destroy']);
    });

    // Departments
    Route::apiResource('departments', DepartmentController::class);

    // Positions
    Route::apiResource('positions', PositionController::class);

    // Employees
    Route::get('/employees/{id}/exam-history', [EmployeeController::class, 'examHistory']);
    Route::get('/employees/{id}/upcoming-exams', [EmployeeController::class, 'upcomingExams']);
    Route::apiResource('employees', EmployeeController::class);

    // Exam Types
    Route::get('/exam-types/by-month', [ExamTypeController::class, 'byMonth']);
    Route::get('/exam-types/calendar', [ExamTypeController::class, 'calendar']);
    Route::apiResource('exam-types', ExamTypeController::class);

    // Reports
    Route::prefix('reports')->group(function () {
        Route::get('employees', [ReportController::class, 'employees']);
        Route::get('exams', [ReportController::class, 'exams']);
        Route::get('organizations', [ReportController::class, 'organizations']);
        Route::get('annual-plan', [ReportController::class, 'annualPlan']);
        Route::get('export/pdf', [ReportController::class, 'exportPdf']);
    });

    // Orders
    Route::apiResource('orders', OrderController::class);

    // Periodic Exams
    Route::post('/periodic-exams/{id}/start', [PeriodicExamController::class, 'start']);
    Route::post('/periodic-exams/{id}/complete', [PeriodicExamController::class, 'complete']);
    Route::get('/periodic-exams/{id}/employees', [PeriodicExamController::class, 'examEmployees']);
    Route::apiResource('periodic-exams', PeriodicExamController::class);

    // Exam Results
    Route::get('/exam-results/unsatisfactory', [ExamResultController::class, 'unsatisfactory']);
    Route::post('/exam-results/bulk', [ExamResultController::class, 'bulk']);
    Route::apiResource('exam-results', ExamResultController::class);

    // Retakes
    Route::get('/retakes', [RetakeController::class, 'index']);
    Route::post('/retakes/{result_id}', [RetakeController::class, 'store']);

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);

    // Users (admin only)
    Route::middleware('role:admin')->apiResource('users', UserController::class);
});
