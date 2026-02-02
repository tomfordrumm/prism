<?php

use App\Http\Controllers\ChainController;
use App\Http\Controllers\ChainNodeController;
use App\Http\Controllers\DatasetController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\PromptConversationController;
use App\Http\Controllers\PromptRunController;
use App\Http\Controllers\PromptTemplateController;
use App\Http\Controllers\PromptVersionFromFeedbackController;
use App\Http\Controllers\ProviderCredentialController;
use App\Http\Controllers\RunController;
use App\Http\Controllers\SystemSettingsController;
use App\Http\Controllers\TenantController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('projects.index');
    }

    return redirect()->route('login');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/project', fn () => Inertia::render('ProjectEntry'))->name('project.entry');
    Route::get('dashboard', fn () => redirect()->route('project.entry'))->name('dashboard');
    Route::get('/playground/prompt-editor', fn () => Inertia::render('playground/PromptEditorDemo'))->name('playground.prompt-editor');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::get('/projects/create', [ProjectController::class, 'create'])->name('projects.create');
    Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
    Route::get('/settings/system', [SystemSettingsController::class, 'edit'])->name('settings.system.edit');
    Route::put('/settings/system', [SystemSettingsController::class, 'update'])->name('settings.system.update');
    Route::prefix('/projects/{project}')->group(function () {
        Route::get('/prompt-conversations', [PromptConversationController::class, 'index'])
            ->name('projects.prompt-conversations.index');
        Route::post('/prompt-conversations', [PromptConversationController::class, 'store'])
            ->name('projects.prompt-conversations.store');
        Route::get('/prompt-conversations/{conversation}', [PromptConversationController::class, 'show'])
            ->name('projects.prompt-conversations.show');
        Route::post('/prompt-conversations/{conversation}/messages', [PromptConversationController::class, 'storeMessage'])
            ->name('projects.prompt-conversations.messages.store');
        Route::get('/prompts', [PromptTemplateController::class, 'index'])->name('projects.prompts.index');
        Route::post('/prompts', [PromptTemplateController::class, 'store'])->name('projects.prompts.store');
        Route::put('/prompts/{promptTemplate}', [PromptTemplateController::class, 'update'])->name('projects.prompts.update');
        Route::post('/prompts/{promptTemplate}/versions', [PromptTemplateController::class, 'storeVersion'])->name('projects.prompts.versions.store');
        Route::post('/prompts/{promptTemplate}/versions/run', [PromptTemplateController::class, 'storeVersionAndRun'])->name('projects.prompts.versions.run');
        Route::post('/prompts/{promptTemplate}/run', [PromptRunController::class, 'store'])->name('projects.prompts.run');
        Route::post('/prompts/{promptTemplate}/run-dataset', [PromptRunController::class, 'runDataset'])->name('projects.prompts.run-dataset');

        Route::get('/chains', [ChainController::class, 'index'])->name('projects.chains.index');
        Route::get('/chains/create', [ChainController::class, 'create'])->name('projects.chains.create');
        Route::post('/chains', [ChainController::class, 'store'])->name('projects.chains.store');
        Route::get('/chains/{chain}', [ChainController::class, 'show'])->name('projects.chains.show');
        Route::put('/chains/{chain}', [ChainController::class, 'update'])->name('projects.chains.update');

        Route::post('/chains/{chain}/nodes', [ChainNodeController::class, 'store'])->name('projects.chains.nodes.store');
        Route::put('/chains/{chain}/nodes/{chainNode}', [ChainNodeController::class, 'update'])->name('projects.chains.nodes.update');
        Route::delete('/chains/{chain}/nodes/{chainNode}', [ChainNodeController::class, 'destroy'])->name('projects.chains.nodes.destroy');

        Route::post('/chains/{chain}/run', [RunController::class, 'run'])->name('projects.chains.run');
        Route::post('/chains/{chain}/run-dataset', [RunController::class, 'runDataset'])->name('projects.chains.run-dataset');

        Route::get('/runs', [RunController::class, 'index'])->name('projects.runs.index');
        Route::get('/runs/{run}', [RunController::class, 'show'])->name('projects.runs.show');
        Route::get('/runs/{run}/stream', [RunController::class, 'stream'])->name('projects.runs.stream');

        Route::get('/datasets', [DatasetController::class, 'index'])->name('projects.datasets.index');
        Route::get('/datasets/create', [DatasetController::class, 'create'])->name('projects.datasets.create');
        Route::post('/datasets', [DatasetController::class, 'store'])->name('projects.datasets.store');
        Route::get('/datasets/{dataset}', [DatasetController::class, 'show'])->name('projects.datasets.show');
        Route::put('/datasets/{dataset}', [DatasetController::class, 'update'])->name('projects.datasets.update');
        Route::delete('/datasets/{dataset}', [DatasetController::class, 'destroy'])->name('projects.datasets.destroy');
        Route::post('/datasets/{dataset}/test-cases', [DatasetController::class, 'storeTestCase'])->name('projects.datasets.test-cases.store');
        Route::put('/datasets/{dataset}/test-cases/{testCase}', [DatasetController::class, 'updateTestCase'])->name('projects.datasets.test-cases.update');
        Route::delete('/datasets/{dataset}/test-cases/{testCase}', [DatasetController::class, 'destroyTestCase'])->name('projects.datasets.test-cases.destroy');
    });

    Route::get('/providers/credentials', [ProviderCredentialController::class, 'index'])->name('provider-credentials.index');
    Route::get('/providers/credentials/create', [ProviderCredentialController::class, 'create'])->name('provider-credentials.create');
    Route::post('/providers/credentials', [ProviderCredentialController::class, 'store'])->name('provider-credentials.store');
    Route::get('/providers/credentials/{providerCredential}', [ProviderCredentialController::class, 'edit'])->name('provider-credentials.edit');
    Route::put('/providers/credentials/{providerCredential}', [ProviderCredentialController::class, 'update'])->name('provider-credentials.update');
    Route::delete('/providers/credentials/{providerCredential}', [ProviderCredentialController::class, 'destroy'])->name('provider-credentials.destroy');

    Route::post('/tenants', [TenantController::class, 'store'])->name('tenants.store');

    Route::post('/runs/{run}/steps/{runStep}/feedback', [FeedbackController::class, 'store'])->name('runs.steps.feedback.store');
    Route::post('/projects/{project}/prompts/{promptTemplate}/versions/from-feedback', PromptVersionFromFeedbackController::class)->name('projects.prompts.versions.from-feedback');
});

require __DIR__.'/settings.php';
