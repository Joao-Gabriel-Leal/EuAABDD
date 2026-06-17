<?php

return [
    'profile' => env('AABB_MODULE_PROFILE', 'reservas'),

    'enabled' => [
        'public_home' => env('AABB_MODULE_PUBLIC_HOME', true),
        'auth' => env('AABB_MODULE_AUTH', true),
        'portal_reservations' => env('AABB_MODULE_PORTAL_RESERVATIONS', true),
        'portal_payments' => env('AABB_MODULE_PORTAL_PAYMENTS', true),
        'team_reservations' => env('AABB_MODULE_TEAM_RESERVATIONS', true),
        'team_member_import' => env('AABB_MODULE_TEAM_MEMBER_IMPORT', true),
        'team_payments' => env('AABB_MODULE_TEAM_PAYMENTS', true),

        'public_signup' => env('AABB_MODULE_PUBLIC_SIGNUP', false),
        'portal_dependents' => env('AABB_MODULE_PORTAL_DEPENDENTS', false),
        'portal_club_invitations' => env('AABB_MODULE_PORTAL_CLUB_INVITATIONS', false),
        'member_card' => env('AABB_MODULE_MEMBER_CARD', false),
        'team_overview' => env('AABB_MODULE_TEAM_OVERVIEW', false),
        'team_secretariat_full' => env('AABB_MODULE_TEAM_SECRETARIAT_FULL', false),
        'team_finance_full' => env('AABB_MODULE_TEAM_FINANCE_FULL', false),
        'team_access' => env('AABB_MODULE_TEAM_ACCESS', false),
        'team_stock' => env('AABB_MODULE_TEAM_STOCK', false),
        'team_content' => env('AABB_MODULE_TEAM_CONTENT', false),
        'filament' => env('AABB_MODULE_FILAMENT', false),
    ],

    'imports' => [
        'create_temporary_passwords' => env('AABB_IMPORT_CREATE_TEMPORARY_PASSWORDS', false),
        'temporary_password' => env('AABB_IMPORT_TEMPORARY_PASSWORD', 'aabb2026'),
    ],
];
