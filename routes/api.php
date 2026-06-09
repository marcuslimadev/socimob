

Route::middleware([\'auth:sanctum\', \'tenant\'])->prefix(\'atendimento\')->group(function () {
    Route::get(\'conversations\', [\App\Http\Controllers\Api\Atendimento\ConversationController::class, \'index\']);
    Route::get(\'conversations/{crm_conversation}\, [\App\Http\Controllers\Api\Atendimento\ConversationController::class, \'show\']);
    Route::post(\'conversations/{crm_conversation}/summary\', [\App\Http\Controllers\Api\Atendimento\ConversationController::class, \'storeSummary\']);
    Route::post(\'conversations/{crm_conversation}/messages\', [\App\Http\Controllers\Api\Atendimento\ConversationMessageController::class, \'store\']);
    Route::post(\'conversations/{crm_conversation}/events\', [\App\Http\Controllers\Api\Atendimento\ConversationEventController::class, \'store\']);
    Route::post(\'conversations/{crm_conversation}/tasks\', [\App\Http\Controllers\Api\Atendimento\ConversationTaskController::class, \'store\']);
    Route::post(\'conversations/{crm_conversation}/visits\', [\App\Http\Controllers\Api\Atendimento\ConversationVisitController::class, \'store\']);
    Route::post(\'conversations/{crm_conversation}/proposals\', [\App\Http\Controllers\Api\Atendimento\ConversationProposalController::class, \'store\']);
});

Route::middleware([\'auth:sanctum\', \'tenant\'])->prefix(\'extension\')->group(function () {
    Route::post(\'auth/check\', [\App\Http\Controllers\Api\Extension\ExtensionAuthController::class, \'check\']);
    Route::post(\'consent\', [\App\Http\Controllers\Api\Extension\ExtensionConsentController::class, \'store\']);
    Route::get(\'leads/search\', [\App\Http\Controllers\Api\Extension\ExtensionLeadController::class, \'search\']);
    Route::post(\'leads\', [\App\Http\Controllers\Api\Extension\ExtensionLeadController::class, \'store\']);
    Route::post(\'conversations/link\', [\App\Http\Controllers\Api\Extension\ExtensionConversationController::class, \'link\']);
    Route::get(\'message-templates\', [\App\Http\Controllers\Api\Extension\ExtensionTemplateController::class, \'index\']);
});
