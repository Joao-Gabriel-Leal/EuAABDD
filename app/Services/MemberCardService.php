<?php

namespace App\Services;

use App\Models\Member;
use chillerlan\QRCode\QRCode;
use Illuminate\Support\Str;

class MemberCardService
{
    public function ensureToken(Member $member): Member
    {
        if ($member->card_token && $member->card_issued_at) {
            return $member;
        }

        $member->forceFill([
            'card_token' => $member->card_token ?: (string) Str::uuid(),
            'card_issued_at' => $member->card_issued_at ?: now(),
        ])->save();

        return $member->refresh();
    }

    public function validationUrl(Member $member): string
    {
        $this->ensureToken($member);

        return route('member-card.verify', ['token' => $member->card_token]);
    }

    public function qrCodeDataUri(Member $member): string
    {
        return (new QRCode)->render($this->validationUrl($member));
    }

    public function code(Member $member): string
    {
        $this->ensureToken($member);

        return Str::upper(Str::substr((string) $member->card_token, 0, 8));
    }
}
