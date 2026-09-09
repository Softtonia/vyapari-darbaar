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
          "token": "2|user_sanctum_token..."
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

---

## 10. Commodity Varieties Management (Admin)
*All endpoints require `Authorization: Bearer <admin_token>`, `EnsureAdmin` middleware, and `throttle:admin-api`.*

### Database Schema & Hierarchy
- **Hierarchy:** `CommodityCategory` -> `Commodity` -> `CommoditySubcategory` (optional) -> `CommodityVariety`
- **Foreign Keys:**
  - `commodity_id` (`BIGINT UNSIGNED`, required, FK -> `commodities.id`, `restrictOnDelete`)
  - `commodity_subcategory_id` (`BIGINT UNSIGNED`, nullable, FK -> `commodity_subcategories.id`, `restrictOnDelete`)
- **Uniqueness:** `UNIQUE(commodity_id, slug)` (enforces unique slugs per commodity).

---

### 10.1 List Commodity Varieties
- **Method:** `GET`
- **URI:** `/api/admin/commodity-varieties`
- **Query Parameters:**
  - `page`: integer, min:1 (default: 1)
  - `per_page`: integer, min:1, max:100 (default: 20)
  - `search`: string, max:150 (searches `name_en`, `name_hi`, `slug`)
  - `commodity_category_id`: integer, filter through commodity relationship
  - `commodity_id`: integer, filter by parent commodity
  - `commodity_subcategory_id`: integer, filter by parent subcategory
  - `status`: boolean (1 or 0)
  - `sort_by`: `id`, `commodity_id`, `commodity_subcategory_id`, `name_en`, `name_hi`, `slug`, `sort_order`, `status`, `created_at`, `updated_at` (default: `sort_order`)
  - `sort_order`: `asc` or `desc` (default: `asc`)
- **Relational Consistency:** Returns `422 Unprocessable Entity` if combined category/commodity/subcategory filters do not match.
- **Success (200 OK):**
  ```json
  {
      "status": true,
      "message": "Commodity varieties fetched successfully.",
      "data": {
          "items": [
              {
                  "id": 1,
                  "commodity_id": 5,
                  "commodity_subcategory_id": 8,
                  "commodity": {
                      "id": 5,
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
                  "subcategory": {
                      "id": 8,
                      "commodity_id": 5,
                      "name_en": "Milling Wheat",
                      "name_hi": "मिलिंग गेहूं",
                      "slug": "milling-wheat"
                  },
                  "name_en": "HD-2967",
                  "name_hi": "एचडी-2967",
                  "slug": "hd-2967",
                  "sort_order": 1,
                  "status": true,
                  "created_at": "2026-09-09T10:00:00.000000Z",
                  "updated_at": "2026-09-09T10:00:00.000000Z"
              }
          ],
          "pagination": {
              "current_page": 1,
              "per_page": 20,
              "total": 1,
              "last_page": 1
          }
      }
  }
  ```

---

### 10.2 Commodity Variety Options (Dropdown)
- **Method:** `GET`
- **URI:** `/api/admin/commodity-varieties/options`
- **Query Parameters (Optional):**
  - `commodity_id`: integer
  - `commodity_subcategory_id`: integer
- **Effective Active Visibility:** Varieties only appear if `variety.status = 1`, `commodity.status = 1`, `category.status = 1`, and if assigned to a subcategory, `subcategory.status = 1`.
- **Cache:** Redis cached (`TTL = 3600s`).
- **Success (200 OK):**
  ```json
  {
      "status": true,
      "message": "Commodity variety options retrieved successfully.",
      "data": [
          {
              "id": 1,
              "commodity_id": 5,
              "commodity_subcategory_id": 8,
              "name_en": "HD-2967",
              "name_hi": "एचडी-2967",
              "slug": "hd-2967"
          }
      ]
  }
  ```

---

### 10.3 Create Commodity Variety
- **Method:** `POST`
- **URI:** `/api/admin/commodity-varieties`
- **Request Body (Direct Commodity Variety):**
  ```json
  {
      "commodity_id": 5,
      "commodity_subcategory_id": null,
      "name_en": "HD-2967",
      "name_hi": "एचडी-2967",
      "slug": "hd-2967",
      "description_en": "High yield semi-dwarf wheat variety.",
      "description_hi": "उच्च उपज देने वाली अर्ध-बौनी गेहूं किस्म।",
      "sort_order": 1,
      "status": true
  }
  ```
- **Request Body (Subcategory Variety):**
  ```json
  {
      "commodity_id": 5,
      "commodity_subcategory_id": 8,
      "name_en": "Lokwan Premium",
      "name_hi": "लोकवान प्रीमियम",
      "slug": "lokwan-premium",
      "sort_order": 2,
      "status": true
  }
  ```
- **Success (201 Created):** Returns `CommodityVarietyResource`.

---

### 10.4 Get Commodity Variety Detail
- **Method:** `GET`
- **URI:** `/api/admin/commodity-varieties/{id}`
- **Success (200 OK):** Returns detailed `CommodityVarietyResource`.

