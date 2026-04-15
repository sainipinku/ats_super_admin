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
use App\Http\Controllers\SuperAdmin\JobRequestController;
use App\Http\Controllers\Admin\ResumeController;
use App\Http\Controllers\Admin\JobController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/** SUPER ADMIN ROUTES START HERE **/
Route::prefix('super')->name('super.')->group(function () {
    // Public routes (no auth required)
    Route::middleware('authorized:superadmin')->group(function () {
        Route::redirect('/login', '/login');
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

        // Job Requests Routes (Super Admin)
        Route::group(['prefix' => 'job-requests', 'as' => 'job.requests.'], function () {
            Route::get('/', [JobRequestController::class, 'index'])->name('index');
            Route::get('/all-jobs', [JobRequestController::class, 'allJobs'])->name('all.jobs');
            Route::get('/api/all', [JobRequestController::class, 'getAllRequests'])->name('api.all');
            Route::get('/api/pending', [JobRequestController::class, 'getPendingRequests'])->name('api.pending');
            Route::get('/api/statistics', [JobRequestController::class, 'getStatistics'])->name('api.statistics');
            Route::get('/api/{job}', [JobRequestController::class, 'show'])->name('api.show');
            Route::patch('/api/{job}/approve', [JobRequestController::class, 'approve'])->name('api.approve');
            Route::patch('/api/{job}/reject', [JobRequestController::class, 'reject'])->name('api.reject');
            Route::patch('/api/{job}/request-changes', [JobRequestController::class, 'requestChanges'])->name('api.request_changes');
            Route::post('/api/{job}/update', [JobRequestController::class, 'update'])->name('api.update');
            Route::patch('/api/{job}/toggle-status', [JobRequestController::class, 'toggleStatus'])->name('api.toggle-status');
            Route::patch('/api/{job}/close', [JobRequestController::class, 'close'])->name('api.close');
            Route::delete('/api/{job}', [JobRequestController::class, 'destroy'])->name('api.destroy');
        });
    });
});
/** SUPER ADMIN ROUTES END HERE **/

