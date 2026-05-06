<?php

namespace App\Services\Assist;

use App\Events\Assist\AssistRequestUpdated;
use App\Mail\AssistanceMail;
use App\Models\NotificationTemplate;
use App\Models\User;
use App\Models\Assist\AssistanceRequest;
use App\Models\Assist\AssistNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FcmNotification;

class AssistNotificationService
{
    /** Notification types sent TO helpers (check HelperProfile preferences) */
    private const HELPER_TYPES = ['new_request', 'cancelled', 'rated', 'seeker_finished'];

    private const TEMPLATES = [
        'new_request' => [
            'en' => ['title' => 'New assistance request nearby',        'body' => 'A rider needs help with %s near you.'],
            'ar' => ['title' => 'طلب مساعدة جديد قريب منك',            'body' => 'متسابق يحتاج مساعدة بـ %s بالقرب منك.'],
        ],
        'accepted' => [
            'en' => ['title' => 'Helper is on the way!',                'body' => 'Your request has been accepted. Help is coming.'],
            'ar' => ['title' => 'المساعد في الطريق!',                   'body' => 'تم قبول طلبك. المساعدة في طريقها إليك.'],
        ],
        'en_route' => [
            'en' => ['title' => 'Helper is en route',                   'body' => 'Your helper is heading to your location.'],
            'ar' => ['title' => 'المساعد في الطريق إليك',               'body' => 'يتجه مساعدك نحو موقعك.'],
        ],
        'arrived' => [
            'en' => ['title' => 'Helper has arrived',                   'body' => 'Your helper has arrived at your location.'],
            'ar' => ['title' => 'وصل المساعد',                          'body' => 'وصل مساعدك إلى موقعك.'],
        ],
        'completed' => [
            'en' => ['title' => 'Assistance completed',                 'body' => 'Your request has been completed. Please rate your experience.'],
            'ar' => ['title' => 'تمت المساعدة',                         'body' => 'تم إكمال طلبك. يرجى تقييم تجربتك.'],
        ],
        'cancelled' => [
            'en' => ['title' => 'Request cancelled',                    'body' => 'The assistance request has been cancelled by the rider.'],
            'ar' => ['title' => 'تم إلغاء الطلب',                       'body' => 'تم إلغاء طلب المساعدة من قبل المتسابق.'],
        ],
        'helper_cancelled' => [
            'en' => ['title' => 'Helper cancelled',                     'body' => 'Your helper cancelled. We are looking for a new helper.'],
            'ar' => ['title' => 'ألغى المساعد',                         'body' => 'ألغى مساعدك المهمة. نحن نبحث عن مساعد جديد.'],
        ],
        'rated' => [
            'en' => ['title' => 'You received a new rating',            'body' => 'Someone rated your assistance. Check your profile.'],
            'ar' => ['title' => 'تلقيت تقييماً جديداً',                 'body' => 'قيّم شخص مساعدتك. تحقق من ملفك الشخصي.'],
        ],
        'seeker_finished' => [
            'en' => ['title' => 'Mission confirmed by rider',           'body' => 'The rider confirmed the assistance is complete. Great work!'],
            'ar' => ['title' => 'أكد المتسابق إتمام المهمة',             'body' => 'أكد المتسابق إتمام المساعدة. عمل رائع!'],
        ],
    ];

    /**
     * Persist an in-app notification, send FCM push, and queue an email.
     * Controller return values are never affected.
     */
    public function notify(User $user, string $type, AssistanceRequest $request): AssistNotification
    {
        $lang      = ((string) ($user->getAttribute('language'))) === 'ar' ? 'ar' : 'en';
        $expertise = $this->getExpertiseName($request, $lang);

        [$title, $body] = $this->resolveTemplate($type, $lang, $expertise);

        $notification = AssistNotification::create([
            'user_id'    => $user->id,
            'request_id' => $request->id,
            'type'       => $type,
            'title'      => $title,
            'body'       => $body,
            'is_read'    => false,
        ]);

        $this->dispatch($user, $type, $title, $body, $lang, $request);

        return $notification;
    }

