<?php

use App\Http\Controllers\AdminActivityController;
use App\Http\Controllers\AdminDocumentController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DocumentPdfController;
use App\Http\Controllers\DocumentWorkflowController;
use App\Http\Controllers\DocumentWorkflowSettingController;
use App\Http\Controllers\IncomingDocumentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SignatureController;
use App\Http\Controllers\VerificationController;
use App\Http\Middleware\RecordActivity;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');

    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('login.store');
});

Route::middleware(['auth', RecordActivity::class])->group(function (): void {
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::get('/profile/edit', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/profile/password', [ProfileController::class, 'editPassword'])->name('profile.password.edit');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::prefix('admin')
        ->name('admin.')
        ->group(function (): void {
            Route::get('/documents', [AdminDocumentController::class, 'index'])->name('documents.index');

            Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');

            Route::get('/users/create', [AdminUserController::class, 'create'])->name('users.create');

            Route::post('/users', [AdminUserController::class, 'store'])->name('users.store');

            Route::get('/users/{user}/edit', [AdminUserController::class, 'edit'])->name('users.edit');

            Route::put('/users/{user}', [AdminUserController::class, 'update'])->name('users.update');

            Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');
        });

    Route::get('/incoming-documents', [IncomingDocumentController::class, 'index'])->name('incoming-documents.index');

    Route::get('/approvals', [ApprovalController::class, 'index'])->name('approvals.index');

    Route::get('/signatures', [SignatureController::class, 'index'])->name('signatures.index');

    Route::get('/master/workflow', [DocumentWorkflowSettingController::class, 'edit'])->name('workflow-settings.edit');

    Route::get('/master/workflow/group/{type}', [DocumentWorkflowSettingController::class, 'edit'])
        ->whereIn('type', ['destination', 'approver', 'signer'])
        ->name('workflow-settings.group');

    Route::post('/master/workflow', [DocumentWorkflowSettingController::class, 'store'])->name(
        'workflow-settings.store'
    );

    Route::get('/master/workflow/{workflowMasterEntry}/edit', [
        DocumentWorkflowSettingController::class,
        'editEntry',
    ])->name('workflow-settings.entry.edit');

    Route::put('/master/workflow/{workflowMasterEntry}', [
        DocumentWorkflowSettingController::class,
        'updateEntry',
    ])->name('workflow-settings.entry.update');

    Route::delete('/master/workflow/{workflowMasterEntry}', [
        DocumentWorkflowSettingController::class,
        'destroyEntry',
    ])->name('workflow-settings.entry.destroy');

    Route::post('/documents/{document}/submit', [DocumentWorkflowController::class, 'submit'])->name(
        'documents.submit'
    );

    Route::post('/documents/{document}/approver', [DocumentWorkflowController::class, 'assignApprover'])->name(
        'documents.assign-approver'
    );

    Route::post('/documents/{document}/approve', [DocumentWorkflowController::class, 'approve'])->name(
        'documents.approve'
    );

    Route::post('/documents/{document}/reject', [DocumentWorkflowController::class, 'reject'])->name(
        'documents.reject'
    );

    Route::post('/documents/{document}/revise', [DocumentWorkflowController::class, 'revise'])->name(
        'documents.revise'
    );

    Route::post('/documents/{document}/revision', [DocumentController::class, 'uploadRevision'])->name(
        'documents.revision.upload'
    );

    Route::post('/documents/{document}/signer', [DocumentWorkflowController::class, 'assignSigner'])->name(
        'documents.assign-signer'
    );

    Route::resource('documents', DocumentController::class);

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});

Route::middleware(['auth', RecordActivity::class, 'throttle:30,1'])->group(function (): void {
    Route::get('/documents/{document}/pdf-workflow', [DocumentPdfController::class, 'edit'])->name(
        'documents.pdf.edit'
    );
    Route::get('/documents/{document}/pdf-review', [DocumentPdfController::class, 'review'])->name(
        'documents.pdf.review'
    );
    Route::get('/documents/{document}/pdf-review/file', [DocumentPdfController::class, 'reviewFile'])->name(
        'documents.pdf.review-file'
    );
    Route::post('/documents/{document}/pdf-workflow', [DocumentPdfController::class, 'store'])->name(
        'documents.pdf.store'
    );
    Route::put('/documents/{document}/positions', [DocumentPdfController::class, 'place'])->name('documents.pdf.place');
    Route::get('/documents/{document}/page-preview', [DocumentPdfController::class, 'preview'])->name(
        'documents.pdf.preview'
    );
    Route::get('/documents/{document}/preview', [DocumentPdfController::class, 'viewer'])->name('documents.pdf.viewer');
    Route::post('/documents/{document}/send', [DocumentPdfController::class, 'send'])->name('documents.send');
    Route::get('/documents/{document}/download', [DocumentPdfController::class, 'download'])->name(
        'documents.pdf.download'
    );
    Route::post('/documents/{document}/sign', [DocumentPdfController::class, 'sign'])
        ->middleware('throttle:5,1,signing')
        ->name('documents.sign');
});
Route::get('/verify/{publicId}', [VerificationController::class, 'show'])
    ->whereUuid('publicId')
    ->middleware('throttle:60,1')
    ->name('verification.show');
Route::post('/verify/{publicId}', [VerificationController::class, 'compare'])
    ->whereUuid('publicId')
    ->middleware('throttle:20,1')
    ->name('verification.compare');

Route::get('/admin/activity', [AdminActivityController::class, 'index'])
    ->middleware('auth')
    ->name('admin.activity.index');
