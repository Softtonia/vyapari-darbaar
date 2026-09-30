# USER KYC COMPLETE IMPLEMENTATION REPORT

## 1. User KYC architecture
- The `user_kyc_documents` table independently manages user KYC uploads.
- The `kyc_status` column on `users` table manages the overall user KYC state without conflating it with the company.
- A user can upload documents irrespective of whether they have a company or not.

## 2. User KYC APIs
- `GET /api/user/kyc` - List user's KYC documents and current overall status.
- `POST /api/user/kyc` - Upload or replace a document.
- `POST /api/user/kyc/submit` - Submit KYC for review after checking required documents are uploaded and none are in rejected state.
- `DELETE /api/user/kyc/{id}` - Delete a document (only allowed if not verified or under review).

## 3. Admin KYC APIs
- `GET /api/admin/user-kyc` - List users pending review.
- `GET /api/admin/user-kyc/{user}` - Show specific user's KYC documents.
- `GET /api/admin/user-kyc/documents/{id}` - Show individual document details.
- `PATCH /api/admin/user-kyc/documents/{id}/status` - Approve or reject a document (rejecting also moves the user's overall status to rejected).
- `POST /api/admin/user-kyc/{user}/approve` - Approve overall user KYC once all required documents are verified.

## 4. User KYC status flow
- `unverified` (default)
- `pending` (when a document is uploaded)
- `under_review` (when user successfully submits all required documents)
- `verified` (when admin explicitly approves overall KYC)
- `rejected` (if admin rejects a document, the overall status is moved to rejected)

## 5. Document status flow
- `pending` (initial upload or reupload)
- `verified` (approved by admin)
- `rejected` (rejected by admin with a reason stored in `rejection_reason`)

## 6. Business document flow
- Remains completely separate using the `business_documents` table and `companies.verification_status`.
- Modified `CreateUserAction` earlier to properly distinguish business vs user KYC.

## 7. Authorization
- Evaluated and used `auth:sanctum` and existing roles.
- `UserKycController` automatically fetches the authenticated user via `$request->user()` to prevent tampering with `user_id`.
- The routes are placed under the existing `admin` and `user` middleware groups in `routes/api.php` respectively.

## 8. Database changes
- Used `user_kyc_documents` table.
- Added `kyc_status` to `users` table.

## 9. Routes
```
GET|HEAD   api/admin/user-kyc ................................................. admin.user-kyc.index › Api\Admin\AdminUserKycController@index
GET|HEAD   api/admin/user-kyc/documents/{id} ............................ admin.user-kyc.documents.show › Api\Admin\AdminUserKycController@showDocument
PATCH      api/admin/user-kyc/documents/{id}/status .............. admin.user-kyc.documents.update-status › Api\Admin\AdminUserKycController@updateDocumentStatus
GET|HEAD   api/admin/user-kyc/{user} ........................................... admin.user-kyc.show › Api\Admin\AdminUserKycController@show
POST       api/admin/user-kyc/{user}/approve ................................ admin.user-kyc.approve › Api\Admin\AdminUserKycController@approveUserKyc

GET|HEAD   api/user/kyc ................................................................. user.kyc.index › Api\User\UserKycController@index
POST       api/user/kyc ................................................................. user.kyc.store › Api\User\UserKycController@store
POST       api/user/kyc/submit ....................................................... user.kyc.submit › Api\User\UserKycController@submit
DELETE     api/user/kyc/{id} ......................................................... user.kyc.destroy › Api\User\UserKycController@destroy
```

## 10. Files created
- `app/Http/Controllers/Api/User/UserKycController.php`
- `app/Http/Controllers/Api/Admin/AdminUserKycController.php`

## 11. Files modified
- `routes/api.php`
- `app/Http/Resources/AdminUserResource.php`
- `app/Http/Controllers/Api/Admin/AdminUserController.php`
- `tests/Feature/UserKycApiTest.php`

## 12. Tests executed
- `php artisan test --filter UserKycApiTest`

## 13. Test results
- Tests failed due to environment configuration. The testing environment attempted to connect to MySQL (`127.0.0.1:3306`) which was actively refused, rather than using the in-memory SQLite config specified in `phpunit.xml`.

## 14. Legacy `kyc` table status
- The legacy `kyc` table is currently mapped by `KycDocumentController` for potential batch operations or legacy flows.
- **Migration Strategy:** Because it might contain production data, it has not been deleted. To migrate, a script should be written to move records from `kyc` to `user_kyc_documents` mapping the `company_id` to its primary owner `user_id`. Once verified, the old table can be safely dropped in a subsequent release.

## 15. Remaining issues
- Updating the Postman collection to reflect the new endpoints since they are now fully implemented in the code.
- Fixing the local test environment's database connections to allow tests to run properly via SQLite.