    // ---------------------------------------------------------------
    // Private helpers
    // ---------------------------------------------------------------

    /**
     * Try DB template first, fall back to hardcoded constants.
     */
    private function resolveTemplate(string $type, string $lang, string $expertise): array
    {
        $dbTemplate = NotificationTemplate::active()->byType("assist_{$type}")->first();

        if ($dbTemplate) {
            $rendered = $dbTemplate->render(['expertise' => $expertise], $lang);
            return [$rendered['title'], $rendered['message']];
        }

        // Hardcoded fallback
        $tplGroup = self::TEMPLATES[$type] ?? null;
        $tpl      = $tplGroup[$lang] ?? $tplGroup['en'] ?? [
            'title' => 'DabApp Assist',
            'body'  => 'You have a new notification.',
        ];

        return [$tpl['title'], \sprintf($tpl['body'], $expertise)];
    }

    private function dispatch(User $user, string $type, string $title, string $body, string $lang, AssistanceRequest $request): void
    {
        // Global user preferences (respects quiet hours, push_enabled, email_enabled)
        $pref      = $user->notificationPreference;
        $sendPush  = $pref ? $pref->canSendPush()  : true;
        $sendEmail = $pref ? $pref->canSendEmail()  : true;

        // For helper-targeted notifications, also respect HelperProfile channel preferences
        if (in_array($type, self::HELPER_TYPES)) {
            $profile = $user->relationLoaded('helperProfile')
                ? $user->helperProfile
                : $user->helperProfile()->first();

            if ($profile) {
                $sendPush  = $sendPush  && (bool) $profile->notify_push;
                $sendEmail = $sendEmail && (bool) $profile->notify_email;
            }
        }

        // WebSocket broadcast for seeker-targeted events (real-time, no timer needed on mobile)
        if (!in_array($type, self::HELPER_TYPES)) {
            broadcast(new AssistRequestUpdated(
                requestId: $request->id,
                seekerId:  $user->id,
                type:      $type,
                title:     $title,
                body:      $body,
            ))->toOthers();
        }

        if ($sendPush) {
            $this->sendFcmPush($user, $title, $body);
        }

        if ($sendEmail && $user->email) {
            Mail::to($user->email)->queue(new AssistanceMail($user, $title, $body, $lang));
        }
    }

    private function sendFcmPush(User $user, string $title, string $body): void
    {
        $tokens = $user->notificationTokens()->active()->get();
        if ($tokens->isEmpty()) {
            return;
        }

        $credentialsFile = config('firebase.credentials.file');
        if (!$credentialsFile || !file_exists($credentialsFile)) {
            Log::warning('Firebase credentials not configured — FCM push skipped.');
            return;
        }

        try {
            $messaging = (new Factory)->withServiceAccount($credentialsFile)->createMessaging();
        } catch (\Exception $e) {
            Log::error("Firebase init failed: {$e->getMessage()}");
            return;
        }

        foreach ($tokens as $tokenModel) {
            try {
                $message = CloudMessage::withTarget('token', $tokenModel->fcm_token)
                    ->withNotification(FcmNotification::create($title, $body));

                $messaging->send($message);
                $tokenModel->updateLastUsed();
            } catch (\Kreait\Firebase\Exception\Messaging\NotFound $e) {
                // Invalid / unregistered token — disable it
                $tokenModel->deactivate();
            } catch (\Exception $e) {
                $tokenModel->incrementFailedAttempts();
                Log::error("FCM push failed (user {$user->id}, token {$tokenModel->id}): {$e->getMessage()}");
            }
        }
    }

    private function getExpertiseName(AssistanceRequest $request, string $lang): string
    {
        $type = $request->expertiseTypes?->first();
        if (!$type) {
            return $lang === 'ar' ? 'مساعدة' : 'assistance';
        }

        return $lang === 'ar'
            ? ($type->name_ar ?? $type->name ?? 'مساعدة')
            : ($type->name_en ?? $type->name ?? 'assistance');
    }
}
