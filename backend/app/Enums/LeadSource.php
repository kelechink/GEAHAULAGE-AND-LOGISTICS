<?php

declare(strict_types=1);

namespace App\Enums;

enum LeadSource: string
{
    case WebsiteForm = 'website_form';
    case AiChat = 'ai_chat';
    case WhatsappClick = 'whatsapp_click';
    case Phone = 'phone';
    case WalkIn = 'walk_in';
    case Referral = 'referral';
    case Careers = 'careers';

    public function label(): string
    {
        return match ($this) {
            self::WebsiteForm => 'Website form',
            self::AiChat => 'AI chat',
            self::WhatsappClick => 'WhatsApp',
            self::Phone => 'Phone',
            self::WalkIn => 'Walk-in',
            self::Referral => 'Referral',
            self::Careers => 'Careers',
        };
    }
}