/** ADMIN ROUTES START HERE **/
Route::prefix('admin')->middleware(['admin'])->group(function () {
    // Profile routes
    Route::get('/dashboard', [App\Http\Controllers\Admin\AdminController::class, 'dashboard'])->name('admin.dashboard');
    Route::get('/tasks/dashboard', [App\Http\Controllers\Admin\AdminController::class, 'dashboard'])->name('admin.task.dashboard');
    Route::get('/tasks/tasklist', [App\Http\Controllers\Admin\AdminController::class, 'dashboard'])->name('admin.task.tasklist');

    Route::get('/members/dashboard', [App\Http\Controllers\Admin\AdminMemberController::class, 'dashboard'])->name('admin.members.dashboard');
    Route::post('/members/{member}/update-status', [App\Http\Controllers\Admin\AdminMemberController::class, 'updateStatus'])->name('admin.members.update-status');
    Route::get('/members/{uuid}/details', [App\Http\Controllers\Admin\AdminMemberController::class, 'memberDetails'])->name('admin.members.details');

    Route::post('/checkin', [App\Http\Controllers\Member\CheckInOutController::class, 'checkIn'])->name('admin.checkin');
    Route::post('/checkout', [App\Http\Controllers\Member\CheckInOutController::class, 'checkOut'])->name('admin.checkout');
    
    Route::get('/resumes', [ResumeController::class, 'index'])->name('admin.resumes.index');
    Route::get('/resumes/create', [ResumeController::class, 'create'])->name('admin.resumes.create');
    Route::post('/resumes', [ResumeController::class, 'store'])->name('admin.resumes.store');
    Route::get('/resumes/{resume}', [ResumeController::class, 'show'])->name('admin.resumes.show');
    Route::get('/resumes/{resume}/edit', [ResumeController::class, 'edit'])->name('admin.resumes.edit');
    Route::post('/resumes/{resume}', [ResumeController::class, 'update'])->name('admin.resumes.update');
    Route::delete('/resumes/{resume}', [ResumeController::class, 'destroy'])->name('admin.resumes.destroy');
    
    // Job Posts routes
    Route::get('/job-posts', [JobController::class, 'index'])->name('admin.job.posts.index');
    Route::get('/job-listing', [JobController::class, 'listing'])->name('admin.job.posts.listing');
    
    // Job API routes
    Route::get('/api/jobs', [JobController::class, 'getAdminJobs'])->name('admin.api.jobs.list');
    Route::post('/api/jobs', [JobController::class, 'store'])->name('admin.api.jobs.store');
    Route::post('/api/jobs/{job}', [JobController::class, 'update'])->name('admin.api.jobs.update');
    Route::delete('/api/jobs/{job}', [JobController::class, 'destroy'])->name('admin.api.jobs.destroy');
    Route::patch('/api/jobs/{job}/resend', [JobController::class, 'resend'])->name('admin.api.jobs.resend');
    Route::patch('/api/jobs/{job}/toggle-status', [JobController::class, 'toggleStatus'])->name('admin.api.jobs.toggle-status');
    
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
    Route::get('/tasks/dashboard', [App\Http\Controllers\Member\MemberTaskController::class, 'dashboard'])->name('member.task.dashboard');
    Route::get('/tasks/tasklist', [App\Http\Controllers\Member\MemberTaskController::class, 'taskList'])->name('member.task.tasklist');

    Route::post('/checkin', [App\Http\Controllers\Member\CheckInOutController::class, 'checkIn'])->name('member.checkin');
    Route::post('/checkout', [App\Http\Controllers\Member\CheckInOutController::class, 'checkOut'])->name('member.checkout');
    Route::post('/logout', [App\Http\Controllers\Admin\AdminController::class, 'logout'])->name('member.logout');
    Route::get('/profile', [App\Http\Controllers\Member\MemberTaskController::class, 'userProfile'])->name('member.profile');
    Route::post('/profile-update', [App\Http\Controllers\Member\MemberTaskController::class, 'userProfileUpdate'])->name('member.profile.update');
    Route::post('/profile/photo/update', [App\Http\Controllers\Member\MemberTaskController::class, 'userProfilePhotoUpdate'])->name('member.profile.photo.update');
    Route::post('/profile/password/update', [App\Http\Controllers\Member\MemberTaskController::class, 'userProfilePasswordUpdate'])->name('member.profile.password.update');
    Route::post('/profile/photo/remove', [App\Http\Controllers\Member\MemberTaskController::class, 'userProfilePhotoRemove'])->name('member.profile.photo.remove');

    // Candidate Job Portal Routes
    Route::get('/jobs', [App\Http\Controllers\Member\CandidateJobController::class, 'index'])->name('member.jobs.index');
    Route::get('/jobs/{job}', [App\Http\Controllers\Member\CandidateJobController::class, 'show'])->name('member.jobs.show');
    Route::post('/jobs/{job}/apply', [App\Http\Controllers\Member\CandidateJobController::class, 'apply'])->name('member.jobs.apply');
    Route::get('/my-applications', [App\Http\Controllers\Member\CandidateJobController::class, 'myApplications'])->name('member.applications.index');
    Route::delete('/applications/{application}/withdraw', [App\Http\Controllers\Member\CandidateJobController::class, 'withdraw'])->name('member.applications.withdraw');
});
/** MEMBER ROUTES END HERE **/

/** PUBLIC ROUTES START HERE **/
Route::get('/', [HomeController::class, 'authShowPage'])->name('home');
Route::get('/login', [AdminAuthController::class, 'login'])->name('login');
Route::post('/verify', [AdminAuthController::class, 'verify'])->name('auth.login');
Route::redirect('/admin/login', '/login')->name('admin.login');
Route::redirect('/member/login', '/login')->name('doer.login');

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
