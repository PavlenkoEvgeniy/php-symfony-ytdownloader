<?php

declare(strict_types=1);

namespace App\Enum;

enum AuditAction: string
{
    case LOGIN                    = 'auth.login';
    case LOGIN_FAILED             = 'auth.login_failed';
    case LOGOUT                   = 'auth.logout';
    case DOWNLOAD_REQUEST         = 'download.requested';
    case DOWNLOAD_SUCCESS         = 'download.completed';
    case DOWNLOAD_FAILURE         = 'download.failed';
    case DOWNLOAD_RETRY           = 'download.retried';
    case SOURCE_DOWNLOAD          = 'source.downloaded';
    case SOURCE_UPDATE            = 'source.updated';
    case SOURCE_DELETE            = 'source.deleted';
    case SOURCES_DELETE           = 'source.deleted_all';
    case QUEUE_PURGE              = 'admin.queue_purged';
    case QUEUE_RESET              = 'admin.queue_reset';
    case ERROR_TASKS_CLEAR        = 'admin.error_tasks_cleared';
    case ORPHANS_PRUNE            = 'admin.orphans_pruned';
    case SETTINGS_UPDATE          = 'admin.settings_updated';
    case TELEGRAM_DELIVERED       = 'telegram.delivered';
    case TELEGRAM_DELIVERY_FAILED = 'telegram.delivery_failed';
    case LIBRARY_PRUNE            = 'admin.library_pruned';
}
