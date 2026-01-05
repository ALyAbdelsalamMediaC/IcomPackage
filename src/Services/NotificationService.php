<?php

namespace AlyIcom\MyPackage\Services;

use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FcmNotification;
use Kreait\Firebase\Messaging\AndroidConfig;
use Kreait\Firebase\Messaging\ApnsConfig;

class NotificationService
{
    protected $notificationModel;
    protected $userModel;

    public function __construct(private ?Messaging $messaging = null)
    {
        if (!$messaging) {
            Log::warning('Firebase Messaging service is not available');
        }

        // Get model classes from config
        $this->notificationModel = config('my-package.models.notification', 'App\Models\Notification');
        $this->userModel = config('my-package.models.user', 'App\Models\User');
    }

    /*────────────────────────── PUBLIC API ───────────────────────────*/

    /**
     * Send a push + store a DB copy
     */
    public function sendNotification(
        $sender,
        $receiver,
        string $title,
        string $body,
        ?string $route = null,
        ?int $requestId = null
    ): void
    {
        /* 1) Persist in DB -------------------------------------------------- */
        $notificationModelClass = $this->notificationModel;
        $notificationModelClass::create([
            'title'       => $title,
            'body'        => $body,
            'route'       => $route,
            'sender_id'   => $sender->id,
            'receiver_id' => $receiver->id,
            'media_id'    => $requestId,
            'seen'        => false,
        ]);

        /* 2) Push via FCM --------------------------------------------------- */
        if (!$receiver->fcm_token || !$this->messaging) {
            Log::warning('FCM notification not sent (missing token or service)', [
                'receiver_id' => $receiver->id,
            ]);
            return;
        }

        try {
            $this->messaging->send(
                $this->buildMessage($receiver->fcm_token, $title, $body)
            );
            Log::info('FCM notification sent', ['receiver_id' => $receiver->id]);
        } catch (\Throwable $e) {
            Log::error("Failed to send FCM notification to user {$receiver->id}: {$e->getMessage()}");
        }
    }

    /*────────────────────────── HELPERS ──────────────────────────────*/

    /**
     * Build a CloudMessage with sound keys for Android + iOS
     */
    private function buildMessage(string $token, string $title, string $body): CloudMessage
    {
        /* a) Visible banner */
        $notification = FcmNotification::create($title, $body);

        /* b) Android config */
        $android = AndroidConfig::fromArray([
            'priority'     => 'high',
            'notification' => [
                'sound'      => 'default',
                'channel_id' => 'high_importance_v2',   // must match Flutter channel
            ],
        ]);

        /* c) APNs (iOS) config */
        $apns = ApnsConfig::fromArray([
            'payload' => [
                'aps' => [
                    'sound'  => 'default',
                    'badge'  => 1,
                ],
            ],
            'headers' => [
                // ensure alert‑type push with high priority
                'apns-push-type' => 'alert',
                'apns-priority'  => '10',
            ],
        ]);

        return CloudMessage::withTarget('token', $token)
            ->withNotification($notification)
            ->withAndroidConfig($android)
            ->withApnsConfig($apns);
    }
}

