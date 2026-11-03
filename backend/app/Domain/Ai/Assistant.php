<?php

declare(strict_types=1);

namespace App\Domain\Ai;

use App\Models\Ai\Conversation;
use App\Models\Ai\Message;
use App\Settings\CompanySettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * The website assistant (PRD 12.2–12.4). Grounded in the knowledge base, tools run by Laravel,
 * 20 turns per chat, top answers cached, and a lead form when the AI is unavailable.
 */
final class Assistant
{
    public const MAX_TURNS = 20;

    private const MAX_TOOL_ROUNDS = 4;

    /** Topics that always go to a person (PRD 12.4). */
    private const HANDOFF_WORDS = ['complain', 'complaint', 'damage', 'damaged', 'broken', 'goods lost', 'lost goods', 'cargo lost',
        'goods missing', 'missing goods', 'stolen', 'refund', 'dispute', 'payment', 'paid', 'invoice', 'overcharge', 'scam', 'lawyer',
        'court', 'speak to a person', 'talk to a person', 'speak to someone', 'talk to someone', 'real person', 'customer care'];

    public function __construct(
        private readonly AiProvider $ai,
        private readonly AssistantTools $tools,
        private readonly KnowledgeBase $kb,
        private readonly CompanySettings $company,
    ) {}

    public static function start(?string $page, ?string $ip): Conversation
    {
        /** @var Conversation */
        return Conversation::query()->create([
            'token' => Str::random(40),
            'page' => $page !== null ? mb_substr($page, 0, 250) : null,
            'ip_hash' => $ip !== null ? hash('sha256', $ip.config('app.key')) : null,
        ]);
    }

    /**
     * Handles one visitor message and returns the messages to show (excluding the visitor's own).
     *
     * @return list<Message>
     */
    public function handle(Conversation $c, string $text, ?string $page = null): array
    {
        $text = trim(mb_substr($text, 0, 1000));
        $c->messages()->create(['role' => 'visitor', 'body' => $text]);
        $c->forceFill(['turns' => $c->turns + 1, 'last_message_at' => now(), 'page' => $page ?? $c->page])->save();

        if ($c->status === 'closed') {
            $c->forceFill(['status' => 'bot'])->save();
        }
        if ($c->isHuman()) {
            return []; // staff are replying from the inbox; the visitor's page polls for their answer
        }
        if ($c->turns > self::MAX_TURNS) {
            return [$this->say($c, 'We have reached the limit for this chat. Leave your details and our team will reply, or call us on '.$this->company->phone.'.', null, ['form' => 'lead'])];
        }
        if ($this->needsPerson($text)) {
            $this->tools->handoff('Visitor raised: '.Str::limit($text, 120), $c);

            return [$this->say($c, 'I am sorry about this. I have asked a GEA team member to join this chat now. You can also call '.$this->company->phone.'.', null, ['handoff' => true])];
        }

        $cacheKey = $c->turns === 1 ? 'ai:answer:'.md5(implode(' ', KnowledgeBase::terms($text)).'|'.parse_url((string) $page, PHP_URL_PATH)) : null;
        if ($cacheKey !== null && is_string($cached = Cache::get($cacheKey))) {
            AiGateway::count('cache', cacheHit: true);

            return [$this->say($c, $cached, 'cache')];
        }

        try {
            [$reply, $provider, $usedTools] = $this->converse($c, $text, $page);
        } catch (AiUnavailable) {
            return [$this->say($c, 'Our assistant is busy right now. Leave your details and our team will reply, or track a shipment below.', null, ['form' => 'lead', 'offline' => true])];
        }

        if ($cacheKey !== null && ! $usedTools) {
            Cache::put($cacheKey, $reply, now()->addDay());
        }

        return [$this->say($c, $reply, $provider)];
    }

    /** @return array{0: string, 1: string, 2: bool} */
    private function converse(Conversation $c, string $text, ?string $page): array
    {
        $knowledge = KnowledgeBase::asContext($this->kb->relevant($text, $page));
        $messages = [['role' => 'system', 'content' => $this->systemPrompt($knowledge, $page)]];
        foreach ($c->messages()->latest('id')->limit(12)->get()->reverse() as $m) {
            if (in_array($m->role, ['visitor', 'assistant', 'staff'], true)) {
                $messages[] = ['role' => $m->role === 'visitor' ? 'user' : 'assistant', 'content' => $m->body];
            }
        }

        $usedTools = false;
        $provider = 'gateway';
        for ($round = 0; $round < self::MAX_TOOL_ROUNDS; $round++) {
            $res = $this->ai->chat($messages, $this->tools->definitions());
            $provider = $res->provider;
            if (! $res->wantsTools()) {
                return [$res->text, $provider, $usedTools];
            }
            $usedTools = true;
            $messages[] = ['role' => 'assistant', 'content' => $res->text, 'tool_calls' => $res->toolCalls];
            foreach ($res->toolCalls as $call) {
                $result = $this->tools->run($call->name, $call->arguments, $c);
                $messages[] = ['role' => 'tool', 'tool_call_id' => $call->id, 'name' => $call->name, 'content' => (string) json_encode($result)];
            }
            $c->refresh();
        }

        return ['Let me pass you to a GEA team member who can help with this.', $provider, true];
    }

    private function systemPrompt(string $knowledge, ?string $page): string
    {
        $c = $this->company;

        return <<<TXT
        You are the website assistant for {$c->company_name}, a haulage and logistics company in Nigeria. Today is {$this->today()}.
        The visitor is on: {$page}

        Rules:
        - Answer only from the GEA knowledge below. If it does not say, say you are not sure and offer a person (handoff_to_human).
        - Never invent prices, rates, ETAs, policies or coverage. For prices, collect the details and save a quote request.
        - Reply in 1 to 3 short sentences of clear, friendly English. Understand Nigerian Pidgin and reply in simple English.
        - Always end with one next step: get a quote, track a shipment, request a call back, call {$c->phone} or WhatsApp {$c->whatsapp}.
        - To save a quote request, collect name, phone, route, cargo and tonnage, read them back, and only call create_or_update_lead with confirmed=true after the visitor says yes.
        - For tracking, call track_shipment with the code. Share only what it returns.
        - Complaints, damage or loss, payments, invoices or disputes: call handoff_to_human.
        - Never ask for or repeat bank, card or payment details.

        GEA knowledge:
        {$knowledge}
        TXT;
    }

    private function today(): string
    {
        return now('Africa/Lagos')->format('l j F Y');
    }

    private function needsPerson(string $text): bool
    {
        $t = ' '.Str::lower($text).' ';
        foreach (self::HANDOFF_WORDS as $w) {
            if (preg_match('/\b'.preg_quote($w, '/').'\b/', $t) === 1) {
                return true;
            }
        }

        return false;
    }

    /** @param  array<string, mixed>  $meta */
    private function say(Conversation $c, string $body, ?string $provider, array $meta = []): Message
    {
        /** @var Message */
        return $c->messages()->create(['role' => 'assistant', 'body' => $body, 'provider' => $provider, 'meta' => $meta ?: null]);
    }
}
