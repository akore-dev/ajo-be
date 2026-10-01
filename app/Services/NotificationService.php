<?php

namespace App\Services;

use App\Models\Circle;
use App\Models\CircleMember;
use App\Models\User;
use App\Traits\FormatsPhoneNumber;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    use FormatsPhoneNumber;

    public function sendSms(string $phone, string $message): bool
    {
        if (empty($phone)) {
            return false;
        }

        $normalizedPhone = $this->sanitizePhoneNumber($phone);

        try {
            $response = Http::post(config('services.termii.url', 'https://api.ng.termii.com/api/sms/send'), [
                'to' => $normalizedPhone,
                'from' => config('services.termii.sender_id', 'AjoApp'),
                'sms' => $message,
                'type' => 'plain',
                'channel' => 'generic',
                'api_key' => config('services.termii.api_key'),
            ]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error('SMS Dispatch Failed: ' . $e->getMessage(), [
                'phone' => $normalizedPhone,
            ]);

            return false;
        }
    }

    public function sendContributionReminder(CircleMember $member, int $cycle): bool
    {
        $user = $member->user;
        $circle = $member->circle;
        $formattedAmount = number_format($circle->contribution_amount / 100, 2);

        $message = "Hello {$user->name}, your contribution of NGN {$formattedAmount} for cycle {$cycle} in '{$circle->name}' is due soon. Please pay on time!";

        return $this->sendSms($user->phone, $message);
    }

    public function sendPayoutDisbursedAlert(User $recipient, Circle $circle, int $amount): bool
    {
        $formattedAmount = number_format($amount / 100, 2);

        $message = "Congratulations {$recipient->name}! Your payout of NGN {$formattedAmount} from '{$circle->name}' has been successfully processed and disbursed.";

        return $this->sendSms($recipient->phone, $message);
    }

    public function sendCircleStartedAlert(Circle $circle): void
    {
        $circle->load('members.user');

        foreach ($circle->members as $member) {
            $message = "Great news! Circle '{$circle->name}' is now active. Your payout position is #{$member->payout_position}.";

            $this->sendSms($member->user->phone, $message);
        }
    }
}

