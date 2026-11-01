<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Website copy editable in the admin (PRD 7, porting rule 3). Lists are stored as JSON arrays.
 */
class WebsiteSettings extends Settings
{
    /** @phpstan-var list<string> */
    public array $hero_lines;

    public string $hero_intro;

    public string $about_heading;

    /** @phpstan-var list<string> */
    public array $about_paragraphs;

    public string $about_quote;

    public string $story_lead;

    /** @phpstan-var list<string> */
    public array $story_paragraphs;

    public string $mission;

    public string $vision;

    public string $services_intro;

    /** @phpstan-var array<int, array<string, string>> */
    public array $values;

    /** @phpstan-var list<string> */
    public array $reasons;

    public string $reasons_closing;

    public string $commitment_statement;

    /** @phpstan-var list<string> */
    public array $commitment_paragraphs;

    /** @phpstan-var array<int, array<string, string>> */
    public array $steps;

    public string $careers_intro;

    public string $quote_intro;

    /** Hidden until GEA sends real client logos with permission (PRD 7 content fixes). */
    public bool $show_clients;

    /** @phpstan-var array<int, array<string, string>> Only credentials GEA has proof for. */
    public array $credentials;

    public string $email_info;

    public string $email_sales;

    public string $email_operations;

    public string $email_support;

    public string $email_billing;

    public static function group(): string
    {
        return 'website';
    }
}