---

### 10.5 Update Commodity Variety
- **Method:** `PUT`
- **URI:** `/api/admin/commodity-varieties/{id}`
- **Request Body:**
  ```json
  {
      "commodity_id": 5,
      "commodity_subcategory_id": 8,
      "name_en": "HD-2967 (Certified)",
      "name_hi": "एचडी-2967 (प्रमाणित)",
      "slug": "hd-2967-certified",
      "description_en": "Updated description.",
      "sort_order": 1,
      "status": true
  }
  ```
- **Reassignment Rules:**
  - If `commodity_id` changes, request MUST explicitly specify `commodity_subcategory_id` (either `null` or a valid subcategory belonging to the new commodity).
  - Explicit `commodity_subcategory_id = null` detaches the variety from subcategory, while omitting the field retains existing subcategory.
- **Success (200 OK):** Returns updated `CommodityVarietyResource`.

---

### 10.6 Update Commodity Variety Status
- **Method:** `PATCH`
- **URI:** `/api/admin/commodity-varieties/{id}/status`
- **Request Body:**
  ```json
  {
      "status": false
  }
  ```
- **Success (200 OK):** Returns updated `CommodityVarietyResource`.

---

### 10.7 Bulk Update Commodity Variety Status
- **Method:** `PATCH`
- **URI:** `/api/admin/commodity-varieties/bulk-status`
- **Request Body:**
  ```json
  {
      "ids": [1, 2, 3],
      "status": true
  }
  ```
- **Success (200 OK):**
  ```json
  {
      "status": true,
      "message": "Commodity varieties status updated successfully.",
      "data": {
          "updated_count": 3
      }
  }
  ```

---

### 10.8 Delete Commodity Variety
- **Method:** `DELETE`
- **URI:** `/api/admin/commodity-varieties/{id}`
- **Behavior:** Soft deletes variety and invalidates parent options caches.
- **Success (200 OK):**
  ```json
  {
      "status": true,
      "message": "Commodity variety deleted successfully."
  }
  ```

---

### 10.9 Bulk Delete Commodity Varieties
- **Method:** `POST`
- **URI:** `/api/admin/commodity-varieties/bulk-delete`
- **Request Body:**
  ```json
  {
      "ids": [1, 2, 3]
  }
  ```
- **Behavior:** Validates IDs (max 100, non-deleted, existing), atomically soft-deletes in a single transaction, and purges all affected parent options caches.
- **Success (200 OK):**
  ```json
  {
      "status": true,
      "message": "Commodity varieties deleted successfully.",
      "data": {
          "deleted_count": 3
      }
  }
  ```

---

## 11. Commodity Grade Management (Admin)
*All require `Authorization: Bearer <admin_token>` and `EnsureAdmin` middleware.*

Supported 3-Tier Hierarchy:
1. **Direct Commodity Grade:** `commodity_id`, `commodity_subcategory_id = null`, `commodity_variety_id = null`
2. **Subcategory Grade:** `commodity_id`, `commodity_subcategory_id`, `commodity_variety_id = null`
3. **Direct Variety Grade:** `commodity_id`, `commodity_subcategory_id = null`, `commodity_variety_id` (direct variety)
4. **Subcategory Variety Grade:** `commodity_id`, `commodity_subcategory_id`, `commodity_variety_id` (subcategory-linked variety)

### 11.1 List Commodity Grades
- **Method:** `GET`
- **URI:** `/api/admin/commodity-grades`
- **Query Parameters:**
  - `page`: Page number (default: 1)
  - `per_page`: 1 to 100 (default: 20)
  - `commodity_id`: Filter by commodity
  - `commodity_subcategory_id`: Filter by subcategory
  - `commodity_variety_id`: Filter by variety
  - `commodity_category_id`: Filter by parent category
  - `search`: Filter by `name_en`, `name_hi`, or `slug`
  - `status`: `1` (active) or `0` (inactive)
  - `sort_by`: `id`, `name_en`, `name_hi`, `slug`, `commodity_id`, `commodity_subcategory_id`, `commodity_variety_id`, `sort_order`, `status`, `created_at`, `updated_at` (default `sort_order`)
  - `sort_order`: `asc` or `desc` (default `asc`)
- **Success (200 OK):**
  ```json
  {
      "status": true,
      "message": "Commodity grades fetched successfully.",
      "data": {
          "items": [
              {
                  "id": 1,
                  "commodity_id": 1,
                  "commodity_subcategory_id": 1,
                  "commodity_variety_id": 1,
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
                  "subcategory": {
                      "id": 1,
                      "commodity_id": 1,
                      "name_en": "Milling Wheat",
                      "name_hi": "मिलिंग गेहूं",
                      "slug": "milling-wheat"
                  },
                  "variety": {
                      "id": 1,
                      "commodity_id": 1,
                      "commodity_subcategory_id": 1,
                      "name_en": "HD-2967",
                      "name_hi": "एचडी-2967",
                      "slug": "hd-2967"
                  },
                  "name_en": "Grade A Premium",
                  "name_hi": "ग्रेड ए प्रीमियम",
                  "slug": "grade-a-premium",
                  "sort_order": 1,
                  "status": true,
                  "created_at": "2026-09-09T10:00:00.000000Z",
                  "updated_at": "2026-09-09T10:00:00.000000Z"
              }
          ],
          "pagination": {
              "current_page": 1,
              "per_page": 20,
              "total": 1,
              "last_page": 1
          }
      }
  }
  ```

