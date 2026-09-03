# Vyapari Darbaar — API Documentation

Comprehensive reference for all REST API endpoints across the Vyapari Darbaar system.

---

## Architecture & Authentication Overview

- **Protocol:** RESTful JSON API
- **Authentication:** Bearer tokens issued via Laravel Sanctum (`auth:sanctum`)
- **Polymorphic Isolation:**
  - Administrator endpoints require `admin` middleware (`EnsureAdmin`).
  - User endpoints require `user` middleware (`EnsureUser`).
  - Cross-model token access is strictly blocked (HTTP 403).
- **Global Error Format:**
  ```json
  {
      "status": false,
      "message": "Error description.",
      "error": "Error details if applicable",
      "errors": {
          "field_name": ["Specific validation error message."]
      }
  }
  ```

---

## 1. Public Authentication Endpoints

### 1.1 Admin Login
- **Method:** `POST`
- **URI:** `/api/admin/login`
- **Throttle:** `admin-login` (5 attempts / minute by SHA1(email + IP))
- **Request Body:**
  ```json
  {
      "email": "admin@example.com",
      "password": "AdminPassword#2026",
      "device_name": "Admin Dashboard"
  }
  ```
- **Validation:**
  - `email`: required, email, max:255
  - `password`: required, string
  - `device_name`: optional, string, max:100
- **Success (200 OK):**
  ```json
  {
      "status": true,
      "message": "Login successful.",
      "data": {
          "token": "1|sanctum_token_string...",
          "token_type": "Bearer",
          "admin": {
              "id": 1,
              "name": "Super Admin",
              "email": "admin@example.com",
              "status": "active"
          }
      }
  }
  ```
- **Error (401 Unauthorized / 422 Unprocessable):**
  ```json
  {
      "status": false,
      "message": "Invalid credentials."
  }
  ```

---

### 1.2 Admin Forgot Password
- **Method:** `POST`
- **URI:** `/api/admin/forgot-password`
- **Throttle:** `admin-password-reset` (5 attempts / minute by SHA1(email + IP))
- **Request Body:**
  ```json
  {
      "email": "admin@example.com"
  }
  ```
- **Security:** Enumeration-safe (returns identical success response whether email exists or not).
- **Success (200 OK):**
  ```json
  {
      "status": true,
      "message": "If your account exists and is active, a password reset link has been sent to your email address.",
      "data": {}
  }
  ```

---

### 1.3 Admin Reset Password
- **Method:** `POST`
- **URI:** `/api/admin/reset-password`
- **Throttle:** `admin-password-reset` (5 attempts / minute)
- **Request Body:**
  ```json
  {
      "token": "reset_token_from_email",
      "email": "admin@example.com",
      "password": "NewStrongAdminPassword#2026",
      "password_confirmation": "NewStrongAdminPassword#2026"
  }
  ```
- **Security:** Successfully resetting password immediately revokes all active Sanctum tokens for that administrator.
- **Success (200 OK):**
  ```json
  {
      "status": true,
      "message": "Password has been reset successfully. Please log in with your new password.",
      "data": {}
  }
  ```

---

### 1.4 User Login
- **Method:** `POST`
- **URI:** `/api/user/login`
- **Throttle:** `user-login` (5 attempts / minute by SHA1(username + IP))
- **Identifier:** Username only (`ajay.kumar`). Email is rejected.
- **Request Body:**
  ```json
  {
      "username": "ajay.kumar",
      "password": "TemporaryPassword123!",
      "device_name": "Mobile App"
  }
  ```
- **Success (200 OK):**
  ```json
  {
      "status": true,
      "message": "Login successful.",
      "data": {
          "token": "2|user_sanctum_token...",
          "token_type": "Bearer",
          "user": {
              "id": 1,
              "name": "Ajay Kumar",
              "username": "ajay.kumar",
              "email": "ajay@example.com",
              "status": "active",
              "must_change_password": true
          }
      }
  }
  ```

---

## 2. Protected Administrator Endpoints
*All require `Authorization: Bearer <admin_token>` and `EnsureAdmin` middleware.*

### 2.1 Admin Profile (GET)
- **Method:** `GET`
- **URI:** `/api/admin/profile`
- **Throttle:** `admin-api` (60 requests / minute)
- **Success (200 OK):**
  ```json
  {
      "status": true,
      "message": "Admin profile retrieved successfully.",
      "data": {
          "id": 1,
          "name": "Super Admin",
          "email": "admin@example.com",
          "status": "active",
          "last_login_at": "2026-09-03T10:00:00.000000Z",
          "created_at": "2026-09-01T00:00:00.000000Z",
          "updated_at": "2026-09-03T10:00:00.000000Z"
      }
  }
  ```

---

### 2.2 Admin Profile Update (PATCH)
- **Method:** `PATCH`
- **URI:** `/api/admin/profile`
- **Throttle:** `admin-api`
- **Request Body:**
  ```json
  {
      "name": "Updated Admin Name",
      "email": "new.admin@example.com",
      "current_password": "CurrentPassword#2026"
  }
  ```
- **Security:** `current_password` is required only if `email` is changing. Changing email revokes all other Admin sessions and removes stale password-reset tokens.
- **Success (200 OK):**
  ```json
  {
      "status": true,
      "message": "Admin profile updated successfully.",
      "data": { ... }
  }
  ```

---

### 2.3 Admin Logout
- **Method:** `POST`
- **URI:** `/api/admin/logout`
- **Behavior:** Revokes current session token only.

---

### 2.4 Admin Logout All
- **Method:** `POST`
- **URI:** `/api/admin/logout-all`
- **Behavior:** Revokes all session tokens for the administrator.

