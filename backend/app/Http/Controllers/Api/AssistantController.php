<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Ai\Assistant;
use App\Domain\Crm\LeadCaptureService;
use App\Domain\Crm\PhoneNumber;
use App\Enums\LeadSource;
use App\Http\Controllers\Controller;
use App\Models\Ai\Conversation;
use App\Models\Ai\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The browser talks only to these endpoints, never to an AI provider (PRD 12.1).
 * A conversation is identified by an unguessable token the widget keeps in sessionStorage.
 */
class AssistantController extends Controller
{
    public function message(Request $request, Assistant $assistant): JsonResponse
    {
        $data = $request->validate([
            'token' => ['nullable', 'string', 'size:40'],
            'message' => ['required', 'string', 'max:1000'],
            'page' => ['nullable', 'string', 'max:250'],
            'after' => ['nullable', 'integer', 'min:0'],
            'website' => ['nullable', 'max:0'], // honeypot
        ]);

        $c = $this->find($data['token'] ?? null) ?? Assistant::start($data['page'] ?? null, $request->ip());
        $assistant->handle($c, $data['message'], $data['page'] ?? null);

        return $this->state($c->refresh(), (int) ($data['after'] ?? 0));
    }

    /** Polling for staff replies after a handoff (PRD 12.4: no websockets on shared hosting). */
    public function poll(Request $request, string $token): JsonResponse
    {
        $c = $this->find($token);
        if ($c === null) {
            return response()->json(['message' => 'Chat not found.'], 404);
        }

        return $this->state($c, (int) $request->query('after', '0'));
    }

    /** Lead form shown when the AI is offline or the chat limit is reached. No lead is lost (PRD 12.1). */
    public function lead(Request $request, LeadCaptureService $leads): JsonResponse
    {
        $data = $request->validate([
            'token' => ['nullable', 'string', 'size:40'],
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:160'],
            'details' => ['nullable', 'string', 'max:1000'],
            'website' => ['nullable', 'max:0'],
        ]);
        $phone = PhoneNumber::normalise($data['phone']);
        if ($phone === null || strlen($phone) !== 13) {
            return response()->json(['message' => 'Enter a Nigerian phone number, e.g. 0803 123 4567.', 'errors' => ['phone' => ['Invalid phone number.']]], 422);
        }

        $c = $this->find($data['token'] ?? null);
        $lead = $leads->captureQuoteRequest(['name' => $data['name'], 'phone' => $phone, 'email' => $data['email'] ?? null,
            'details' => $data['details'] ?? null], LeadSource::AiChat);
        if ($c !== null) {
            $lead->activities()->create(['contact_id' => $lead->contact_id, 'type' => 'note', 'at' => now(),
                'body' => "Website chat transcript:\n".$c->load('messages')->transcript()]);
            $c->forceFill(['lead_id' => $lead->id, 'visitor_name' => $data['name'], 'visitor_phone' => $phone, 'visitor_email' => $data['email'] ?? null])->save();
            $c->messages()->create(['role' => 'assistant', 'body' => 'Thank you, '.$data['name'].'. Our team will contact you within one working day.']);
        }

        return response()->json(['ok' => true, 'token' => $c?->token]);
    }

    private function find(?string $token): ?Conversation
    {
        return $token === null ? null : Conversation::query()->where('token', $token)->first();
    }

    private function state(Conversation $c, int $after): JsonResponse
    {
        $messages = $c->messages()->where('id', '>', $after)->whereIn('role', ['visitor', 'assistant', 'staff', 'system'])->with('user')->get();

        return response()->json([
            'token' => $c->token,
            'status' => $c->status,
            'messages' => $messages->map(fn (Message $m): array => $m->toPublic() + ['name' => $m->role === 'staff' ? ($m->user?->name !== null ? explode(' ', $m->user->name)[0].' (GEA)' : 'GEA team') : null])->values(),
        ]);
    }
}