---

### 11.2 Lightweight Commodity Grade Options (Cached & Filter Precedence)
- **Method:** `GET`
- **URI:** `/api/admin/commodity-grades/options`
- **Query Parameters:**
  - `commodity_variety_id`: (optional)
  - `commodity_subcategory_id`: (optional)
  - `commodity_id`: (optional)
- **Cache Precedence Strategy:**
  - `commodity_variety_id` -> `commodity_grades:options:variety:{id}`
  - `commodity_subcategory_id` -> `commodity_grades:options:subcategory:{id}`
  - `commodity_id` -> `commodity_grades:options:commodity:{id}`
  - None -> `commodity_grades:options:all`
  - Suppresses inactive/soft-deleted parents and verifies relational consistency (422 on contradictory query params).
- **Success (200 OK):**
  ```json
  {
      "status": true,
      "message": "Commodity grade options retrieved successfully.",
      "data": [
          {
              "id": 1,
              "commodity_id": 1,
              "commodity_subcategory_id": 1,
              "commodity_variety_id": 1,
              "name_en": "Grade A Premium",
              "name_hi": "ग्रेड ए प्रीमियम",
              "slug": "grade-a-premium"
          }
      ]
  }
  ```

---

### 11.3 Create Commodity Grade
- **Method:** `POST`
- **URI:** `/api/admin/commodity-grades`
- **Request Body:**
  ```json
  {
      "commodity_id": 1,
      "commodity_subcategory_id": 1,
      "commodity_variety_id": 1,
      "name_en": "Grade A Premium",
      "name_hi": "ग्रेड ए प्रीमियम",
      "slug": "grade-a-premium",
      "description_en": "High grade milling wheat.",
      "description_hi": "उच्च श्रेणी का मिलिंग गेहूं।",
      "sort_order": 1,
      "status": true
  }
  ```
- **Success (201 Created):** Returns `CommodityGradeResource`.

---

### 11.4 Get Commodity Grade Detail
- **Method:** `GET`
- **URI:** `/api/admin/commodity-grades/{id}`
- **Success (200 OK):** Returns detailed `CommodityGradeResource`.

---

### 11.5 Update Commodity Grade
- **Method:** `PUT`
- **URI:** `/api/admin/commodity-grades/{id}`
- **Request Body:**
  ```json
  {
      "commodity_id": 1,
      "commodity_subcategory_id": 1,
      "commodity_variety_id": null,
      "name_en": "Grade A Special",
      "name_hi": "ग्रेड ए स्पेशल",
      "slug": "grade-a-special",
      "description_en": "Updated grade description.",
      "sort_order": 1,
      "status": true
  }
  ```
- **Key Present != Relationship Changed Rules:**
  - Submitting existing `commodity_id` / `commodity_subcategory_id` / `commodity_variety_id` does NOT trigger reassignment checks.
  - If `commodity_id` actually changes, request MUST explicitly specify both `commodity_subcategory_id` and `commodity_variety_id` decisions (or `null`).
  - Explicit `null` detaches relationship; omitted key retains existing database value.
  - Validates full effective parent tuple together (e.g. detaching subcategory while variety requires subcategory returns 422).
- **Success (200 OK):** Returns updated `CommodityGradeResource`.

---

### 11.6 Update Commodity Grade Status
- **Method:** `PATCH`
- **URI:** `/api/admin/commodity-grades/{id}/status`
- **Request Body:**
  ```json
  {
      "status": false
  }
  ```
- **Success (200 OK):** Returns updated `CommodityGradeResource`.

---

### 11.7 Bulk Update Commodity Grade Status
- **Method:** `PATCH`
- **URI:** `/api/admin/commodity-grades/bulk-status`
- **Request Body:**
  ```json
  {
      "ids": [1, 2, 3],
      "status": true
  }
  ```
- **Success (200 OK):**
  ```json
  {
      "status": true,
      "message": "Commodity grades status updated successfully.",
      "data": {
          "updated_count": 3
      }
  }
  ```

---

### 11.8 Delete Commodity Grade
- **Method:** `DELETE`
- **URI:** `/api/admin/commodity-grades/{id}`
- **Behavior:** Soft deletes grade and invalidates affected options caches.
- **Success (200 OK):**
  ```json
  {
      "status": true,
      "message": "Commodity grade deleted successfully."
  }
  ```

---

