<?php

use App\Http\Controllers\AiProviderController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PrdController;
use App\Http\Controllers\ProjectContextController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\RequirementController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
});

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);
    Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('register', [RegisteredUserController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    // Projects
    Route::resource('projects', ProjectController::class)->only(['index', 'store', 'show', 'update', 'destroy']);
    Route::post('projects/{project}/transition', [ProjectController::class, 'transition'])->name('projects.transition');

    // Conversation (SSE + fallback)
    Route::get('projects/{project}/conversations/{conversation}/messages', [ConversationController::class, 'messages'])->name('conversations.messages');
    Route::post('projects/{project}/conversations/{conversation}/send', [ConversationController::class, 'send'])->middleware('throttle.ai:chat')->name('conversations.send');
    Route::post('projects/{project}/conversations/{conversation}/stream', [ConversationController::class, 'stream'])->middleware('throttle.ai:chat')->name('conversations.stream');
    Route::post('projects/{project}/conversations/{conversation}/extract', [ConversationController::class, 'extract'])->middleware('throttle.ai:extract')->name('conversations.extract');

    // Project context + requirements
    Route::get('projects/{project}/context', [ProjectContextController::class, 'show'])->name('projects.context.show');
    Route::put('projects/{project}/context', [ProjectContextController::class, 'update'])->name('projects.context.update');
    Route::get('projects/{project}/requirements', [RequirementController::class, 'index'])->name('projects.requirements.index');
    Route::post('projects/{project}/requirements', [RequirementController::class, 'store'])->name('projects.requirements.store');
    Route::put('projects/{project}/requirements/{requirement}', [RequirementController::class, 'update'])->name('projects.requirements.update');
    Route::delete('projects/{project}/requirements/{requirement}', [RequirementController::class, 'destroy'])->name('projects.requirements.destroy');

    // PRD workspace
    Route::get('projects/{project}/prd', [PrdController::class, 'show'])->name('projects.prd.show');
    Route::post('projects/{project}/prd/generate', [PrdController::class, 'generate'])->middleware('throttle.ai:generate')->name('projects.prd.generate');
    Route::get('projects/{project}/prd/status', [PrdController::class, 'status'])->name('projects.prd.status');
    Route::get('projects/{project}/prd/export', [PrdController::class, 'export'])->name('projects.prd.export');
    Route::get('projects/{project}/prd/export-pdf', [PrdController::class, 'exportPdf'])->name('projects.prd.exportPdf');
    Route::put('projects/{project}/prd', [PrdController::class, 'update'])->name('projects.prd.update');
    Route::put('projects/{project}/prd/sections/order', [PrdController::class, 'reorderSections'])->name('projects.prd.sections.reorder');
    Route::put('projects/{project}/prd/sections/{section}', [PrdController::class, 'updateSection'])->name('projects.prd.sections.update');
    Route::delete('projects/{project}/prd/sections/{section}', [PrdController::class, 'destroySection'])->name('projects.prd.sections.destroy');
    Route::post('projects/{project}/prd/sections/{section}/ai', [PrdController::class, 'sectionAction'])->middleware('throttle.ai:action')->name('projects.prd.sections.ai');
    Route::post('projects/{project}/prd/sections/{section}/apply', [PrdController::class, 'applySectionProposal'])->name('projects.prd.sections.apply');
    Route::post('projects/{project}/prd/review', [PrdController::class, 'review'])->middleware('throttle.ai:review')->name('projects.prd.review');
    Route::post('projects/{project}/prd/versions', [PrdController::class, 'storeVersion'])->name('projects.prd.versions.store');
    Route::get('projects/{project}/prd/versions/{versionId}', [PrdController::class, 'showVersion'])->name('projects.prd.versions.show');

    // AI providers
    Route::get('settings/providers', fn () => inertia('Settings/Providers'))->name('settings.providers');
    Route::get('ai/providers', [AiProviderController::class, 'index'])->name('ai.providers.index');
    Route::post('ai/providers', [AiProviderController::class, 'store'])->name('ai.providers.store');
    Route::put('ai/providers/{provider}', [AiProviderController::class, 'update'])->name('ai.providers.update');
    Route::delete('ai/providers/{provider}', [AiProviderController::class, 'destroy'])->name('ai.providers.destroy');
    Route::post('ai/providers/{provider}/test', [AiProviderController::class, 'test'])->middleware('throttle.ai:test')->name('ai.providers.test');
    Route::post('ai/providers/test', [AiProviderController::class, 'test'])->middleware('throttle.ai:test')->name('ai.providers.test-unsaved');
    Route::get('ai/providers/{provider}/models', [AiProviderController::class, 'models'])->name('ai.providers.models');
});
