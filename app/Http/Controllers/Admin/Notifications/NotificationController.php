<?php

namespace App\Http\Controllers\Admin\Notifications;

use App\Http\Controllers\Concerns\HandlesNotifications;
use App\Http\Controllers\Controller;

class NotificationController extends Controller
{
    use HandlesNotifications;

    protected function notificationView(): string
    {
        return 'admin.notifications.index';
    }
}