### 11.9 Bulk Delete Commodity Grades
- **Method:** `POST`
- **URI:** `/api/admin/commodity-grades/bulk-delete`
- **Request Body:**
  ```json
  {
      "ids": [1, 2, 3]
  }
  ```
- **Behavior:** Validates IDs shape, fetches requested non-deleted records with one query, atomically soft-deletes in a single transaction, and purges all affected parent options caches.
- **Success (200 OK):**
  ```json
  {
      "status": true,
      "message": "Commodity grades deleted successfully.",
      "data": {
          "deleted_count": 3
      }
  }
  ```

---

## 12. Site Settings Endpoints (Singleton)

Vyapari Darbaar maintains an application-level singleton record (`id = 1`) for global site metadata, branding logos, and bilingual copy. There are no create, list, or delete endpoints.

### 12.1 Get Public Site Settings
- **Method:** `GET`
- **URI:** `/api/site-settings`
- **Authentication:** Public (No authentication required)
- **Caching:** Cached in Redis under key `site_settings:public` for 3600 seconds (1 hour).
- **Behavior:** Returns bilingual text fields and fully qualified public logo URLs. Internal database IDs, foreign keys, and audit timestamps are intentionally excluded from the public response.
- **Success (200 OK):**
  ```json
  {
      "status": true,
      "message": "Site settings fetched successfully.",
      "data": {
          "site_name_en": "Vyapari Darbar",
          "site_name_hi": "व्यापारी दरबार",
          "site_title_en": "India Premier Mandi Platform",
          "site_title_hi": "भारत का प्रमुख मंडी मंच",
          "site_description_en": "Connecting mandi traders across India.",
          "site_description_hi": "पूरे भारत के मंडी व्यापारियों को जोड़ना।",
          "web_logo": "https://api.vyaparidarbaar.com/storage/site-settings/logos/sample-web.png",
          "mobile_logo": "https://api.vyaparidarbaar.com/storage/site-settings/logos/sample-mobile.png"
      }
  }
  ```

---

### 12.2 Get Admin Site Settings
- **Method:** `GET`
- **URI:** `/api/admin/site-settings`
- **Authentication:** Bearer token (`auth:sanctum`, `admin` middleware)
- **Permission:** `site-setting.view`
- **Behavior:** Fetches current singleton settings directly from database without using public cache.
- **Success (200 OK):**
  ```json
  {
      "status": true,
      "message": "Site settings fetched successfully.",
      "data": {
          "id": 1,
          "site_name_en": "Vyapari Darbar",
          "site_name_hi": "व्यापारी दरबार",
          "site_title_en": "India Premier Mandi Platform",
          "site_title_hi": "भारत का प्रमुख मंडी मंच",
          "site_description_en": "Connecting mandi traders across India.",
          "site_description_hi": "पूरे भारत के मंडी व्यापारियों को जोड़ना।",
          "web_logo": "https://api.vyaparidarbaar.com/storage/site-settings/logos/sample-web.png",
          "mobile_logo": "https://api.vyaparidarbaar.com/storage/site-settings/logos/sample-mobile.png",
          "created_at": "2026-09-09T08:00:00.000000Z",
          "updated_at": "2026-09-09T08:00:00.000000Z"
      }
  }
  ```

---

### 12.3 Update Site Settings
- **Canonical Method:** `PATCH`
- **URI:** `/api/admin/site-settings`
- **Authentication:** Bearer token (`auth:sanctum`, `admin` middleware)
- **Permission:** `site-setting.update`
- **Multipart Form-Data Method Spoofing (For Logo Uploads):**
  - Standard PHP engines do not populate `$_FILES` on raw `PUT`/`PATCH` multipart requests.
  - Clients sending files (`web_logo`, `mobile_logo`) should submit a `POST /api/admin/site-settings` multipart/form-data request with `_method = PATCH`.
  - For text-only updates, clients may send standard JSON with `PATCH /api/admin/site-settings`.
- **Validation Rules:**
  - `site_name_en`: `sometimes|required|string|max:150`
  - `site_name_hi`: `sometimes|nullable|string|max:150`
  - `site_title_en`: `sometimes|nullable|string|max:255`
  - `site_title_hi`: `sometimes|nullable|string|max:255`
  - `site_description_en`: `sometimes|nullable|string|max:5000`
  - `site_description_hi`: `sometimes|nullable|string|max:5000`
  - `web_logo`: `sometimes|file|image|mimes:jpg,jpeg,png,webp|max:2048` (SVG rejected; not nullable)
  - `mobile_logo`: `sometimes|file|image|mimes:jpg,jpeg,png,webp|max:2048` (SVG rejected; not nullable)
- **File & Concurrency Safety:**
  - Database row locked via `lockForUpdate()` during transaction.
  - Newly uploaded files are stored safely in `site-settings/logos` on the `public` storage disk.
  - If DB update fails, all newly stored files are cleaned up immediately and old files remain untouched.
  - Old superseded files are deleted only after DB transaction commits.
  - Public cache key `site_settings:public` is invalidated after successful commit.
