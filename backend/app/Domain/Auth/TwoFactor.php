<?php

declare(strict_types=1);

namespace App\Domain\Auth;

use App\Models\User;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

/**
 * Time-based one-time passwords (RFC 6238, the codes shown by Google Authenticator, Microsoft Authenticator
 * and similar apps) for admin sign-in (PRD 6, 16). Free, no SMS. Secrets and recovery codes are stored
 * encrypted; recovery codes are also hashed and work once.
 */
final class TwoFactor
{
    public const SESSION_KEY = 'two_factor.passed_for';

    private const PERIOD = 30;

    private const DIGITS = 6;

    private const BASE32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function enforced(): bool
    {
        return (bool) config('gea.two_factor_enforced');
    }

    public static function newSecret(): string
    {
        $bytes = random_bytes(20);
        $bits = '';
        foreach (str_split($bytes) as $c) {
            $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::BASE32[bindec(str_pad($chunk, 5, '0'))];
        }

        return $out;
    }

    public static function code(string $secret, ?int $timestamp = null, int $digits = self::DIGITS): string
    {
        $counter = intdiv($timestamp ?? time(), self::PERIOD);
        $hash = hash_hmac('sha1', pack('J', $counter), self::decode($secret), true);
        $offset = ord($hash[19]) & 0x0F;
        $value = ((ord($hash[$offset]) & 0x7F) << 24) | (ord($hash[$offset + 1]) << 16) | (ord($hash[$offset + 2]) << 8) | ord($hash[$offset + 3]);

        return str_pad((string) ($value % (10 ** $digits)), $digits, '0', STR_PAD_LEFT);
    }

    /** Accepts the current code or one 30-second step either side (phone clocks drift). A code works once. */
    public static function verify(string $secret, string $code, string $replayKey, ?int $timestamp = null): bool
    {
        $code = preg_replace('/\D/', '', $code) ?? '';
        if (strlen($code) !== self::DIGITS) {
            return false;
        }
        $now = $timestamp ?? time();
        foreach ([0, -1, 1] as $step) {
            $t = $now + $step * self::PERIOD;
            if (hash_equals(self::code($secret, $t), $code)) {
                return Cache::add('2fa-used:'.$replayKey.':'.intdiv($t, self::PERIOD), true, self::PERIOD * 3);
            }
        }

        return false;
    }

    public static function uri(User $user, string $secret): string
    {
        $issuer = rawurlencode('GEA Haulage');

        return "otpauth://totp/{$issuer}:".rawurlencode($user->email)."?secret={$secret}&issuer={$issuer}&digits=6&period=30";
    }

    public static function qrSvg(string $uri): string
    {
        return (new QRCode(new QROptions(['outputBase64' => true, 'svgViewBox' => true])))->render($uri);
    }

    /**
     * Turns 2FA on once the user has proved their app shows the right code. Returns the plain recovery codes,
     * which are shown once.
     *
     * @return list<string>
     */
    public function confirm(User $user, string $secret, string $code): ?array
    {
        if (! self::verify($secret, $code, 'setup:'.$user->id)) {
            return null;
        }
        $codes = self::newRecoveryCodes();
        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => array_map(fn (string $c): string => Hash::make($c), $codes),
            'two_factor_confirmed_at' => now(),
        ])->save();
        activity()->performedOn($user)->causedBy($user)->log('Two-factor sign-in turned on');

        return $codes;
    }

    /** Checks an app code or, failing that, a recovery code (which is then used up). */
    public function check(User $user, string $input): bool
    {
        if (! $user->hasTwoFactor()) {
            return false;
        }
        $input = trim($input);
        if (preg_match('/^\d[\d\s]{5,7}$/', $input) === 1) {
            return self::verify((string) $user->two_factor_secret, $input, (string) $user->id);
        }

        $codes = $user->two_factor_recovery_codes ?? [];
        foreach ($codes as $i => $hash) {
            if (Hash::check(strtoupper($input), $hash)) {
                unset($codes[$i]);
                $user->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();
                activity()->performedOn($user)->causedBy($user)->log('Signed in with a two-factor recovery code ('.count($codes).' left)');

                return true;
            }
        }

        return false;
    }

    public function reset(User $user, ?User $by): void
    {
        $user->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null])->save();
        activity()->performedOn($user)->causedBy($by)->log('Two-factor sign-in reset');
    }

    /** @return list<string> */
    public static function newRecoveryCodes(): array
    {
        // No 0/O, 1/I/L or 5/S, so codes read back from paper are not mistyped.
        $alphabet = 'ABCDEFGHJKMNPQRTUVWXYZ2346789';
        $part = fn (): string => implode('', array_map(fn (): string => $alphabet[random_int(0, strlen($alphabet) - 1)], range(1, 5)));

        return array_map(fn (): string => $part().'-'.$part(), range(1, 8));
    }

    private static function decode(string $secret): string
    {
        $secret = strtoupper(str_replace(['=', ' '], '', $secret));
        $bits = '';
        foreach (str_split($secret) as $c) {
            $pos = strpos(self::BASE32, $c);
            if ($pos !== false) {
                $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
            }
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr((int) bindec($byte));
            }
        }

        return $out;
    }
}
