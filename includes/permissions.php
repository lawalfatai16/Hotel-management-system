<?php
/**
 * Role-based access matrix.
 *
 * Super Admin has '*' — unrestricted access to every module, always,
 * regardless of what's added below. Every other role is scoped to what
 * that job actually needs, per Section 17 of the brief ("a receptionist
 * should NOT be able to access sensitive system administration functions").
 *
 * Module keys match the sidebar nav keys (includes/sidebar.php) and the
 * string passed to Auth::requireModuleAccess() in each page/API file.
 *
 * To grant a role access to a new module later, just add the key to its
 * array here — nothing else needs to change.
 */
return [
    'Super Admin' => ['*'],

    'Manager' => [
        'dashboard', 'reservations', 'guests', 'rooms', 'room_types',
        'checkin', 'checkout', 'housekeeping', 'payments', 'invoices',
        'expenses', 'staff', 'events', 'restaurant', 'reports', 'analytics',
        'audit_logs',
    ],

    'Receptionist' => [
        'dashboard', 'reservations', 'guests', 'rooms',
        'checkin', 'checkout', 'housekeeping', 'payments', 'events',
    ],

    'Accountant' => [
        'dashboard', 'payments', 'invoices', 'expenses', 'reports', 'analytics',
    ],

    'Housekeeping' => [
        'dashboard', 'housekeeping', 'rooms',
    ],

    'Event Manager' => [
        'dashboard', 'events', 'payments', 'invoices',
    ],

    'Restaurant Staff' => [
        'dashboard', 'restaurant',
    ],
];