- **Request Body Example (JSON / Text Only):**
  ```json
  {
      "site_name_en": "Vyapari Darbaar Global",
      "site_title_en": "India Premier Mandi Platform",
      "site_title_hi": "भारत का प्रमुख मंडी मंच",
      "site_description_en": "Connecting mandi traders across India.",
      "site_description_hi": "पूरे भारत के मंडी व्यापारियों को जोड़ना।"
  }
  ```
- **Request Body Example (Multipart / Form Data with Spoofing):**
  ```http
  POST /api/admin/site-settings
  Content-Type: multipart/form-data

  _method=PATCH
  site_name_en=Vyapari Darbaar Global
  site_name_hi=व्यापारी दरबार
  web_logo=[FILE: logo_web.png]
  mobile_logo=[FILE: logo_mobile.webp]
  ```
- **Success (200 OK):**
  ```json
  {
      "status": true,
      "message": "Site settings updated successfully.",
      "data": {
          "id": 1,
          "site_name_en": "Vyapari Darbaar Global",
          "site_name_hi": "व्यापारी दरबार",
          "site_title_en": "India Premier Mandi Platform",
          "site_title_hi": "भारत का प्रमुख मंडी मंच",
          "site_description_en": "Connecting mandi traders across India.",
          "site_description_hi": "पूरे भारत के मंडी व्यापारियों को जोड़ना।",
          "web_logo": "https://api.vyaparidarbaar.com/storage/site-settings/logos/abc123web.png",
          "mobile_logo": "https://api.vyaparidarbaar.com/storage/site-settings/logos/def456mobile.webp",
          "created_at": "2026-09-09T08:00:00.000000Z",
          "updated_at": "2026-09-09T08:05:00.000000Z"
      }
  }
  ```

---

## 13. Dynamic SMTP Settings Endpoints

Vyapari Darbar maintains a singleton database configuration (`smtp_settings`) allowing administrators to dynamically configure and test SMTP mail transport credentials without mutating environment files.

### 13.1 Get SMTP Settings
- **Method:** `GET`
- **URI:** `/api/admin/settings/smtp`
- **Authentication:** Bearer token (`auth:sanctum`, `admin` middleware)
- **Permission:** `smtp-setting.view`
- **Behavior:** Returns the current dynamic SMTP settings. The password is never exposed; `password_configured: true/false` indicates status.
- **Success (200 OK):**
  ```json
  {
      "status": true,
      "message": "SMTP settings fetched successfully.",
      "data": {
          "mailer": "smtp",
          "host": "smtp.gmail.com",
          "port": 587,
          "username": "user@example.com",
          "from_email": "noreply@vyaparidarbar.com",
          "from_address": "noreply@vyaparidarbar.com",
          "from_name": "Vyapari Darbar",
          "encryption": "tls",
          "status": "active",
          "password_configured": true
      }
  }
  ```

---

### 13.2 Update SMTP Settings
- **Method:** `PUT`
- **URI:** `/api/admin/settings/smtp`
- **Authentication:** Bearer token (`auth:sanctum`, `admin` middleware)
- **Permission:** `smtp-setting.update`
- **Validation Rules:**
  - `mailer`: `required|string|max:50` (e.g. `smtp`)
  - `host`: `required|string|max:255` (e.g. `smtp.gmail.com`)
  - `port`: `required|integer|min:1|max:65535` (e.g. `587`, `465`)
  - `username`: `required|string|max:255`
  - `password`: `required` on initial setup; `nullable` on updates (omitted or `"********"` retains existing password)
  - `from_email`: `required|email|max:255` (supports `from_address` interchangeably)
  - `from_name`: `nullable|string|max:150` (optional)
  - `encryption`: `nullable|string|max:20|in:tls,ssl,starttls,none,smtp,smtps` (optional dropdown)
  - `status`: `required|string|in:active,pending` (supports `'active'`, `'pending'`, `1`, `0`, `true`, `false`)
- **Request Body Example:**
  ```json
  {
      "mailer": "smtp",
      "host": "smtp.gmail.com",
      "port": 587,
      "username": "user@example.com",
      "password": "your-smtp-password",
      "from_email": "noreply@vyaparidarbar.com",
      "from_name": "Vyapari Darbar",
      "encryption": "tls",
      "status": "active"
  }
  ```
- **Success (200 OK):**
  ```json
  {
      "status": true,
      "message": "SMTP settings updated successfully.",
      "data": {
          "mailer": "smtp",
          "host": "smtp.gmail.com",
          "port": 587,
          "username": "user@example.com",
          "from_email": "noreply@vyaparidarbar.com",
          "from_address": "noreply@vyaparidarbar.com",
          "from_name": "Vyapari Darbar",
          "encryption": "tls",
          "status": "active",
          "password_configured": true
      }
  }
  ```

---

