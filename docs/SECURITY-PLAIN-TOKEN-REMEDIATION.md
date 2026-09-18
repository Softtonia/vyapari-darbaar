# Security Remediation Plan: Sanctum Plaintext Bearer Token Persistence

**Document Version**: 1.0.0  
**Status**: DRAFT / PENDING IMPLEMENTATION APPROVAL  
**Classification**: HIGH SECURITY REMEDIATION PLAN

---

## 1. Executive Summary & Problem Description

### 1.1 Vulnerability Description
In Laravel Sanctum, authentication tokens are designed to be hashed using **SHA-256** prior to database storage (`personal_access_tokens.token`). The plain-text token is intended to be returned to the client once at issuance time, with the database retaining only the cryptographic one-way hash.

In migration `2026_09_05_000001_add_plain_token_to_personal_access_tokens_table.php`, a nullable text column `plain_token` was added to `personal_access_tokens`. 

Active application auth actions write raw plaintext bearer tokens to this column:
- `app/Actions/Auth/LoginAdminAction.php`
- `app/Actions/Auth/LoginUserAction.php`
- `app/Actions/Auth/RegisterUserAction.php`
- `app/Actions/Auth/RefreshTokenAction.php`

### 1.2 Security Risk Analysis
- **Impact**: Anyone with read access to the MySQL database (including database backups, replication logs, read-only analytics replicas, SQL dumps, or SQL injection vectors) can immediately harvest valid bearer tokens for all active administrators and users without cracking hashes.
- **Session Hijacking**: Compromised plain-text tokens allow unauthorized actors to impersonate administrators and users across all API endpoints until token expiry or manual revocation.

---

## 2. Current Code Usage Audit

| File | Action | Usage of `plain_token` |
| :--- | :--- | :--- |
| `app/Actions/Auth/LoginAdminAction.php` | Admin Login | Retrieves active token from DB or creates new token and updates `plain_token = $tokenResult->plainTextToken`. |
| `app/Actions/Auth/LoginUserAction.php` | User Login | Retrieves active token from DB or creates new token and updates `plain_token = $tokenResult->plainTextToken`. |
| `app/Actions/Auth/RegisterUserAction.php`| User Registration | Stores `$tokenResult->plainTextToken` in `plain_token`. |
| `app/Actions/Auth/RefreshTokenAction.php`| Token Refresh | Updates `plain_token` on new token issuance. |

---

## 3. Remediation Strategy

### 3.1 Architecture Invariants for Remediation
1. **Never Persist Plaintext Tokens**: Remove all assignments to `plain_token`.
2. **Standard Sanctum Hashing**: Rely exclusively on Sanctum's built-in `hash('sha256', $plainTextToken)` stored in `personal_access_tokens.token`.
3. **Client-Side Storage**: Clients are responsible for securely storing their issued bearer token (e.g., in Secure HttpOnly cookies or encrypted mobile keystore).
4. **Active Session Management**: If single-session or device-session limiting is required, manage active sessions by checking token expiration (`expires_at`) and `last_used_at` timestamps on the hashed token records rather than reading back plaintext tokens.

### 3.2 Phased Remediation Plan

#### Phase 1: Code Decoupling
- Refactor `LoginAdminAction`, `LoginUserAction`, `RegisterUserAction`, and `RefreshTokenAction` to cease reading and writing `plain_token`.
- On login:
  - If single-session policy is desired: Revoke existing tokens (`$user->tokens()->delete()`) and issue a fresh token.
  - Return the freshly generated `$tokenResult->plainTextToken` in the API response DTO.

#### Phase 2: Database Migration
- Create a migration to drop the `plain_token` column from `personal_access_tokens`:
```php
Schema::table('personal_access_tokens', function (Blueprint $table) {
    $table->dropColumn('plain_token');
});
```

#### Phase 3: Token Rotation & Expiry Policy
- Configure standard Sanctum token expiration (`config/sanctum.php` $\to$ `expiration => 1440` minutes / 24 hours).
- Implement refresh token rotation where exchanging an expiring token invalidates the old token record and issues a new hashed token.

---

## 4. Testing & Verification Plan

1. **Authentication Suite**:
   - `AdminAuthTest`: Verify admin login returns valid bearer token and subsequent authenticated requests succeed.
   - `UserAuthTest`: Verify user registration, login, and profile retrieval work seamlessly.
2. **Database Verification**:
   - Verify `personal_access_tokens` contains only SHA-256 hashed values in `token` column.
   - Verify `plain_token` column is nonexistent.
3. **Session Revocation**:
   - Verify logout revokes current token.
   - Verify expired tokens are rejected with 401 Unauthorized.
