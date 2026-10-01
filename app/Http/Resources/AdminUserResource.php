<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\User
 */
class AdminUserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $roleName = $this->roles->first()?->name ?? 'user';
        $isTrader = $roleName === 'trader' || ($this->relationLoaded('roles') ? $this->roles->contains('name', 'trader') : $this->hasRole('trader'));

        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'name' => $this->name,
            'date_of_birth' => $this->date_of_birth,
            'gender' => $this->gender,
            'phone_number' => $this->phone_number,
            'alternate_number' => $this->alternate_number,
            'profile_photo_url' => $this->profile_photo_url,
            'username' => $this->username,
            'email' => $this->email,
            'status' => $this->status,
            'suspension_reason' => $this->suspension_reason,
            'must_change_password' => (bool) $this->must_change_password,
            'role' => $roleName,
            'company' => $this->when($isTrader, function () {
                $businessProfile = $this->relationLoaded('companies')
                    ? ($this->businessProfile->firstWhere('pivot.is_primary', true) ?? $this->businessProfile->first())
                    : $this->businessProfile;

                return $businessProfile ? new BusinessProfileResource($businessProfile) : null;
            }),
            'bank' => $this->when($isTrader, function () {
                $businessProfile = $this->relationLoaded('companies')
                    ? ($this->businessProfile->firstWhere('pivot.is_primary', true) ?? $this->businessProfile->first())
                    : $this->businessProfile;

                $bank = $businessProfile ? $businessProfile->bankDetails()->where('is_primary', true)->first() : null;
                return $bank ? [
                    'account_holder_name' => $bank->account_holder_name,
                    'bank_name' => $bank->bank_name,
                    'account_number' => $bank->account_number,
                    'ifsc_code' => $bank->ifsc_code,
                    'branch_name' => $bank->branch_name,
                ] : null;
            }),
            'kyc_status' => $this->kyc_status,
            'kyc_documents' => $this->whenLoaded('kycDocuments', function () {
                return $this->kycDocuments->map(function ($doc) {
                    return [
                        'id' => $doc->id,
                        'document_type' => $doc->document_type,
                        'status' => $doc->status,
                        'rejection_reason' => $doc->rejection_reason,
                        'download_url' => route('admin.user-kyc.documents.download', ['id' => $doc->id]),
                        'created_at' => $doc->created_at?->toISOString(),
                        'updated_at' => $doc->updated_at?->toISOString(),
                    ];
                });
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'creator' => $this->whenLoaded('creator', function () {
                if (! $this->creator) {
                    return null;
                }

                return [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
                ];
            }),
        ];
    }
}