### 13.3 Test SMTP Settings
- **Method:** `POST`
- **URI:** `/api/admin/settings/smtp/test`
- **Authentication:** Bearer token (`auth:sanctum`, `admin` middleware)
- **Permission:** `smtp-setting.test`
- **Throttle:** `admin-smtp-test` (5 attempts / minute / admin)
- **Behavior:** Loads the saved database SMTP configuration (even if `status` is currently `false`), rebuilds the transport, and sends a diagnostic HTML test email to the recipient.
- **Request Body:**
  ```json
  {
      "recipient": "admin@vyaparidarbar.com"
  }
  ```
- **Success (200 OK):**
  ```json
  {
      "status": true,
      "message": "SMTP test email sent successfully."
  }
  ```
- **Configuration Missing (422 Unprocessable Entity):**
  ```json
  {
      "status": false,
      "message": "SMTP configuration is not configured.",
      "error": "SMTP_CONFIGURATION_MISSING"
  }
  ```
- **Delivery Failure (422 Unprocessable Entity):**
  ```json
  {
      "status": false,
      "message": "Unable to send SMTP test email.",
      "error": "SMTP_TEST_FAILED"
  }
  ```

---

## 14. User Registration, OTP & Authentication

### 14.1 Send OTP
- **Method:** `POST`
- **URI:** `/api/user/send-otp` (Alias: `/api/user/otp/send`)
- **Throttle:** `user-send-otp` (6 requests / minute / IP)
- **Request Body:**
  ```json
  {
      "email": "ramesh.kumar@example.com",
      "purpose": "registration"
  }
  ```
- **Validation:**
  - `email`: `required|email|max:255` (must be unique if `purpose` is `registration`)
  - `purpose`: `nullable|in:registration,register,login,verification,password_reset` (default `registration`)
- **Success (200 OK):**
  ```json
  {
      "status": true,
      "message": "OTP has been sent to the email address. Valid for 10 minutes.",
      "data": {
          "expires_in_seconds": 600,
          "cooldown_seconds": 60
      }
  }
  ```

---

### 14.2 Verify OTP
- **Method:** `POST`
- **URI:** `/api/user/verify-otp` (Alias: `/api/user/otp/verify`)
- **Throttle:** `user-verify-otp` (10 attempts / minute / IP)
- **Request Body:**
  ```json
  {
      "email": "ramesh.kumar@example.com",
      "otp": "123456",
      "purpose": "registration"
  }
  ```
- **Validation:**
  - `email`: `required|email|max:255`
  - `otp`: `required|string|size:6`
  - `purpose`: `nullable|string` (default `registration`)
- **Success (200 OK):**
  ```json
  {
      "status": true,
      "message": "OTP verified successfully.",
      "data": {
          "email": "ramesh.kumar@example.com",
          "verified": true
      }
  }
  ```

---

### 14.3 User Registration
- **Method:** `POST`
- **URI:** `/api/user/register`
- **Throttle:** `user-register` (10 requests / minute)
- **Request Body:**
  ```json
  {
      "first_name": "Ramesh",
      "last_name": "Kumar",
      "email": "ramesh.kumar@example.com",
      "phone_number": "+919876543210",
      "username": "ramesh.kumar",
      "password": "Password#2026",
      "password_confirmation": "Password#2026",
      "otp": "123456",
      "role": "trader",
      "device_name": "Mobile Android App"
  }
  ```
- **Validation:**
  - `first_name`: `required|string|max:100`
  - `last_name`: `nullable|string|max:100`
  - `email`: `required|email|max:255|unique:users,email`
  - `phone_number`: `nullable|string|max:20|unique:users,phone_number`
  - `username`: `nullable|string|max:50|alpha_dash|unique:users,username`
  - `password`: `required|string|min:8|confirmed`
  - `otp`: `required|string|size:6` (must match active OTP and is consumed upon registration)
  - `role`: `required|in:user,trader,subscriber,advertiser,guest`
  - `device_name`: `nullable|string|max:255`
- **Success (201 Created):**
  ```json
  {
      "status": true,
      "message": "User registered successfully.",
      "data": {
          "token": "1|abcdef123456..."
      }
  }
  ```

---

### 14.4 Refresh Token
- **Method:** `POST`
- **URI:** `/api/user/refresh-token`
- **Authentication:** Bearer token (`auth:sanctum`, `user` guard)
- **Behavior:** Revokes the current token and issues a fresh token for the device.
- **Success (200 OK):**
  ```json
  {
      "status": true,
      "message": "Token refreshed successfully.",
      "data": {
          "token": "2|fedcba654321...",
          "token_type": "Bearer"
      }
  }
  ```

---

## 15. User Activity Tracking Endpoints

### 15.1 Get User Activity Log
- **Method:** `GET`
- **URI:** `/api/user/activities`
- **Authentication:** Bearer token (`auth:sanctum`, `user` guard)
- **Query Parameters:**
  - `event`: optional string filter (e.g., `login`, `logout`, `register`, `password_change`, `password_reset`, `profile_update`)
  - `per_page`: optional integer (1-100, default: 20)
  - `page`: optional integer (default: 1)
