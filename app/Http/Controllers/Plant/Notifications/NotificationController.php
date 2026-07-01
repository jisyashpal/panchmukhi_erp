<?php

namespace App\Http\Controllers\Site\Notifications;

use App\Http\Controllers\Concerns\HandlesNotifications;
use App\Http\Controllers\Controller;

class NotificationController extends Controller
{
    use HandlesNotifications;

    protected function notificationView(): string
    {
        return 'site.notifications.index';
    }
}
