<?php

namespace App\Http\Controllers;

use App\Domain\Ai\Adapters\AiProviderException;
use App\Domain\Ai\ProviderNotConfiguredException;
use App\Domain\Ai\Support\ErrorNormalizer;
use App\Domain\Conversation\ConversationEngine;
use App\Domain\Requirement\ReadinessEngine;
use App\Domain\Requirement\RequirementExtractor;
use App\Models\Conversation;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ConversationController extends Controller
{
    public function __construct(
        private readonly ConversationEngine $engine,
        private readonly ReadinessEngine $readiness,
    ) {}

    /** SSE streaming chat endpoint. */
    public function stream(Request $request, Project $project, Conversation $conversation): StreamedResponse|JsonResponse
    {
        $this->authorize('view', $project);
        abort_unless($conversation->project_id === $project->id, 404);

        $data = $request->validate([
            'message' => ['required', 'string', 'max:8000'],
        ]);

        $user = $request->user();

        // Long chats: reasoning models can take minutes before first token.
        set_time_limit(0);

        $this->engine->addUserMessage($conversation, $data['message']);

        // Nudge status forward on first real discussion
        if ($project->status->value === 'discovery') {
            $project->forceFill(['status' => 'requirements_in_progress'])->save();
        }

        $requestId = (string) Str::uuid();

        return new StreamedResponse(function () use ($user, $conversation, $requestId) {
            echo "event: meta\n";
            echo 'data: '.json_encode(['request_id' => $requestId])."\n\n";

            while (ob_get_level() > 0) {
                ob_end_flush();
            }
            flush();

            try {
                foreach ($this->engine->streamReply($user, $conversation) as $delta) {
                    echo 'data: '.json_encode(['delta' => $delta], JSON_UNESCAPED_UNICODE)."\n\n";

                    while (ob_get_level() > 0) {
                        ob_end_flush();
                    }
                    flush();
                }

                echo "event: done\n";
                echo 'data: '.json_encode(['status' => 'complete'])."\n\n";
            } catch (ProviderNotConfiguredException $e) {
                echo 'event: error'.chr(10);
                echo 'data: '.json_encode(['error' => $e->getMessage(), 'category' => 'no_provider'])."\n\n";
            } catch (AiProviderException $e) {
                echo 'event: error'.chr(10);
                echo 'data: '.json_encode(['error' => $e->getMessage(), 'category' => $e->category])."\n\n";
            } catch (\Throwable $e) {
                Log::error('conversation.stream.failed', ['request_id' => $requestId, 'error' => ErrorNormalizer::sanitize($e->getMessage())]);

                echo 'event: error'.chr(10);
                echo 'data: '.json_encode(['error' => 'Terjadi error internal. Coba lagi.', 'category' => 'internal'])."\n\n";
            }

            flush();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
        ]);
    }

    /** Non-streaming fallback + retry. */
    public function send(Request $request, Project $project, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $project);
        abort_unless($conversation->project_id === $project->id, 404);

        $data = $request->validate([
            'message' => ['required', 'string', 'max:8000'],
        ]);

        $user = $request->user();

        try {
            $this->engine->addUserMessage($conversation, $data['message']);

            if ($project->status->value === 'discovery') {
                $project->forceFill(['status' => 'requirements_in_progress'])->save();
            }

            $message = $this->engine->reply($user, $conversation);

            return response()->json([
                'message' => [
                    'id' => $message->id,
                    'role' => $message->role,
                    'content' => $message->content,
                    'created_at' => $message->created_at->toIso8601String(),
                ],
                'readiness' => $this->readiness->evaluate($project)->toArray(),
            ]);
        } catch (ProviderNotConfiguredException $e) {
            return response()->json(['error' => $e->getMessage(), 'category' => 'no_provider'], 422);
        } catch (AiProviderException $e) {
            return response()->json(['error' => $e->getMessage(), 'category' => $e->category], 502);
        } catch (\Throwable) {
            return response()->json(['error' => 'Terjadi error internal. Coba lagi.', 'category' => 'internal'], 500);
        }
    }

    /** Re-run extraction after conversation. */
    public function extract(Request $request, Project $project, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $project);
        abort_unless($conversation->project_id === $project->id, 404);

        try {
            $result = app(RequirementExtractor::class)
                ->extractFromConversation($request->user(), $conversation);

            return response()->json([
                'context_changes' => $result->contextChanges,
                'proposed' => collect($result->proposedRequirements)->map(fn ($r) => [
                    'id' => $r->id,
                    'title' => $r->title,
                    'type' => $r->type,
                    'priority' => $r->priority,
                ]),
                'readiness' => $this->readiness->evaluate($project)->toArray(),
            ]);
        } catch (ProviderNotConfiguredException $e) {
            return response()->json(['error' => $e->getMessage(), 'category' => 'no_provider'], 422);
        } catch (AiProviderException $e) {
            return response()->json(['error' => $e->getMessage(), 'category' => $e->category], 502);
        } catch (\Throwable) {
            return response()->json(['error' => 'Ekstraksi gagal. Coba lagi.', 'category' => 'internal'], 500);
        }
    }

    public function messages(Request $request, Project $project, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $project);
        abort_unless($conversation->project_id === $project->id, 404);

        $messages = $conversation->messages()
            ->orderBy('id')
            ->when($request->query('after_id'), fn ($q, $after) => $q->where('id', '>', (int) $after))
            ->limit(100)
            ->get();

        return response()->json([
            'messages' => $messages->map(fn ($m) => [
                'id' => $m->id,
                'role' => $m->role,
                'content' => $m->content,
                'created_at' => $m->created_at->toIso8601String(),
            ]),
        ]);
    }
}
