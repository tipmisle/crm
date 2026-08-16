<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Workspace deletion grace period
    |--------------------------------------------------------------------------
    |
    | Days between an owner requesting workspace deletion and the workspace
    | becoming eligible for permanent purge (workspaces:purge-expired).
    | See docs/data-lifecycle.md.
    */
    'workspace_grace_days' => (int) env('RETENTION_WORKSPACE_GRACE_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Temporary export lifetime
    |--------------------------------------------------------------------------
    |
    | Hours a generated workspace-data export ZIP remains downloadable
    | before exports:purge-expired removes it.
    */
    'export_hours' => (int) env('RETENTION_EXPORT_HOURS', 24),

    /*
    |--------------------------------------------------------------------------
    | Backups
    |--------------------------------------------------------------------------
    |
    | Backup retention is an infrastructure decision, not managed by this
    | application config — it depends on the hosting/backup provider chosen
    | at deploy time. See docs/data-lifecycle.md.
    */
    'backups_note' => 'Backup retention is managed by infrastructure, not this config — see docs/data-lifecycle.md.',

    /*
    |--------------------------------------------------------------------------
    | Audit / security log retention
    |--------------------------------------------------------------------------
    |
    | Placeholder only. audit_logs currently has no purge job and this key
    | is NOT read by any scheduled command. Do not wire this into a purge
    | job without legal review — retention requirements for security
    | processing logs (e.g. ZVOP-2 Article 22) may set a mandatory minimum
    | that this app must not undercut. See docs/data-lifecycle.md.
    */
    'audit_log_days' => env('RETENTION_AUDIT_LOG_DAYS') !== null ? (int) env('RETENTION_AUDIT_LOG_DAYS') : null,

];
