<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SuperAdmin\AdminAuthController;
use App\Http\Controllers\SuperAdmin\AdminDashboardController;
use App\Http\Controllers\SuperAdmin\DepartmentController;
use App\Http\Controllers\SuperAdmin\DesignationController;
use App\Http\Controllers\SuperAdmin\MemberController;
use App\Http\Controllers\SuperAdmin\RolesController;
use App\Http\Controllers\SuperAdmin\ResumeController as SuperResumeController;
use App\Http\Controllers\Admin\ResumeController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

/** SUPER ADMIN ROUTES START HERE **/
Route::prefix('super')->name('super.')->group(function () {
    // Public routes (no auth required)
    Route::middleware('authorized:superadmin')->group(function () {
        Route::get('/login', [AdminAuthController::class, 'login'])->name('login');
        Route::post('/verify', [AdminAuthController::class, 'verify'])->name('verify');
        Route::get('/forgot-password', [AdminAuthController::class, 'forgotPassword'])->name('forgotPassword');
    });

    // Protected routes (auth required)
    Route::middleware('auth.superadmin')->group(function () {
        // Profile routes
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
        Route::post('/logout', [AdminDashboardController::class, 'logout'])->name('logout');
        Route::get('/profile', [AdminDashboardController::class, 'userProfile'])->name('profile');
        Route::post('/profile-update', [AdminDashboardController::class, 'userProfileUpdate'])->name('profile.update');
        Route::post('/profile/photo/update', [AdminDashboardController::class, 'userProfilePhotoUpdate'])->name('profile.photo.update');
        Route::post('/profile/photo/remove', [AdminDashboardController::class, 'userProfilePhotoRemove'])->name('profile.photo.remove');
        Route::post('/profile/password/update', [AdminDashboardController::class, 'userProfilePasswordUpdate'])->name('profile.password.update');

        // Resume Builder routes
        Route::get('/resumes', [SuperResumeController::class, 'index'])->name('resumes.index');
        Route::get('/resumes/create', [SuperResumeController::class, 'create'])->name('resumes.create');
        Route::post('/resumes', [SuperResumeController::class, 'store'])->name('resumes.store');
        Route::get('/resumes/{resume}', [SuperResumeController::class, 'show'])->name('resumes.show');
        Route::get('/resumes/{resume}/edit', [SuperResumeController::class, 'edit'])->name('resumes.edit');
        Route::post('/resumes/{resume}', [SuperResumeController::class, 'update'])->name('resumes.update');
        Route::delete('/resumes/{resume}', [SuperResumeController::class, 'destroy'])->name('resumes.destroy');

        // Departments Route
        Route::get('/departments', [DepartmentController::class, 'departments'])->name('departments');
        Route::post('/departments/add', [DepartmentController::class, 'addDepartments'])->name('add.departments');
        Route::put('/update-departments/{uuid?}', [DepartmentController::class, 'addDepartments'])->name('update.departments');
        Route::post('/update-status/{uuid}', [DepartmentController::class, 'updateStatus'])->name('status.departments');
        Route::delete('departments/{id}', [DepartmentController::class, 'destroy'])->name('department.destroy');

        // Roles Route
        Route::prefix('role')->name('role.')->group(function () {
            Route::get('/list', [RolesController::class, 'list'])->name('list');
            Route::post('/add', [RolesController::class, 'addRole'])->name('add');
            Route::put('/{uuid}/update', [RolesController::class, 'addRole'])->name('update');
            Route::delete('/{id}', [RolesController::class, 'destroy'])->name('destroy');
            Route::post('/update-status/{uuid}', [RolesController::class, 'updateStatus'])->name('status');
        });

        // Designation Routes
        Route::group(['prefix' => 'designation', 'as' => 'designation.'], function () {
            Route::get('/list', [DesignationController::class, 'index'])->name('list');
            Route::post('/store', [DesignationController::class, 'store'])->name('store');
            Route::put('/update/{id}', [DesignationController::class, 'store'])->name('update');
            Route::delete('/{id}', [DesignationController::class, 'destroy'])->name('destroy');
            Route::post('/update-status/{uuid}', [DesignationController::class, 'updateStatus'])->name('status');
        });

        Route::get('/designations/by-departments', [MemberController::class, 'getByDepartments'])->name('designations.by_departments');

        Route::group(['prefix' => 'members', 'as' => 'members.'], function () {
            Route::get('/list', [MemberController::class, 'index'])->name('list');
            Route::post('/store', [MemberController::class, 'store'])->name('store');
            Route::put('/update/{id}', [MemberController::class, 'store'])->name('update');
            Route::delete('/{uuid}', [MemberController::class, 'destroy'])->name('destroy');
            Route::post('/update-status/{uuid}', [MemberController::class, 'updateStatus'])->name('status');
            Route::put('/{member}/password', [MemberController::class, 'updatePassword'])->name('password');
            Route::get('/{uuid}/details', [MemberController::class, 'memberDetails'])->name('details');
        });
    });
});
/** SUPER ADMIN ROUTES END HERE **/

