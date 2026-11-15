<?php

return [
    'name' => 'SpaceRent',
    'whatsapp' => env('SPACERENT_WHATSAPP', '6281234567890'),
    'hotline' => env('SPACERENT_HOTLINE', '0812-3456-7890'),
    'email' => env('SPACERENT_EMAIL', 'halo@spacerent.id'),
    'address' => env('SPACERENT_ADDRESS', 'Jl. Jenderal Sudirman Kav. 52-53, SCBD, Jakarta Selatan 12190'),
    'reply_time' => env('SPACERENT_REPLY_TIME', 'Biasanya membalas dalam 1 jam'),
    // Aturan bisnis booking
    'service_fee_percent' => (float) env('SPACERENT_SERVICE_FEE', 5),
    'max_pending_per_user' => 3,
    'approval_hours' => 24,
    'review_window_days' => 30,
    'review_edit_days' => 7,
    'slot_grid_minutes' => 30,
    'reschedule_min_hours' => 24,
    'max_reschedules' => 1,
    // Payout penjual
    'commission_percent' => (float) env('SPACERENT_COMMISSION', 10),
    'payout_min' => 50000,
    'payout_hold_days' => 1,
];
