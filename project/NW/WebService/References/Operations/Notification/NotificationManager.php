<?php
declare(strict_types=1);

namespace NW\WebService\References\Operations\Notification;

class NotificationManager
{
    public static function send(
        int    $resellerId,
        int    $clientId,
        string $notificationStatus,
        int    $differencesTo,
        array  $templateData,
    ): bool
    {
        //TODO Нужно реализовать этот метод.

        return true;
    }
}