- **Success (200 OK):**
  ```json
  {
      "status": true,
      "message": "User activities retrieved successfully.",
      "data": {
          "items": [
              {
                  "id": 1,
                  "event": "login",
                  "description": "User logged in successfully",
                  "ip_address": "127.0.0.1",
                  "user_agent": "Mozilla/5.0 ...",
                  "properties": {
                      "device_name": "Chrome Mobile"
                  },
                  "created_at": "2026-09-09T12:00:00.000000Z"
              }
          ],
          "pagination": {
              "current_page": 1,
              "per_page": 20,
              "total": 1,
              "last_page": 1
          }
      }
  }
  ```

---

## 16. In-App Notification Endpoints

### 16.1 List User Notifications
- **Method:** `GET`
- **URI:** `/api/user/notifications`
- **Authentication:** Bearer token (`auth:sanctum`, `user` guard)
- **Query Parameters:**
  - `status`: optional string filter (`all`, `unread`, `read`; default: `all`)
  - `per_page`: optional integer (1-100, default: 20)
  - `page`: optional integer (default: 1)
- **Success (200 OK):**
  ```json
  {
      "status": true,
      "message": "Notifications retrieved successfully.",
      "data": {
          "items": [
              {
                  "id": "c1f729b4-5f53-4889-b7b5-27473950efec",
                  "title": "Welcome to Vyapari Darbaar",
                  "message": "Start trading and tracking commodity mandi prices.",
                  "action_url": "https://vyaparidarbaar.com/dashboard",
                  "type": "general",
                  "metadata": {},
                  "is_read": false,
                  "read_at": null,
                  "created_at": "2026-09-09T12:00:00.000000Z"
              }
          ],
          "unread_count": 1,
          "pagination": {
              "current_page": 1,
              "per_page": 20,
              "total": 1,
              "last_page": 1
          }
      }
  }
  ```

### 16.2 Get Unread Notification Count
- **Method:** `GET`
- **URI:** `/api/user/notifications/unread-count`
- **Authentication:** Bearer token (`auth:sanctum`, `user` guard)
- **Success (200 OK):**
  ```json
  {
      "status": true,
      "message": "Unread notification count retrieved successfully.",
      "data": {
          "unread_count": 5
      }
  }
  ```

### 16.3 Mark Notification as Read
- **Method:** `PATCH`
- **URI:** `/api/user/notifications/{id}/read`
- **Authentication:** Bearer token (`auth:sanctum`, `user` guard)
- **Success (200 OK):**
  ```json
  {
      "status": true,
      "message": "Notification marked as read successfully.",
      "data": {
          "id": "c1f729b4-5f53-4889-b7b5-27473950efec",
          "title": "Welcome to Vyapari Darbaar",
          "message": "Start trading and tracking commodity mandi prices.",
          "action_url": "https://vyaparidarbaar.com/dashboard",
          "type": "general",
          "metadata": {},
          "is_read": true,
          "read_at": "2026-09-09T12:05:00.000000Z",
          "created_at": "2026-09-09T12:00:00.000000Z"
      }
  }
  ```

### 16.4 Mark All Notifications as Read
- **Method:** `PATCH`
- **URI:** `/api/user/notifications/read-all`
- **Authentication:** Bearer token (`auth:sanctum`, `user` guard)
- **Success (200 OK):**
  ```json
  {
      "status": true,
      "message": "All notifications marked as read successfully.",
      "data": {
          "unread_count": 0
      }
  }
  ```

### 16.5 Delete Specific Notification
- **Method:** `DELETE`
- **URI:** `/api/user/notifications/{id}`
- **Authentication:** Bearer token (`auth:sanctum`, `user` guard)
- **Success (200 OK):**
  ```json
  {
      "status": true,
      "message": "Notification deleted successfully."
  }
  ```

### 16.6 Clear All Read Notifications
- **Method:** `DELETE`
- **URI:** `/api/user/notifications/read`
- **Authentication:** Bearer token (`auth:sanctum`, `user` guard)
- **Success (200 OK):**
  ```json
  {
      "status": true,
      "message": "Read notifications cleared successfully.",
      "data": {
          "deleted_count": 8
      }
  }
  ```

### 16.7 Admin Send / Broadcast Notification
- **Method:** `POST`
- **URI:** `/api/admin/notifications/send`
- **Authentication:** Bearer token (`auth:sanctum`, `admin` guard, `notification.send` permission or `admin` role)
- **Request Body Options:**
  - **Single User:**
    ```json
    {
        "recipient_type": "single",
        "user_id": 15,
        "title": "Account Alert",
        "message": "Your KYC documents have been approved.",
        "type": "kyc_approval",
        "action_url": "https://vyaparidarbaar.com/account/kyc"
    }
    ```
  - **Role Broadcast:**
    ```json
    {
        "recipient_type": "role",
        "role": "trader",
        "title": "Mandi Rates Updated",
        "message": "Today's mandi trading prices have been updated.",
        "type": "market_rates"
    }
    ```
  - **All Users Broadcast:**
    ```json
    {
        "recipient_type": "all",
        "title": "System Update Notice",
        "message": "Vyapari Darbaar will undergo scheduled maintenance at 02:00 AM.",
        "type": "maintenance_announcement"
    }
    ```
