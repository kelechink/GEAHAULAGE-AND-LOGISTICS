<?php

declare(strict_types=1);

return [
    // Monitoring (PRD 6, 16): where error alerts go.
    'alert_email' => env('ALERT_EMAIL'),

    // Two-factor sign-in for everyone who uses /admin (PRD 6, 16). Switch off only for local demos.
    'two_factor_enforced' => (bool) env('TWO_FACTOR_ENFORCED', true),
];
