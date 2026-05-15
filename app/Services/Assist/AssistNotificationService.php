<?php

namespace App\Services\Assist;

use App\Events\Assist\AssistRequestUpdated;
use App\Mail\AssistanceMail;
use App\Models\NotificationTemplate;
use App\Models\User;
use App\Models\Assist\AssistanceRequest;
use App\Models\Assist\AssistNotification;
use App\Services\FirebaseService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AssistNotificationService
{
    public function __construct(
        private readonly FirebaseService $firebase
    ) {}
    /** Notification types sent TO helpers (check HelperProfile preferences) */
    private const HELPER_TYPES = ['new_request', 'cancelled', 'rated', 'seeker_finished', 'proposal_accepted', 'proposal_rejected', 'proposal_rejected_manual', 'proposal_restored'];

    /** Profile-level notification types (no AssistanceRequest attached) */
    private const PROFILE_TYPES = ['helper_approved', 'helper_rejected'];

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
        'helper_approved' => [
            'en' => ['title' => 'Application approved!',                'body' => 'Your helper application has been approved. You can now receive assistance requests.'],
            'ar' => ['title' => 'تم قبول طلبك!',                        'body' => 'تم قبول طلب المساعد الخاص بك. يمكنك الآن استقبال طلبات المساعدة.'],
        ],
        'helper_rejected' => [
            'en' => ['title' => 'Application not approved',             'body' => 'Your helper application was not approved. Contact support for more details.'],
            'ar' => ['title' => 'لم يتم قبول طلبك',                     'body' => 'لم يتم قبول طلب المساعد الخاص بك. تواصل مع الدعم للمزيد من التفاصيل.'],
        ],
        'proposal_received' => [
            'en' => ['title' => 'New proposal received',                'body' => 'A helper submitted a price proposal for your request. Check the offers now.'],
            'ar' => ['title' => 'تلقيت عرضاً جديداً',                   'body' => 'قدّم مساعد عرض سعر لطلبك. تحقق من العروض الآن.'],
        ],
        'proposal_accepted' => [
            'en' => ['title' => 'Your proposal was accepted!',          'body' => 'The rider accepted your proposal. Head to their location now.'],
            'ar' => ['title' => 'تم قبول عرضك!',                        'body' => 'قبل المتسابق عرضك. توجه إلى موقعه الآن.'],
        ],
        'proposal_rejected' => [
            'en' => ['title' => 'Proposal not selected',                'body' => 'The rider chose another helper. Keep an eye on the feed for new requests.'],
            'ar' => ['title' => 'لم يتم اختيار عرضك',                   'body' => 'اختار المتسابق مساعداً آخر. راقب قائمة الطلبات للعثور على فرص جديدة.'],
        ],
        'proposal_rejected_manual' => [
            'en' => ['title' => 'Proposal declined',                    'body' => 'The rider declined your proposal. You can submit a new offer for this request.'],
            'ar' => ['title' => 'تم رفض عرضك',                          'body' => 'رفض المتسابق عرضك. يمكنك تقديم عرض جديد لهذا الطلب.'],
        ],
        'proposal_restored' => [
            'en' => ['title' => 'Your proposal is back in play!',        'body' => 'The assigned helper cancelled. Your proposal is now active again — the rider may still accept it.'],
            'ar' => ['title' => 'عرضك نشط مجدداً!',                     'body' => 'ألغى المساعد المعيّن المهمة. عرضك نشط مجدداً وقد يقبله المتسابق.'],
        ],
    ];

    /**
     * Send a profile-level notification (no AssistanceRequest) — e.g. helper approved/rejected.
     */
    public function notifyHelperProfile(User $user, string $type): AssistNotification
    {
        $lang  = ((string) ($user->getAttribute('language'))) === 'ar' ? 'ar' : 'en';
        $tplGroup = self::TEMPLATES[$type] ?? null;
        $tpl      = $tplGroup[$lang] ?? $tplGroup['en'] ?? [
            'title' => 'DabApp Assist',
            'body'  => 'You have a new notification.',
        ];

        $notification = AssistNotification::create([
            'user_id'    => $user->id,
            'request_id' => null,
            'type'       => $type,
            'title'      => $tpl['title'],
            'body'       => $tpl['body'],
            'is_read'    => false,
        ]);

        $pref     = $user->notificationPreference;
        $sendPush = $pref ? $pref->canSendPush() : true;

        if ($sendPush) {
            $tokens = $user->notificationTokens()->active()->get();
            foreach ($tokens as $tokenModel) {
                try {
                    $this->firebase->sendToToken(
                        $tokenModel->fcm_token,
                        $tpl['title'],
                        $tpl['body'],
                        [
                            'notification_id' => (string) $notification->getAttribute('id'),
                            'type'            => "assist_{$type}",
                            'entity_type'     => 'helper_profile',
                            'role'            => 'helper',
                            'action_url'      => \in_array($type, self::PROFILE_TYPES)
                                ? 'assist/helper/profile'
                                : 'assist/helper/feed',
                            'timestamp'       => now()->toIso8601String(),
                        ]
                    );
                    $tokenModel->updateLastUsed();
                } catch (\Exception $e) {
                    $tokenModel->incrementFailedAttempts();
                    Log::error("Assist FCM profile push failed (user {$user->id}): {$e->getMessage()}");
                }
            }
        }

        return $notification;
    }

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

        $this->dispatch($user, $type, $title, $body, $lang, $request, $notification->getAttribute('id'));

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

    private function dispatch(User $user, string $type, string $title, string $body, string $lang, AssistanceRequest $request, int $notificationId): void
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
            $this->sendFcmPush($user, $title, $body, $type, $request->id, $notificationId);
        }

        if ($sendEmail && $user->email) {
            Mail::to($user->email)->queue(new AssistanceMail($user, $title, $body, $lang));
        }
    }

    private function sendFcmPush(User $user, string $title, string $body, string $type, int $requestId, int $notificationId): void
    {
        $tokens = $user->notificationTokens()->active()->get();
        if ($tokens->isEmpty()) {
            return;
        }

        $role       = \in_array($type, self::HELPER_TYPES) ? 'helper' : 'seeker';
        $actionUrl  = $this->resolveActionUrl($type, $requestId);

        $data = [
            'notification_id' => (string) $notificationId,
            'type'            => "assist_{$type}",
            'entity_type'     => 'assistance_request',
            'entity_id'       => (string) $requestId,
            'role'            => $role,
            'action_url'      => $actionUrl,
            'timestamp'       => now()->toIso8601String(),
        ];

        foreach ($tokens as $tokenModel) {
            try {
                $this->firebase->sendToToken(
                    $tokenModel->fcm_token,
                    $title,
                    $body,
                    $data
                );
                $tokenModel->updateLastUsed();
            } catch (\Exception $e) {
                $tokenModel->incrementFailedAttempts();
                Log::error("Assist FCM push failed (user {$user->id}, token {$tokenModel->getAttribute('id')}): {$e->getMessage()}");
            }
        }
    }

    private function resolveActionUrl(string $type, int $requestId): string
    {
        return match($type) {
            'new_request'                               => 'assist/helper/feed',
            'cancelled', 'rated', 'seeker_finished'     => "assist/helper/mission/{$requestId}",
            'proposal_accepted'                         => "assist/helper/mission/{$requestId}",
            'proposal_rejected'                         => 'assist/helper/feed',
            'proposal_rejected_manual'                  => "assist/helper/feed/{$requestId}",
            'proposal_restored'                         => "assist/helper/feed/{$requestId}",
            'proposal_received'                         => "assist/seeker/request/{$requestId}/proposals",
            default                                     => "assist/seeker/request/{$requestId}",
        };
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