/** ADMIN ROUTES START HERE **/
Route::prefix('admin')->middleware(['admin'])->group(function () {
    // Profile routes
    Route::get('/dashboard', [App\Http\Controllers\Admin\AdminController::class, 'dashboard'])->name('admin.dashboard');
    Route::get('/resumes', [ResumeController::class, 'index'])->name('admin.resumes.index');
    Route::get('/resumes/create', [ResumeController::class, 'create'])->name('admin.resumes.create');
    Route::post('/resumes', [ResumeController::class, 'store'])->name('admin.resumes.store');
    Route::get('/resumes/{resume}', [ResumeController::class, 'show'])->name('admin.resumes.show');
    Route::get('/resumes/{resume}/edit', [ResumeController::class, 'edit'])->name('admin.resumes.edit');
    Route::post('/resumes/{resume}', [ResumeController::class, 'update'])->name('admin.resumes.update');
    Route::delete('/resumes/{resume}', [ResumeController::class, 'destroy'])->name('admin.resumes.destroy');
    Route::post('/logout', [App\Http\Controllers\Admin\AdminController::class, 'logout'])->name('admin.logout');
    Route::get('/profile', [App\Http\Controllers\Admin\AdminController::class, 'userProfile'])->name('admin.profile');
    Route::post('/profile-update', [App\Http\Controllers\Admin\AdminController::class, 'userProfileUpdate'])->name('admin.profile.update');
    Route::post('/profile/photo/update', [App\Http\Controllers\Admin\AdminController::class, 'userProfilePhotoUpdate'])->name('admin.profile.photo.update');
    Route::post('/profile/password/update', [App\Http\Controllers\Admin\AdminController::class, 'userProfilePasswordUpdate'])->name('admin.profile.password.update');
    Route::post('/profile/photo/remove', [App\Http\Controllers\Admin\AdminController::class, 'userProfilePhotoRemove'])->name('admin.profile.photo.remove');
});
/** ADMIN ROUTES END HERE **/

/** MEMBER ROUTES START HERE **/
Route::prefix('member')->middleware(['member'])->group(function () {
    // Profile routes
    Route::get('/dashboard', [App\Http\Controllers\Admin\AdminController::class, 'dashboard'])->name('member.dashboard');
    Route::post('/logout', [App\Http\Controllers\Admin\AdminController::class, 'logout'])->name('member.logout');
    Route::get('/profile', [App\Http\Controllers\Member\MemberTaskController::class, 'userProfile'])->name('member.profile');
    Route::post('/profile-update', [App\Http\Controllers\Member\MemberTaskController::class, 'userProfileUpdate'])->name('member.profile.update');
    Route::post('/profile/photo/update', [App\Http\Controllers\Member\MemberTaskController::class, 'userProfilePhotoUpdate'])->name('member.profile.photo.update');
    Route::post('/profile/password/update', [App\Http\Controllers\Member\MemberTaskController::class, 'userProfilePasswordUpdate'])->name('member.profile.password.update');
    Route::post('/profile/photo/remove', [App\Http\Controllers\Member\MemberTaskController::class, 'userProfilePhotoRemove'])->name('member.profile.photo.remove');
});
/** MEMBER ROUTES END HERE **/

/** PUBLIC ROUTES START HERE **/
Route::get('/', [HomeController::class, 'authShowPage'])->name('home');
Route::get('/login', [HomeController::class, 'login'])->name('login');
Route::post('/verify', [HomeController::class, 'AuthLogin'])->name('auth.login');
Route::get('/admin/login', [HomeController::class, 'login'])->name('admin.login');
Route::get('/member/login', [HomeController::class, 'login'])->name('doer.login');

// Password Reset Routes
Route::get('/forget-password', [HomeController::class, 'forgetPassword'])->name('password.request');
Route::get('/super-forget-password', [HomeController::class, 'superForgetPassword'])->name('super.password.request');
Route::post('/check-email', [HomeController::class, 'checkEmail'])->name('auth.checkEmail');
Route::post('/super-check-email', [HomeController::class, 'superCheckEmail'])->name('super.auth.checkEmail');
Route::post('/forgot-password', [HomeController::class, 'sendResetLink'])->name('password.email');
Route::post('/super-forgot-password', [HomeController::class, 'superSendResetLink'])->name('super.password.email');
Route::get('/reset-password/{token}', [HomeController::class, 'showResetForm'])->name('password.reset');
Route::get('/super-reset-password/{token}', [HomeController::class, 'superShowResetForm'])->name('super.password.reset');
Route::post('/reset-password', [HomeController::class, 'resetPassword'])->name('password.update');
Route::post('/super-reset-password', [HomeController::class, 'superResetPassword'])->name('super.password.update');
/** PUBLIC ROUTES END HERE **/

/** UTILITY ROUTES START HERE **/
Route::get('/migrate', function () {
    Artisan::call('migrate');
    return response()->json(['migrated']);
});

Route::get('seed', function () {
    Artisan::call('db:seed');
    return response()->json(['seeded']);
});

Route::get('/clear', function () {
    Artisan::call('cache:clear');
    Artisan::call('route:cache');
    Artisan::call('view:clear');
    Artisan::call('optimize:clear');
    return 'Application cache has been cleared';
});
/** UTILITY ROUTES END HERE **/
