<?php

namespace App\Actions\Admin\User;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateUserAction
{
    /**
     * Update the user's information and sync Spatie role if provided.
     *
     * @param  array{first_name: string, last_name: string, name: string, email: string, phone_number?: string|null, role?: string|null}  $data
     */
    public function execute(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            $updateData = [
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'full_name' => $data['name'],
            ];

            $personalFields = ['phone_number', 'alternate_number', 'date_of_birth', 'gender', 'profile_photo'];
            foreach ($personalFields as $field) {
                if (array_key_exists($field, $data)) {
                    $updateData[$field] = $data[$field];
                }
            }

            $user->update($updateData);

            if (! empty($data['role'])) {
                $role = Role::where('name', $data['role'])->orWhere('slug', $data['role'])->first();
                $targetRole = $role ? $role->name : $data['role'];

                // syncRoles replaces existing roles with the new role in pivot table without duplicates
                $user->syncRoles([$targetRole]);
            }

            // Update associated company
            if (!empty($data['company_name'])) {
                $company = $user->company; // Gets primary company
                
                if (!$company) {
                    // Create new if doesn't exist
                    $company = new \App\Models\Company();
                }

                $company->name = trim((string) $data['company_name']);
                
                $companyFields = [
                    'contact_person', 'business_type', 'gstin', 'country_id', 'state_id', 'city_id', 'address',
                    'address_line_2', 'pin_code', 'pan_number', 'year_of_establishment', 'no_of_employees',
                    'website', 'business_description', 'trade_preference', 'verification_status'
                ];

                foreach ($companyFields as $field) {
                    if (array_key_exists($field, $data)) {
                        $company->{$field} = $data[$field] === '' ? null : $data[$field];
                    }
                }

                if (isset($data['commodities_handled'])) {
                    $commodities = $data['commodities_handled'];
                    if (is_string($commodities)) {
                        $decoded = json_decode($commodities, true);
                        $commodities = is_array($decoded) ? $decoded : array_filter(array_map('trim', explode(',', $commodities)));
                    }
                    $company->commodities_handled = $commodities;
                }

                $company->save();

                if (isset($data['business_category_ids']) && is_array($data['business_category_ids'])) {
                    $company->businessCategories()->sync($data['business_category_ids']);
                }

                if (!$user->companies()->where('company_id', $company->id)->exists()) {
                    $user->companies()->attach($company->id, [
                        'role' => $targetRole ?? 'user',
                        'is_primary' => true,
                    ]);
                }

                // Update Bank Details
                if (!empty($data['bank_account_number']) || !empty($data['bank_name'])) {
                    $bank = $company->bankDetails()->where('is_primary', true)->first();
                    if (!$bank) {
                        $bank = new \App\Models\CompanyBankDetail();
                        $bank->company_id = $company->id;
                        $bank->is_primary = true;
                    }

                    $bank->account_holder_name = $data['bank_account_holder_name'] ?? $bank->account_holder_name;
                    $bank->bank_name = $data['bank_name'] ?? $bank->bank_name;
                    $bank->account_number = $data['bank_account_number'] ?? $bank->account_number;
                    $bank->ifsc_code = $data['bank_ifsc_code'] ?? $bank->ifsc_code;
                    $bank->branch_name = $data['bank_branch_name'] ?? $bank->branch_name;
                    $bank->save();
                }
            }

            return $user->fresh(['roles', 'companies']);
        });
    }
}
