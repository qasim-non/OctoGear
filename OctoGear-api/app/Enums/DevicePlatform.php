<?php

namespace App\Enums;

/**
 * Defines the mobile platform of a device.
 *
 * Used in: device_tokens.platform column
 *
 * Needed because push notifications use different formats for each platform:
 * - iOS uses APNs (Apple Push Notification service)
 * - Android uses FCM (Firebase Cloud Messaging)
 */
enum DevicePlatform: string
{
    case Ios = 'ios';
    case Android = 'android';
}
