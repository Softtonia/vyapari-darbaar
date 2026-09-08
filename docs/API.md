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
- **Global Response Standards:**
  - **Success with payload:**
    ```json
    {
        "status": true,
        "message": "Resource retrieved successfully.",
        "data": { ... }
    }
    ```
  - **Success without payload (e.g. Delete, Logout, Actions):**
    ```json
    {
        "status": true,
        "message": "Action completed successfully."
    }
    ```
  - **Validation Error (HTTP 422):**
    ```json
    {
        "status": false,
        "message": "Validation error.",
        "errors": {
            "field_name": ["Specific validation error message."]
        }
    }
    ```
  - **Authentication / Authorization Error (HTTP 401 / 403):**
    ```json
    {
        "status": false,
        "message": "Error description."
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
      "email": "admin@vyaparidarbaar.com",
      "password": "password",
      "device_name": "Admin Dashboard"
  }
  ```
- **Validation:**
  - `email`: required, email, max:255
  - `password`: required, string
  - `device_name`: optional, string, max:255
- **Success (200 OK):**
  ```json
  {
      "status": true,
      "message": "Login successful.",
      "data": {
          "token": "1|sanctum_token_string..."
      }
  }
  ```
- **Specific Error Responses:**
  - Email not found (401 Unauthorized):
    ```json
    {
        "status": false,
        "message": "No account found with this email address."
    }
    ```
  - Wrong password (401 Unauthorized):
    ```json
    {
        "status": false,
        "message": "Incorrect password."
    }
    ```
  - Inactive account (403 Forbidden):
    ```json
    {
        "status": false,
        "message": "Account is inactive. Please contact the administrator."
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
- **Success (200 OK):**
  ```json
  {
      "status": true,
      "message": "A password reset link has been sent to your email address."
  }
  ```
- **Error (404 Not Found):**
  ```json
  {
      "status": false,
      "message": "No administrator account found with this email address."
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
      "message": "Your password has been reset successfully."
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
              "first_name": "Ajay",
              "last_name": "Kumar",
              "full_name": "Ajay Kumar",
              "phone_number": "+919876543210",
              "username": "ajay.kumar",
              "email": "ajay@example.com",
              "status": "active",
              "must_change_password": true
          }
      }
  }
  ```
- **Specific Error Responses:**
  - Username not found (401 Unauthorized):
    ```json
    {
        "status": false,
        "message": "No account found with this username."
    }
    ```
  - Wrong password (401 Unauthorized):
    ```json
    {
        "status": false,
        "message": "Incorrect password."
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
          "first_name": "System",
          "last_name": "Administrator",
          "full_name": "System Administrator",
          "email": "admin@example.com",
          "status": "active",
          "roles": [
              {
                  "id": 1,
                  "name": "admin",
                  "guard_name": "admin"
              }
          ],
          "last_login_at": "2026-09-03T10:00:00.000000Z",
          "created_at": "2026-09-01T00:00:00.000000Z",
          "updated_at": "2026-09-03T10:00:00.000000Z"
      }
  }
  ```

---

### 2.2 Send Email Update OTP
- **Method:** `POST`
- **URI:** `/api/admin/profile/send-email-otp`
- **Throttle:** `admin-api`
- **Request Body:**
  ```json
  {
      "email": "new.admin@example.com"
  }
  ```
- **Validation:**
  - `email`: required, email, max:255, unique:admins, must be different from current email.
- **Behavior:** Generates a 6-digit OTP valid for 10 minutes (reuses active OTP if requested within 10 min, 60s resend cooldown).
- **Success (200 OK):**
  ```json
  {
      "status": true,
      "message": "OTP has been sent to the email address. Valid for 10 minutes.",
      "data": {
          "remaining_seconds": 600
      }
  }
  ```

---

### 2.3 Admin Profile Update (PATCH)
- **Method:** `PATCH`
- **URI:** `/api/admin/profile`
- **Throttle:** `admin-api`
- **Request Body:**
  ```json
  {
      "first_name": "Super",
      "last_name": "Admin",
      "name": "Super Admin",
      "email": "new.admin@example.com",
      "otp": "123456"
  }
  ```
- **Validation:**
  - `otp`: required when `email` is changed. Must match active OTP sent to the new email.
- **Security:** Changing email revokes all other Admin sessions and removes stale password-reset tokens.
- **Success (200 OK):**
  ```json
  {
      "status": true,
      "message": "Admin profile updated successfully.",
      "data": { ... }
  }
  ```

---

### 2.4 Admin Logout
- **Method:** `POST`
- **URI:** `/api/admin/logout`
- **Behavior:** Revokes current session token only.
- **Success (200 OK):**
  ```json
  {
      "status": true,
      "message": "Logged out successfully."
  }
  ```

---

### 2.5 Admin Logout All
- **Method:** `POST`
- **URI:** `/api/admin/logout-all`
- **Behavior:** Revokes all session tokens for the administrator.
- **Success (200 OK):**
  ```json
  {
      "status": true,
      "message": "Logged out from all devices successfully."
  }
  ```

---

## 3. Email Template Management (Admin)
*All require `Authorization: Bearer <admin_token>`.*

### 3.1 Supported Dynamic Placeholders

| Variable Tag | Variable Name | Description | Example / Usage |
| :--- | :--- | :--- | :--- |
| `{{UserName}}` | **User Full Name** | Full name of the user receiving the email. | `Hello {{UserName}},` &rarr; *Hello Ajay Kumar,* |
| `{{Username}}` | **Username** | Unique login username/ID for the user account. | `Username: {{Username}}` &rarr; *Username: ajay.kumar* |
| `{{TemporaryPassword}}` | **Temporary Password** | System-generated temporary login password. | `Password: {{TemporaryPassword}}` &rarr; *Password: Temp#123* |
| `{{CompanyName}}` | **Company Name** | Application brand name configured in system (`APP_NAME`). | `Regards, {{CompanyName}}` &rarr; *Regards, Vyapari Darbaar* |
| `{{SupportEmail}}` | **Support Email** | Primary support contact email address. | `Contact us at {{SupportEmail}}` &rarr; *support@vyaparidarbaar.com* |

---

### 3.2 Get Supported Placeholders
- **Method:** `GET`
- **URI:** `/api/admin/email-templates/placeholders`
- **Query Parameters:** `key` (optional, e.g. `USER_ACCOUNT_CREATED`)
- **Success (200 OK):**
  ```json
  {
      "status": true,
      "message": "Supported email template placeholders retrieved successfully.",
      "data": [
          {
              "variable": "UserName",
              "tag": "{{UserName}}",
              "label": "User Full Name",
              "description": "The full name of the user receiving the email.",
              "example": "Demo User"
          },
          {
              "variable": "Username",
              "tag": "{{Username}}",
              "label": "Username",
              "description": "The unique username / login ID assigned to the user.",
              "example": "demo.user"
          },
          {
              "variable": "TemporaryPassword",
              "tag": "{{TemporaryPassword}}",
              "label": "Temporary Password",
              "description": "The system-generated temporary password for initial login.",
              "example": "TempExample123!"
          },
          {
              "variable": "CompanyName",
              "tag": "{{CompanyName}}",
              "label": "Company / Application Name",
              "description": "The configured organization or application brand name.",
              "example": "Vyapari Darbaar"
          },
          {
              "variable": "SupportEmail",
              "tag": "{{SupportEmail}}",
              "label": "Support Email",
              "description": "The official support contact email address.",
              "example": "support@vyaparidarbaar.com"
          }
      ]
  }
  ```

---

### 3.3 List Email Templates
- **Method:** `GET`
- **URI:** `/api/admin/email-templates`
- **Query Parameters:** `page`, `per_page` (default 20, max 100), `search`, `is_active`
- **Performance:** Omits `LONGTEXT` `body` column.

### 3.4 Create Email Template
- **Method:** `POST`
- **URI:** `/api/admin/email-templates`
- **Validation:**
  - `key`: required, regex `/^[A-Z0-9_]+$/`, unique
  - `name`: required, string, max:150
  - `subject`: required, string, max:255 (forbidden to contain `{{TemporaryPassword}}`)
  - `body`: required, string (Supports full HTML structure)

### 3.5 Preview Email Template
- **Method:** `POST`
- **URI:** `/api/admin/email-templates/preview`
- **Request Body:** `{"key": "USER_ACCOUNT_CREATED", "subject": "...", "body": "..."}`
- **Behavior:** Pure in-memory render with demo data; no side effects.

### 3.6 Get Template by Key
- **Method:** `GET`
- **URI:** `/api/admin/email-templates/by-key/{key}`

### 3.7 Get Template Detail
- **Method:** `GET`
- **URI:** `/api/admin/email-templates/{id}`
- **Note:** Returns template details along with `supported_placeholders` array.

### 3.8 Update Email Template
- **Method:** `PUT`
- **URI:** `/api/admin/email-templates/{id}`
- **Rule:** `key` is immutable.

### 3.9 Update Template Status
- **Method:** `PATCH`
- **URI:** `/api/admin/email-templates/{id}/status`
- **Request Body:** `{"is_active": true}`

### 3.10 Delete Email Template
- **Method:** `DELETE`
- **URI:** `/api/admin/email-templates/{id}`
- **Security:** Protected system templates cannot be deleted (`422 Unprocessable`).
- **Success (200 OK):**
  ```json
  {
      "status": true,
      "message": "Email template deleted successfully."
  }
  ```

### 3.11 Bulk Delete Email Templates
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
- **Request Body:**
  ```json
  {
      "first_name": "Ajay",
      "last_name": "Kumar",
      "phone_number": "+919876543210",
      "email": "ajay@example.com",
      "role": "user"
  }
  ```
- **Behavior:** Preflights template, generates atomic unique username and secure 16-char temporary password, assigns default Spatie role (`user`), renders snapshot, commits user, and enqueues encrypted job.

### 4.3 View User Detail
- **Method:** `GET`
- **URI:** `/api/admin/users/{id}`
- **Behavior:** Selectively loads creator summary (`creator:id,first_name,last_name,name`) and assigned roles (`roles:id,name,guard_name`).

### 4.4 Update User
- **Method:** `PUT`
- **URI:** `/api/admin/users/{id}`
- **Request Body:**
  ```json
  {
      "first_name": "Ajay",
      "last_name": "Kumar Updated",
      "phone_number": "+919876543210",
      "email": "ajay.updated@example.com",
      "role": "user"
  }
  ```
- **Editable:** `first_name`, `last_name`, `phone_number`, `email`, `role` (`username` is immutable).

### 4.5 Delete User
- **Method:** `DELETE`
- **URI:** `/api/admin/users/{id}`
- **Behavior:** Revokes Sanctum tokens and hard-deletes record.
- **Success (200 OK):**
  ```json
  {
      "status": true,
      "message": "User deleted successfully."
  }
  ```

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
- **Success (200 OK):**
  ```json
  {
      "status": true,
      "message": "Credentials resent successfully."
  }
  ```

---

## 5. Role Management (Admin)
*All require `Authorization: Bearer <admin_token>` and `EnsureAdmin` middleware.*

### 5.1 List Roles
- **Method:** `GET`
- **URI:** `/api/admin/roles`
- **Query Parameters:**
  - `search`: filters `name` and `slug`
  - `status`: `1` (active) or `0` (inactive)
  - `is_system`: `1` (system roles) or `0` (custom roles)
  - `sort_by`: `id`, `name`, `slug`, `status`, `created_at` (default `id`)
  - `sort_order`: `asc` or `desc` (default `desc`)
  - `per_page`: `1` to `100` (default `20`)
- **Success (200 OK):**
  ```json
  {
      "status": true,
      "message": "Roles retrieved successfully.",
      "data": {
          "current_page": 1,
          "data": [
              {
                  "id": 1,
                  "name": "Admin",
                  "slug": "admin",
                  "status": true,
                  "is_system": true,
                  "created_at": "2026-09-07T06:17:15.000000Z",
                  "updated_at": "2026-09-07T06:17:15.000000Z"
              }
          ],
          "first_page_url": "http://localhost:8000/api/admin/roles?page=1",
          "from": 1,
          "last_page": 1,
          "last_page_url": "http://localhost:8000/api/admin/roles?page=1",
          "links": [ ... ],
          "next_page_url": null,
          "path": "http://localhost:8000/api/admin/roles",
          "per_page": 20,
          "prev_page_url": null,
          "to": 1,
          "total": 1
      }
  }
  ```

### 5.2 Create Role
- **Method:** `POST`
- **URI:** `/api/admin/roles`
- **Request Body:**
  ```json
  {
      "name": "Content Editor",
      "slug": "content-editor",
      "status": true
  }
  ```
- **Validation:**
  - `name`: required, string, max:100
  - `slug`: optional (auto-generated from name if omitted), string, max:100, unique:roles,slug
  - `status`: optional, boolean (default: true)
- **Success (201 Created):**
  ```json
  {
      "status": true,
      "message": "Role created successfully.",
      "data": {
          "id": 4,
          "name": "Content Editor",
          "slug": "content-editor",
          "status": true,
          "is_system": false,
          "created_at": "2026-09-07T06:18:00.000000Z",
          "updated_at": "2026-09-07T06:18:00.000000Z"
      }
  }
  ```

### 5.3 View Role Detail
- **Method:** `GET`
- **URI:** `/api/admin/roles/{id}`
- **Success (200 OK):**
  ```json
  {
      "status": true,
      "message": "Role retrieved successfully.",
      "data": {
          "id": 4,
          "name": "Content Editor",
          "slug": "content-editor",
          "status": true,
          "is_system": false,
          "created_at": "2026-09-07T06:18:00.000000Z",
          "updated_at": "2026-09-07T06:18:00.000000Z"
      }
  }
  ```

### 5.4 Update Role
- **Method:** `PUT`
- **URI:** `/api/admin/roles/{id}`
- **Request Body:**
  ```json
  {
      "name": "Senior Content Editor",
      "slug": "senior-content-editor",
      "status": false
  }
  ```
- **Security:** If the role is a protected system role (`admin`, `user`, `guest`), its internal `slug` is preserved and cannot be mutated.
- **Success (200 OK):**
  ```json
  {
      "status": true,
      "message": "Role updated successfully.",
      "data": {
          "id": 4,
          "name": "Senior Content Editor",
          "slug": "senior-content-editor",
          "status": false,
          "is_system": false,
          "created_at": "2026-09-07T06:18:00.000000Z",
          "updated_at": "2026-09-07T06:19:00.000000Z"
      }
  }
  ```

### 5.5 Update Role Status
- **Method:** `PATCH`
- **URI:** `/api/admin/roles/{id}/status`
- **Request Body:**
  ```json
  {
      "status": true
  }
  ```
- **Success (200 OK):**
  ```json
  {
      "status": true,
      "message": "Role status updated successfully.",
      "data": { ... }
  }
  ```

### 5.6 Single Delete Role
- **Method:** `DELETE`
- **URI:** `/api/admin/roles/{id}`
- **Behavior:** Soft deletes the role record.
- **Security:** System roles (`admin`, `user`, `guest`) cannot be deleted (HTTP 403 Forbidden).
- **Success (200 OK):**
  ```json
  {
      "status": true,
      "message": "Role deleted successfully."
  }
  ```
- **Error (403 Forbidden on system role):**
  ```json
  {
      "status": false,
      "message": "System role cannot be deleted."
  }
  ```

### 5.7 Bulk Delete Roles
- **Method:** `DELETE` / `POST`
- **URI:** `/api/admin/roles/bulk-delete`
- **Request Body:**
  ```json
  {
      "ids": [4, 5, 6]
  }
  ```
- **Validation:** `ids` array of 1-100 valid role IDs, distinct integer values.
- **Behavior:** Single optimized `whereIn('id', $ids)->delete()` query in a database transaction.
- **Atomic Failure:** If any ID in the array is a protected system role, the entire operation is rejected and no roles are deleted.
- **Success (200 OK):**
  ```json
  {
      "status": true,
      "message": "Roles deleted successfully.",
      "data": {
          "deleted_count": 3
      }
  }
  ```
- **Error (403 Forbidden):**
  ```json
  {
      "status": false,
      "message": "System roles cannot be deleted."
  }
  ```

---

## 6. Commodity Category Management (Admin)
*All require `Authorization: Bearer <admin_token>` and `EnsureAdmin` middleware.*

### 6.1 List Commodity Categories
- **Method:** `GET`
- **URI:** `/api/admin/commodity-categories`
- **Query Parameters:**
  - `page`: Page number (default: 1)
  - `per_page`: 1 to 100 (default: 20)
  - `search`: Filter by `name_en`, `name_hi`, or `slug`
  - `status`: `1` (active) or `0` (inactive)
  - `sort_by`: `id`, `name_en`, `name_hi`, `slug`, `sort_order`, `status`, `created_at`, `updated_at` (default `sort_order`)
  - `sort_order`: `asc` or `desc` (default `asc`)
- **Success (200 OK):**
  ```json
  {
      "status": true,
      "message": "Commodity categories fetched successfully.",
      "data": {
          "items": [
              {
                  "id": 1,
                  "name_en": "Grains",
                  "name_hi": "अनाज",
                  "slug": "grains",
                  "description_en": "Wheat, Paddy/Rice, Maize, Barley, Millet and other cereal grains.",
                  "description_hi": "गेहूं, धान/चावल, मक्का, जौ, बाजरा एवं अन्य अनाज।",
                  "sort_order": 1,
                  "status": true,
                  "created_at": "2026-09-08T10:00:00.000000Z",
                  "updated_at": "2026-09-08T10:00:00.000000Z"
              }
          ],
          "pagination": {
              "current_page": 1,
              "per_page": 20,
              "total": 9,
              "last_page": 1
          }
      }
  }
  ```

### 6.2 Lightweight Category Options (Cached)
- **Method:** `GET`
- **URI:** `/api/admin/commodity-categories/options`
- **Cache Strategy:** Redis cached (`commodity_categories:options`, TTL: 3600s). Returns only active items (`status=true`), sorted by `sort_order ASC, id ASC`.
- **Success (200 OK):**
  ```json
  {
      "status": true,
      "message": "Commodity category options retrieved successfully.",
      "data": [
          {
              "id": 1,
              "name_en": "Grains",
              "name_hi": "अनाज",
              "slug": "grains"
          },
          {
              "id": 2,
              "name_en": "Pulses",
              "name_hi": "दलहन",
              "slug": "pulses"
          }
      ]
  }
  ```

### 6.3 Create Commodity Category
- **Method:** `POST`
- **URI:** `/api/admin/commodity-categories`
- **Request Body:**
  ```json
  {
      "name_en": "Grains",
      "name_hi": "अनाज",
      "slug": "grains",
      "description_en": "Grain commodities",
      "description_hi": "अनाज कमोडिटी",
      "sort_order": 1,
      "status": true
  }
  ```
- **Validation:**
  - `name_en`: required, string, max:150
  - `name_hi`: optional/nullable, string, max:150
  - `slug`: optional (auto-generated from `name_en` if omitted), string, max:180, globally unique (including soft-deleted)
  - `description_en`: optional, string
  - `description_hi`: optional, string
  - `sort_order`: optional, integer, min:0, max:65535 (default: 0)
  - `status`: optional, boolean (default: true)
- **Success (201 Created):**
  ```json
  {
      "status": true,
      "message": "Commodity category created successfully.",
      "data": {
          "id": 1,
          "name_en": "Grains",
          "name_hi": "अनाज",
          "slug": "grains",
          "description_en": "Grain commodities",
          "description_hi": "अनाज कमोडिटी",
          "sort_order": 1,
          "status": true,
          "created_at": "2026-09-08T10:00:00.000000Z",
          "updated_at": "2026-09-08T10:00:00.000000Z"
      }
  }
  ```

### 6.4 Get Commodity Category Detail
- **Method:** `GET`
- **URI:** `/api/admin/commodity-categories/{id}`
- **Success (200 OK):** Returns single category detail with creator and updater relations.

### 6.5 Update Commodity Category
- **Method:** `PUT`
- **URI:** `/api/admin/commodity-categories/{id}`
- **Behavior:** If `slug` is omitted from payload, the existing slug is retained (prevents accidental URL breakage).
- **Success (200 OK):** Returns updated category representation.

### 6.6 Update Category Status
- **Method:** `PATCH`
- **URI:** `/api/admin/commodity-categories/{id}/status`
- **Request Body:** `{"status": false}`
- **Success (200 OK):** Returns updated category with new status.

### 6.7 Bulk Update Category Status
- **Method:** `PATCH`
- **URI:** `/api/admin/commodity-categories/bulk-status`
- **Request Body:** `{"ids": [1, 2, 3], "status": true}`
- **Success (200 OK):**
  ```json
  {
      "status": true,
      "message": "Commodity categories status updated successfully.",
      "data": {
          "updated_count": 3
      }
  }
  ```

### 6.8 Delete Commodity Category
- **Method:** `DELETE`
- **URI:** `/api/admin/commodity-categories/{id}`
- **Behavior:** Safe soft delete. If category has active downstream dependencies, returns HTTP 409.
- **Success (200 OK):**
  ```json
  {
      "status": true,
      "message": "Commodity category deleted successfully."
  }
  ```
- **Error (409 Conflict - In Use):**
  ```json
  {
      "status": false,
      "message": "Commodity category cannot be deleted because it is currently in use.",
      "error": "CATEGORY_IN_USE"
  }
  ```

### 6.9 Bulk Delete Commodity Categories
- **Method:** `POST` / `DELETE`
- **URI:** `/api/admin/commodity-categories/bulk-delete`
- **Request Body:** `{"ids": [2, 3, 4]}`
- **Validation:** `ids` array of 1-100 valid category IDs.
- **Behavior:** Atomic bulk soft delete inside a transaction. If any category is in use, the entire operation is aborted.
- **Success (200 OK):**
  ```json
  {
      "status": true,
      "message": "Commodity categories deleted successfully.",
      "data": {
          "deleted_count": 3
      }
  }
  ```
- **Error (409 Conflict - In Use):**
  ```json
  {
      "status": false,
      "message": "Some commodity categories cannot be deleted because they are in use.",
      "data": {
          "blocked_ids": [3, 4]
      }
  }
  ```

---

## 7. Commodity Management (Admin)
*All require `Authorization: Bearer <admin_token>` and `EnsureAdmin` middleware.*

### 7.1 List Commodities
- **Method:** `GET`
- **URI:** `/api/admin/commodities`
- **Query Parameters:**
  - `page`: Page number (default: 1)
  - `per_page`: 1 to 100 (default: 20)
  - `commodity_category_id`: Filter by parent category ID
  - `search`: Filter by `name_en`, `name_hi`, or `slug`
  - `status`: `1` (active) or `0` (inactive)
  - `sort_by`: `id`, `name_en`, `name_hi`, `slug`, `commodity_category_id`, `sort_order`, `status`, `created_at`, `updated_at` (default `sort_order`)
  - `sort_order`: `asc` or `desc` (default `asc`)
- **Success (200 OK):**
  ```json
  {
      "status": true,
      "message": "Commodities fetched successfully.",
      "data": {
          "items": [
              {
                  "id": 1,
                  "commodity_category_id": 1,
                  "category": {
                      "id": 1,
                      "name_en": "Grains",
                      "name_hi": "अनाज",
                      "slug": "grains"
                  },
                  "name_en": "Wheat",
                  "name_hi": "गेहूं",
                  "slug": "wheat",
                  "sort_order": 1,
                  "status": true,
                  "created_at": "2026-09-08T10:00:00.000000Z",
                  "updated_at": "2026-09-08T10:00:00.000000Z"
              }
          ],
          "pagination": {
              "current_page": 1,
              "per_page": 20,
              "total": 24,
              "last_page": 2
          }
      }
  }
  ```

### 7.2 Lightweight Commodity Options (Cached & Category Filtered)
- **Method:** `GET`
- **URI:** `/api/admin/commodities/options`
- **Query Parameters:** `commodity_category_id` (optional)
- **Cache Strategy:** Redis cached (`commodities:options:all` or `commodities:options:category:{id}`, TTL: 3600s). Suppresses commodities whose parent category is inactive.
- **Success (200 OK):**
  ```json
  {
      "status": true,
      "message": "Commodity options retrieved successfully.",
      "data": [
          {
              "id": 1,
              "commodity_category_id": 1,
              "name_en": "Wheat",
              "name_hi": "गेहूं",
              "slug": "wheat"
          },
          {
              "id": 2,
              "commodity_category_id": 1,
              "name_en": "Paddy / Rice",
              "name_hi": "धान / चावल",
              "slug": "paddy-rice"
          }
      ]
  }
  ```

### 7.3 Create Commodity
- **Method:** `POST`
- **URI:** `/api/admin/commodities`
- **Request Body:**
  ```json
  {
      "commodity_category_id": 1,
      "name_en": "Wheat",
      "name_hi": "गेहूं",
      "slug": "wheat",
      "description_en": "Standard milling wheat.",
      "description_hi": "मानक मिलिंग गेहूं।",
      "sort_order": 1,
      "status": true
  }
  ```
- **Validation:**
  - `commodity_category_id`: required, integer, must reference active & non-deleted category
  - `name_en`: required, string, max:150
  - `name_hi`: optional, string, max:150
  - `slug`: optional (auto-generated from `name_en` if omitted), string, max:180, globally unique
  - `description_en`: optional, string
  - `description_hi`: optional, string
  - `sort_order`: optional, integer, min:0, max:65535 (default: 0)
  - `status`: optional, boolean (default: true)
- **Success (201 Created):** Returns created commodity resource.

### 7.4 Get Commodity Detail
- **Method:** `GET`
- **URI:** `/api/admin/commodities/{id}`
- **Success (200 OK):** Returns single commodity detail with category, creator, and updater relations.

### 7.5 Update Commodity
- **Method:** `PUT`
- **URI:** `/api/admin/commodities/{id}`
- **Behavior:** Retains existing slug and category if omitted. Reassignment to a different category requires the target category to be active.
- **Success (200 OK):** Returns updated commodity resource.

### 7.6 Update Commodity Status
- **Method:** `PATCH`
- **URI:** `/api/admin/commodities/{id}/status`
- **Request Body:** `{"status": false}`
- **Success (200 OK):** Returns updated commodity.

### 7.7 Bulk Update Commodity Status
- **Method:** `PATCH`
- **URI:** `/api/admin/commodities/bulk-status`
- **Request Body:** `{"ids": [1, 2, 3], "status": true}`
- **Success (200 OK):** Returns `{"status": true, "message": "Commodities status updated successfully.", "data": {"updated_count": 3}}`.

### 7.8 Delete Commodity
- **Method:** `DELETE`
- **URI:** `/api/admin/commodities/{id}`
- **Behavior:** Safe soft delete, checking that no non-deleted subcategories exist. Invalidates category and subcategory option caches.
- **Success (200 OK):** Returns `{"status": true, "message": "Commodity deleted successfully."}`.
- **Error (409 Conflict - In Use):**
  ```json
  {
      "status": false,
      "message": "Commodity cannot be deleted because it has subcategories assigned.",
      "error": "COMMODITY_IN_USE"
  }
  ```

### 7.9 Bulk Delete Commodities
- **Method:** `POST`
- **URI:** `/api/admin/commodities/bulk-delete`
- **Request Body:** `{"ids": [2, 3, 4]}`
- **Validation:** `ids` array of 1-100 valid commodity IDs.
- **Behavior:** Atomic bulk soft delete across multiple categories inside a database transaction. If any commodity has non-deleted subcategories, none are deleted.
- **Success (200 OK):**
  ```json
  {
      "status": true,
      "message": "Commodities deleted successfully.",
      "data": {
          "deleted_count": 3
      }
  }
  ```
- **Error (409 Conflict - In Use):**
  ```json
  {
      "status": false,
      "message": "Some commodities cannot be deleted because they are in use.",
      "data": {
          "blocked_ids": [2]
      }
  }
  ```

---

## 8. Commodity Subcategory Management (Admin)
*All require `Authorization: Bearer <admin_token>` and `EnsureAdmin` middleware.*

### 8.1 List Commodity Subcategories
- **Method:** `GET`
- **URI:** `/api/admin/commodity-subcategories`
- **Query Parameters:**
  - `page`: Page number (default: 1)
  - `per_page`: 1 to 100 (default: 20)
  - `commodity_id`: Filter by parent commodity ID
  - `commodity_category_id`: Filter by grandparent category ID (validated against `commodity_id` if both provided)
  - `search`: Filter by `name_en`, `name_hi`, or `slug`
  - `status`: `1` (active) or `0` (inactive)
  - `sort_by`: `id`, `name_en`, `name_hi`, `slug`, `commodity_id`, `sort_order`, `status`, `created_at`, `updated_at` (default `sort_order`)
  - `sort_order`: `asc` or `desc` (default `asc`)
- **Success (200 OK):**
  ```json
  {
      "status": true,
      "message": "Commodity subcategories fetched successfully.",
      "data": {
          "items": [
              {
                  "id": 1,
                  "commodity_id": 1,
                  "commodity": {
                      "id": 1,
                      "commodity_category_id": 1,
                      "name_en": "Wheat",
                      "name_hi": "गेहूं",
                      "slug": "wheat",
                      "category": {
                          "id": 1,
                          "name_en": "Grains",
                          "name_hi": "अनाज",
                          "slug": "grains"
                      }
                  },
                  "name_en": "Lokwan Wheat",
                  "name_hi": "लोकवान गेहूं",
                  "slug": "lokwan-wheat",
                  "sort_order": 1,
                  "status": true,
                  "created_at": "2026-09-08T10:00:00.000000Z",
                  "updated_at": "2026-09-08T10:00:00.000000Z"
              }
          ],
          "pagination": {
              "current_page": 1,
              "per_page": 20,
              "total": 50,
              "last_page": 3
          }
      }
  }
  ```

### 8.2 Lightweight Subcategory Options (Cached & Commodity Filtered)
- **Method:** `GET`
- **URI:** `/api/admin/commodity-subcategories/options`
- **Query Parameters:** `commodity_id` (optional)
- **Cache Strategy:** Redis cached (`commodity_subcategories:options:all` or `commodity_subcategories:options:commodity:{id}`, TTL: 3600s).
- **Visibility:** Returns records only when subcategory, parent commodity, and grandparent category are all active (`status = true`) and non-deleted.
- **Success (200 OK):**
  ```json
  {
      "status": true,
      "message": "Commodity subcategory options retrieved successfully.",
      "data": [
          {
              "id": 1,
              "commodity_id": 1,
              "name_en": "Lokwan Wheat",
              "name_hi": "लोकवान गेहूं",
              "slug": "lokwan-wheat"
          }
      ]
  }
  ```

### 8.3 Create Commodity Subcategory
- **Method:** `POST`
- **URI:** `/api/admin/commodity-subcategories`
- **Request Body:**
  ```json
  {
      "commodity_id": 1,
      "name_en": "Lokwan Wheat",
      "name_hi": "लोकवान गेहूं",
      "slug": "lokwan-wheat",
      "description_en": "High quality Lokwan wheat.",
      "description_hi": "उच्च गुणवत्ता लोकवान गेहूं।",
      "sort_order": 1,
      "status": true
  }
  ```
- **Validation:**
  - `commodity_id`: required, integer, must reference active & non-deleted Commodity whose parent Category is also active & non-deleted
  - `name_en`: required, string, max:150
  - `name_hi`: optional, string, max:150
  - `slug`: optional (auto-generated from `name_en` if omitted), string, max:180, unique per commodity
  - `description_en`: optional, string
  - `description_hi`: optional, string
  - `sort_order`: optional, integer, min:0, max:65535 (default: 0)
  - `status`: optional, boolean (default: true)
- **Success (201 Created):** Returns created commodity subcategory resource.

### 8.4 Get Commodity Subcategory Detail
- **Method:** `GET`
- **URI:** `/api/admin/commodity-subcategories/{id}`
- **Success (200 OK):** Returns single subcategory detail with commodity hierarchy, creator, and updater relations.

### 8.5 Update Commodity Subcategory
- **Method:** `PUT`
- **URI:** `/api/admin/commodity-subcategories/{id}`
- **Behavior:**
  - If `slug` is omitted during commodity reassignment, retains existing slug and validates against collisions on target commodity.
  - Reassignment to a different commodity requires target commodity and its category to be active.
- **Success (200 OK):** Returns updated commodity subcategory resource.

### 8.6 Update Commodity Subcategory Status
- **Method:** `PATCH`
- **URI:** `/api/admin/commodity-subcategories/{id}/status`
- **Request Body:** `{"status": false}`
- **Success (200 OK):** Returns updated subcategory.

### 8.7 Bulk Update Commodity Subcategory Status
- **Method:** `PATCH`
- **URI:** `/api/admin/commodity-subcategories/bulk-status`
- **Request Body:** `{"ids": [1, 2, 3], "status": true}`
- **Success (200 OK):** Returns `{"status": true, "message": "Commodity subcategories status updated successfully.", "data": {"updated_count": 3}}`.

### 8.8 Delete Commodity Subcategory
- **Method:** `DELETE`
- **URI:** `/api/admin/commodity-subcategories/{id}`
- **Behavior:** Safe soft delete, invalidating parent commodity option caches.
- **Success (200 OK):** Returns `{"status": true, "message": "Commodity subcategory deleted successfully."}`.

### 8.9 Bulk Delete Commodity Subcategories
- **Method:** `POST`
- **URI:** `/api/admin/commodity-subcategories/bulk-delete`
- **Request Body:** `{"ids": [2, 3, 4]}`
- **Validation:** `ids` array of 1-100 valid subcategory IDs.
- **Behavior:** Atomic bulk soft delete across multiple commodities inside a database transaction.
- **Success (200 OK):**
  ```json
  {
      "status": true,
      "message": "Commodity subcategories deleted successfully.",
      "data": {
          "deleted_count": 3
      }
  }
  ```

---

## 9. Protected User Endpoints
*All require `Authorization: Bearer <user_token>` and `EnsureUser` middleware.*

### 9.1 User Profile Detail
- **Method:** `GET`
- **URI:** `/api/user/profile`
- **Throttle:** `user-api` (120 requests / min)
- **Success (200 OK):**
  ```json
  {
      "status": true,
      "message": "User profile retrieved successfully.",
      "data": {
          "id": 1,
          "first_name": "Rahul",
          "last_name": "Sharma",
          "full_name": "Rahul Sharma",
          "phone_number": "+919876543210",
          "username": "rahul.sharma",
          "email": "rahul.sharma@example.com",
          "status": "active",
          "must_change_password": false,
          "roles": [
              {
                  "id": 2,
                  "name": "user",
                  "guard_name": "web"
              }
          ],
          "created_at": "2026-09-08T10:00:00.000000Z",
          "updated_at": "2026-09-08T10:00:00.000000Z"
      }
  }
  ```

### 9.2 Send Email Update OTP (User)
- **Method:** `POST`
- **URI:** `/api/user/profile/send-email-otp`
- **Request Body:**
  ```json
  {
      "email": "new.email@example.com"
  }
  ```
- **Validation:** `email` required, valid email, unique in `users`, must differ from current email.
- **Behavior:** Generates 6-digit numeric OTP valid for 10 minutes, enforces 60-second cooldown per target email, and sends notification email.
- **Success (200 OK):**
  ```json
  {
      "status": true,
      "message": "OTP has been sent to the email address. Valid for 10 minutes.",
      "data": {
          "remaining_seconds": 600
      }
  }
  ```

### 9.3 Update User Profile
- **Method:** `PATCH`
- **URI:** `/api/user/profile`
- **Request Body (Name & Phone):**
  ```json
  {
      "first_name": "Rahul",
      "last_name": "Verma",
      "phone_number": "+919988776655"
  }
  ```
- **Request Body (Email Change):**
  ```json
  {
      "email": "new.email@example.com",
      "otp": "123456"
  }
  ```
- **Behavior:**
  - Name and phone number update directly.
  - If changing email address, requires valid OTP. Upon successful email change, revokes all other active device tokens while preserving current token.
- **Success (200 OK):** Returns updated `UserProfileResource`.

### 9.4 Change Password
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
- **Success (200 OK):**
  ```json
  {
      "status": true,
      "message": "Password changed successfully."
  }
  ```

### 9.5 User Logout
- **Method:** `POST`
- **URI:** `/api/user/logout`
- **Behavior:** Revokes current device token only.
- **Success (200 OK):**
  ```json
  {
      "status": true,
      "message": "Logged out successfully."
  }
  ```

