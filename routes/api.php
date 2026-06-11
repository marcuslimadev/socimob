<?php

use App\Http\Controllers\Api\Atendimento\ConversationController;
use App\Http\Controllers\Api\Atendimento\ConversationEventController;
use App\Http\Controllers\Api\Atendimento\ConversationMessageController;
use App\Http\Controllers\Api\Atendimento\ConversationProposalController;
use App\Http\Controllers\Api\Atendimento\ConversationTaskController;
use App\Http\Controllers\Api\Atendimento\ConversationVisitController;
use App\Http\Controllers\Api\Extension\ExtensionAuthController;
use App\Http\Controllers\Api\Extension\ExtensionConsentController;
use App\Http\Controllers\Api\Extension\ExtensionConversationController;
use App\Http\Controllers\Api\Extension\ExtensionLeadController;
use App\Http\Controllers\Api\Extension\ExtensionTemplateController;
use Illuminate\Support\Facades\Route;

Route::middleware(['resolve-tenant', 'simple-auth'])->prefix('atendimento')->group(function () {
    Route::get('conversations', [ConversationController::class, 'index']);
    Route::get('conversations/{crm_conversation}', [ConversationController::class, 'show']);
    Route::post('conversations/{crm_conversation}/summary', [ConversationController::class, 'storeSummary']);
    Route::post('conversations/{crm_conversation}/messages', [ConversationMessageController::class, 'store']);
    Route::post('conversations/{crm_conversation}/events', [ConversationEventController::class, 'store']);
    Route::post('conversations/{crm_conversation}/tasks', [ConversationTaskController::class, 'store']);
    Route::post('conversations/{crm_conversation}/visits', [ConversationVisitController::class, 'store']);
    Route::post('conversations/{crm_conversation}/proposals', [ConversationProposalController::class, 'store']);
});

Route::middleware(['resolve-tenant', 'simple-auth'])->prefix('extension')->group(function () {
    Route::post('auth/check', [ExtensionAuthController::class, 'check']);
    Route::post('consent', [ExtensionConsentController::class, 'store']);
    Route::get('leads/search', [ExtensionLeadController::class, 'search']);
    Route::post('leads', [ExtensionLeadController::class, 'store']);
    Route::post('leads/{lead}/note', [ExtensionLeadController::class, 'addNote']);
    Route::patch('leads/{lead}/status', [ExtensionLeadController::class, 'updateStatus']);
    Route::get('conversations/find', [ExtensionConversationController::class, 'find']);
    Route::post('conversations/link', [ExtensionConversationController::class, 'link']);
    Route::get('message-templates', [ExtensionTemplateController::class, 'index']);
});
