<?php

declare(strict_types=1);

namespace App\Domain\Ai;

use App\Domain\Ai\Providers\GeminiProvider;
use App\Domain\Ai\Providers\GroqProvider;
use App\Enums\Role;
use App\Models\User;
use App\Notifications\AiOfflineNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Gemini first, Groq second (PRD 12.1). Every outgoing message passes the DataGuard. Counts usage per day.
 * When both fail, Management is alerted (once a day) and callers fall back to "leave your details".
 */
final class AiGateway implements AiProvider
{
    /** @var list<AiProvider> */
    private array $providers;

    public function __construct(?GeminiProvider $gemini = null, ?GroqProvider $groq = null)
    {
        $this->providers = [$gemini ?? new GeminiProvider, $groq ?? new GroqProvider];
    }

    public function name(): string
    {
        return 'gateway';
    }

    public function isConfigured(): bool
    {
        foreach ($this->providers as $p) {
            if ($p->isConfigured()) {
                return true;
            }
        }

        return false;
    }

    public function chat(array $messages, array $tools = [], array $options = []): AiResponse
    {
        $messages = DataGuard::scrubMessages($messages);
        $errors = [];

        foreach ($this->providers as $p) {
            if (! $p->isConfigured()) {
                continue;
            }
            try {
                $res = $p->chat($messages, $tools, $options);
                self::count($p->name(), tokens: $res->tokens);

                return $res;
            } catch (AiUnavailable $e) {
                self::count($p->name(), failed: true);
                $errors[] = $e->getMessage();
            }
        }

        $this->alertOffline($errors);

        throw new AiUnavailable($errors === [] ? 'No AI provider is configured.' : implode(' ', $errors), rateLimited: true);
    }

    public static function count(string $provider, bool $failed = false, int $tokens = 0, bool $cacheHit = false): void
    {
        $day = now('Africa/Lagos')->toDateString();
        DB::table('ai_usage')->insertOrIgnore(['day' => $day, 'provider' => $provider]);
        DB::table('ai_usage')->where(['day' => $day, 'provider' => $provider])->update([
            'requests' => DB::raw('requests + '.($failed || $cacheHit ? 0 : 1)),
            'failures' => DB::raw('failures + '.($failed ? 1 : 0)),
            'tokens' => DB::raw('tokens + '.max(0, $tokens)),
            'cache_hits' => DB::raw('cache_hits + '.($cacheHit ? 1 : 0)),
        ]);
    }

    /** @param  list<string>  $errors */
    private function alertOffline(array $errors): void
    {
        Log::warning('AI assistant offline', ['errors' => $errors]);
        if (! Cache::add('ai-offline-alert:'.now('Africa/Lagos')->toDateString(), true, now()->addDay())) {
            return;
        }
        $to = User::query()->role([Role::Management->value, Role::SuperAdmin->value])->where('is_active', true)->get();
        Notification::send($to, new AiOfflineNotification(implode(' ', $errors) ?: 'No AI provider is configured.'));
    }
}
