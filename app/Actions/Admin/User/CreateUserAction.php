<?php

namespace App\Actions\Admin\User;

use App\Jobs\SendUserCredentialsEmailJob;
use App\Models\Company;
use App\Models\EmailTemplate;
use App\Models\Role;
use App\Models\User;
use App\Services\EmailTemplateRenderer;
use App\Services\TemporaryPasswordGenerator;
use App\Services\UsernameGenerator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CreateUserAction
{
    public function __construct(
        protected UsernameGenerator $usernameGenerator,
        protected TemporaryPasswordGenerator $temporaryPasswordGenerator,
        protected EmailTemplateRenderer $templateRenderer
    ) {}

    /**
     * Provision a new user account, assign Spatie role, render credential email snapshot and queue after commit.
     *
     * @param  array{first_name: string, last_name: string, name?: string, full_name?: string, phone_number: string, email: string, role?: string}  $data
     *
     * @throws HttpResponseException
     */
    public function execute(User $admin, array $data): User
    {
        // Step 1: Preflight active USER_ACCOUNT_CREATED template
        $template = EmailTemplate::query()
            ->where('key', 'USER_ACCOUNT_CREATED')
            ->first();

        if (! $template || ! $template->is_active) {
            throw new HttpResponseException(
                response()->json([
                    'status' => false,
                    'message' => 'Account creation template USER_ACCOUNT_CREATED is missing or inactive. User cannot be provisioned.',
                    'error' => 'Template configuration error',
                ], 422)
            );
        }

        $fullName = $data['full_name'] ?? $data['name'] ?? trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? ''));

        // Step 2: Generate unique username server-side with Redis lock
        $username = $this->usernameGenerator->generate($fullName, $data['email']);

        // Step 3: Generate cryptographically secure temporary password
        $tempPassword = $this->temporaryPasswordGenerator->generate(16);

        // Step 4: Render credential email snapshot before DB commit
        $replacements = [
            'UserName' => $fullName,
            'Username' => $username,
            'TemporaryPassword' => $tempPassword,
        ];

        $renderedSubject = $this->templateRenderer->render($template->subject, $replacements, 'USER_ACCOUNT_CREATED');
        $renderedBody = $this->templateRenderer->render($template->body, $replacements, 'USER_ACCOUNT_CREATED');

        // Step 5: Persist user and assign Spatie role within DB transaction
        $user = DB::transaction(function () use ($admin, $data, $fullName, $username, $tempPassword) {
            $newUser = User::create([
                'first_name' => $data['first_name'] ?? null,
                'last_name' => $data['last_name'] ?? null,
                'full_name' => $fullName,
                'phone_number' => $data['phone_number'] ?? null,
                'alternate_number' => $data['alternate_number'] ?? null,
                'gender' => $data['gender'] ?? null,
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'profile_photo' => $data['profile_photo'] ?? null,
                'username' => $username,
                'email' => $data['email'],
                'password' => Hash::make($tempPassword),
                'status' => 'active',
                'must_change_password' => true,
                'created_by' => $admin->id,
            ]);

            $roleName = ! empty($data['role']) ? $data['role'] : 'user';

            // Find role by name or slug, default to 'user'
            $role = Role::where('name', $roleName)->orWhere('slug', $roleName)->first();
            $targetRole = $role ? $role->name : 'user';

            // syncRoles handles pivot creation cleanly and prevents duplicate role assignments
            $newUser->syncRoles([$targetRole]);

            // Create and attach company for any role if company details provided
            if (! empty($data['company_name'])) {
                $commodities = $data['commodities_handled'] ?? [];
                if (is_string($commodities)) {
                    $decoded = json_decode($commodities, true);
                    $commodities = is_array($decoded) ? $decoded : array_filter(array_map('trim', explode(',', $commodities)));
                }

                $company = Company::create([
                    'name' => trim((string) $data['company_name']),
                    'contact_person' => trim((string) ($data['contact_person'] ?? $data['name'])),
                    'business_type' => isset($data['business_type']) ? trim((string) $data['business_type']) : null,
                    'gstin' => isset($data['gstin']) ? strtoupper(trim((string) $data['gstin'])) : null,
                    'pan_number' => isset($data['pan_number']) ? trim((string) $data['pan_number']) : null,
                    'year_of_establishment' => isset($data['year_of_establishment']) ? trim((string) $data['year_of_establishment']) : null,
                    'no_of_employees' => isset($data['no_of_employees']) ? trim((string) $data['no_of_employees']) : null,
                    'website' => isset($data['website']) ? trim((string) $data['website']) : null,
                    
                    'country_id' => isset($data['country_id']) ? $data['country_id'] : null,
                    'state_id' => isset($data['state_id']) ? $data['state_id'] : null,
                    'city_id' => isset($data['city_id']) ? $data['city_id'] : null,
                    'address' => isset($data['address']) ? trim((string) $data['address']) : null,
                    'address_line_2' => isset($data['address_line_2']) ? trim((string) $data['address_line_2']) : null,
                    'pin_code' => isset($data['pin_code']) ? trim((string) $data['pin_code']) : null,
                    
                    'business_description' => isset($data['business_description']) ? trim((string) $data['business_description']) : null,
                    
                    'commodities_handled' => $commodities,
                    'trade_preference' => strtolower((string) ($data['trade_preference'] ?? $data['buy_sell_preference'] ?? 'both')),
                    'verification_status' => isset($data['verification_status']) ? trim((string) $data['verification_status']) : 'pending',
                ]);
                
                if (!empty($data['bank_account_number']) || !empty($data['bank_name'])) {
                    \App\Models\CompanyBankDetail::create([
                        'company_id' => $company->id,
                        'account_holder_name' => isset($data['bank_account_holder_name']) ? trim((string) $data['bank_account_holder_name']) : null,
                        'bank_name' => isset($data['bank_name']) ? trim((string) $data['bank_name']) : null,
                        'account_number' => isset($data['bank_account_number']) ? trim((string) $data['bank_account_number']) : null,
                        'ifsc_code' => isset($data['bank_ifsc_code']) ? trim((string) $data['bank_ifsc_code']) : null,
                        'branch_name' => isset($data['bank_branch_name']) ? trim((string) $data['bank_branch_name']) : null,
                        'is_primary' => true,
                    ]);
                }

                $newUser->companies()->attach($company->id, [
                    'role' => $targetRole, // Associate with their primary role
                    'is_primary' => true,
                ]);

                if (!empty($data['business_category_ids']) && is_array($data['business_category_ids'])) {
                    $company->businessCategories()->attach($data['business_category_ids']);
                }
            }

            return $newUser->fresh(['roles', 'companies']);
        });

        // Step 6: Dispatch encrypted credential email job after commit
        SendUserCredentialsEmailJob::dispatch(
            $user->id,
            $user->email,
            $renderedSubject,
            $renderedBody
        )->afterCommit();

        return $user;
    }
}