---

## 3. Email Template Management (Admin)
*All require `Authorization: Bearer <admin_token>`.*

### 3.1 List Email Templates
- **Method:** `GET`
- **URI:** `/api/admin/email-templates`
- **Query Parameters:** `page`, `per_page` (default 20, max 100), `search`, `is_active`
- **Performance:** Omits `LONGTEXT` `body` column.

### 3.2 Create Email Template
- **Method:** `POST`
- **URI:** `/api/admin/email-templates`
- **Validation:**
  - `key`: required, regex `/^[A-Z0-9_]+$/`, unique
  - `name`: required, string, max:150
  - `subject`: required, string, max:255 (forbidden to contain `{{TemporaryPassword}}`)
  - `body`: required, string

### 3.3 Preview Email Template
- **Method:** `POST`
- **URI:** `/api/admin/email-templates/preview`
- **Request Body:** `{"key": "USER_ACCOUNT_CREATED", "subject": "...", "body": "..."}`
- **Behavior:** Pure in-memory render with demo data; no side effects.

### 3.4 Get Template by Key
- **Method:** `GET`
- **URI:** `/api/admin/email-templates/by-key/{key}`

### 3.5 Get Template Detail
- **Method:** `GET`
- **URI:** `/api/admin/email-templates/{id}`

### 3.6 Update Email Template
- **Method:** `PUT`
- **URI:** `/api/admin/email-templates/{id}`
- **Rule:** `key` is immutable.

### 3.7 Update Template Status
- **Method:** `PATCH`
- **URI:** `/api/admin/email-templates/{id}/status`
- **Request Body:** `{"is_active": true}`

### 3.8 Delete Email Template
- **Method:** `DELETE`
- **URI:** `/api/admin/email-templates/{id}`
- **Security:** Protected system templates (e.g. `USER_ACCOUNT_CREATED`) cannot be deleted (`422 Unprocessable`).

### 3.9 Bulk Delete Email Templates
- **Method:** `POST` / `DELETE`
- **URI:** `/api/admin/email-templates/bulk-delete`
- **Request Body:** `{"ids": [2, 3, 4]}`
- **Validation:** `ids` array of 1-100 valid template IDs.
- **Security:** Rejects selection if it contains protected system templates (`422 Unprocessable`).

---

## 4. Admin User Management
*All require `Authorization: Bearer <admin_token>`.*

### 4.1 List Users
- **Method:** `GET`
- **URI:** `/api/admin/users`
- **Query Parameters:** `page`, `per_page` (1-100), `search` (name substring, username/email prefix), `status`, `created_from`, `created_to`, `sort_by`, `sort_dir`

### 4.2 Provision User
- **Method:** `POST`
- **URI:** `/api/admin/users`
- **Throttle:** `admin-user-create` (20 / min)
- **Request Body:** `{"name": "Ajay Kumar", "email": "ajay@example.com"}`
- **Behavior:** Preflights template, generates atomic unique username and secure 16-char temporary password, renders snapshot, commits user, and enqueues encrypted job.

### 4.3 View User Detail
- **Method:** `GET`
- **URI:** `/api/admin/users/{id}`
- **Behavior:** Selectively loads creator summary (`creator:id,name`).

### 4.4 Update User
- **Method:** `PUT`
- **URI:** `/api/admin/users/{id}`
- **Editable:** `name`, `email` (`username` is immutable).

### 4.5 Delete User
- **Method:** `DELETE`
- **URI:** `/api/admin/users/{id}`
- **Behavior:** Revokes Sanctum tokens and hard-deletes record.

### 4.6 Bulk Delete Users
- **Method:** `POST` / `DELETE`
- **URI:** `/api/admin/users/bulk-delete`
- **Request Body:** `{"ids": [2, 3, 4]}`
- **Validation:** `ids` array of 1-100 valid user IDs.
- **Behavior:** Revokes all personal access tokens for selected users and deletes them in a single database transaction.

### 4.7 Update User Status
- **Method:** `PATCH`
- **URI:** `/api/admin/users/{id}/status`
- **Request Body:** `{"status": "inactive"}` (`active`, `inactive`, `suspended`)
- **Behavior:** Transitions to `inactive` or `suspended` immediately revoke all active user tokens.

### 4.8 Resend User Credentials
- **Method:** `POST`
- **URI:** `/api/admin/users/{id}/resend-credentials`
- **Throttle:** `admin-user-resend-credentials` (3 attempts / 10 min per Admin + User pair)
- **Behavior:** Generates new temporary password, keeps username unchanged, sets `must_change_password = true`, revokes old tokens, and dispatches encrypted email after commit.

---

## 5. Protected User Endpoints
*All require `Authorization: Bearer <user_token>` and `EnsureUser` middleware.*

### 5.1 User Profile
- **Method:** `GET`
- **URI:** `/api/user/profile`
- **Throttle:** `user-api` (120 requests / min)

### 5.2 Change Password
- **Method:** `POST`
- **URI:** `/api/user/change-password`
- **Throttle:** `user-change-password` (5 attempts / min)
- **Request Body:**
  ```json
  {
      "current_password": "OldPassword123!",
      "password": "NewStrongUserPassword#2026",
      "password_confirmation": "NewStrongUserPassword#2026"
  }
  ```
- **Behavior:** Sets `must_change_password = false`, preserves current device token, and revokes all other device sessions.

### 5.3 User Logout
- **Method:** `POST`
- **URI:** `/api/user/logout`
- **Behavior:** Revokes current device token only.