- **Success (200 OK):**
  ```json
  {
      "status": true,
      "message": "Notification sent successfully to 120 user(s).",
      "data": {
          "recipient_count": 120
      }
  }
  ```

---

## 17. Trader Company & Company Management Endpoints

### 17.1 View Trader Company Profile (User Side)
- **Method:** `GET`
- **URI:** `/api/user/company`
- **Authentication:** Bearer token (`auth:sanctum`, `user` guard)
- **Success (200 OK):**
  ```json
  {
      "status": true,
      "message": "Company details retrieved successfully.",
      "data": {
          "id": 1,
          "name": "Singhania Agro Traders Pvt Ltd",
          "company_name": "Singhania Agro Traders Pvt Ltd",
          "contact_person": "Vikram Singhania",
          "business_type": "Wholesaler",
          "gstin": "27ABCDE1234F1Z5",
          "country": "India",
          "state": "Maharashtra",
          "city": "Nagpur",
          "address": "Shop 12, APMC Market Yard",
          "commodities_handled": ["Wheat", "Soybean", "Cotton"],
          "trade_preference": "both",
          "buy_sell_preference": "both",
          "verification_status": "pending",
          "created_at": "2026-09-09T12:00:00.000000Z",
          "updated_at": "2026-09-09T12:00:00.000000Z"
      }
  }
  ```

### 17.2 Update Trader Company Profile (User Side)
- **Method:** `PUT` / `PATCH`
- **URI:** `/api/user/company`
- **Authentication:** Bearer token (`auth:sanctum`, `user` guard)
- **Request Body:**
  ```json
  {
      "company_name": "Singhania Global Agro Ltd",
      "contact_person": "Vikram Singhania",
      "business_type": "Exporter",
      "gstin": "27ABCDE1234F1Z5",
      "city": "Nagpur",
      "state": "Maharashtra",
      "address": "APMC Commercial Complex, Wardha Road",
      "commodities_handled": ["Wheat", "Soybean", "Cotton", "Maize"],
      "trade_preference": "both"
  }
  ```
- **Success (200 OK):**
  ```json
  {
      "status": true,
      "message": "Company profile updated successfully.",
      "data": {
          "id": 1,
          "name": "Singhania Global Agro Ltd",
          "city": "Nagpur",
          "commodities_handled": ["Wheat", "Soybean", "Cotton", "Maize"],
          "trade_preference": "both"
      }
  }
  ```

### 17.3 Admin List Companies
- **Method:** `GET`
- **URI:** `/api/admin/companies`
- **Authentication:** Bearer token (`auth:sanctum`, `admin` guard, `companies.view` permission)
- **Query Parameters:**
  - `search`: search term in name, contact_person, gstin, city, state
  - `verification_status`: `pending`, `verified`, `rejected`
  - `state`: filter by state
  - `city`: filter by city
  - `per_page`: items per page (default: 20)
- **Success (200 OK):**
  ```json
  {
      "status": true,
      "message": "Companies retrieved successfully.",
      "data": {
          "items": [
              {
                  "id": 1,
                  "name": "Singhania Agro Traders Pvt Ltd",
                  "contact_person": "Vikram Singhania",
                  "business_type": "Wholesaler",
                  "gstin": "27ABCDE1234F1Z5",
                  "city": "Nagpur",
                  "state": "Maharashtra",
                  "verification_status": "pending",
                  "created_at": "2026-09-09T12:00:00.000000Z"
              }
          ],
          "pagination": {
              "current_page": 1,
              "per_page": 20,
              "total": 1,
              "last_page": 1
          }
      }
  }
  ```

### 17.4 Admin View Single Company
- **Method:** `GET`
- **URI:** `/api/admin/companies/{id}`
- **Authentication:** Bearer token (`auth:sanctum`, `admin` guard, `companies.view` permission)

### 17.5 Admin Update Company Verification Status
- **Method:** `PATCH`
- **URI:** `/api/admin/companies/{id}/status`
- **Authentication:** Bearer token (`auth:sanctum`, `admin` guard, `companies.update` permission)
- **Request Body:**
  ```json
  {
      "verification_status": "verified"
  }
  ```
- **Success (200 OK):**
  ```json
  {
      "status": true,
      "message": "Company verification status updated to 'verified' successfully.",
      "data": {
          "id": 1,
          "verification_status": "verified"
      }
  }
  ```

### 17.6 Admin Delete Company
- **Method:** `DELETE`
- **URI:** `/api/admin/companies/{id}`
- **Authentication:** Bearer token (`auth:sanctum`, `admin` guard, `companies.delete` permission)








