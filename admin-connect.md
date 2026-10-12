# SmartLive — Admin API Documentation

Backend API for the SmartLive admin panel (user management, hosts, agencies,
resellers, sub-admins, roles, gifts, banners, levels, notifications,
settings, VIP plans, recharges, streams).

- **Base URL**: `https://saptahikgyan.space/smart/php-api/Admin`
- **Auth**: `Authorization: Bearer <admin token>` on **every** endpoint
- **Content-Type**: `application/json` (multipart/form-data accepted where noted)
- **Response envelope**: `{ "success": true|false, "message": "...", "data": {...} }`

---

## 🔐 Admin Authentication

### Super Admin Login

`POST /Admin/login.php`

**Body:**

```json
{
  "email": "admin@livestream.com",
  "password": "Admin@123"
}
```

**Response:**

```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "token": "abc123...",
    "admin": {
      "id": 1,
      "name": "Super Admin",
      "email": "admin@livestream.com"
    }
  }
}
```

> Super admins bypass all permission checks.
> Default seeded admin: `admin@livestream.com` / `Admin@123`

### Sub-Admin Login

`POST /Admin/sub_admin_login.php`

**Body:**

```json
{
  "email": "subadmin@example.com",
  "password": "password123"
}
```

**Response:**

```json
{
  "success": true,
  "message": "Sub-admin login successful",
  "data": {
    "token": "xyz789...",
    "sub_admin": {
      "id": 2,
      "name": "Sub Admin",
      "email": "subadmin@example.com",
      "role": "User Manager",
      "role_id": 1,
      "permissions": ["users.view", "hosts.view"],
      "status": "active"
    },
    "permissions": ["users.view", "hosts.view", "users.edit"]
  }
}
```

> The `permissions` array in the response is the **effective** set
> (role permissions merged with own permissions, union).
> Sub-admins with `status: blocked` cannot log in.

---

## 🔑 Permission System

Every Admin API endpoint checks a permission before running.
Super admins bypass all checks; sub-admins need the matching grant.

### Permission Format

Permissions can be stored in any of these formats:

| Format | Example |
|--------|---------|
| Flat string list | `["users.view", "hosts.edit"]` |
| Flat map | `{"users.view": true}` |
| Nested map | `{"users": {"view": true, "edit": false}}` |
| Wildcard | `["*"]` (full access) |

### Permission → HTTP Method Mapping

| HTTP Method | Required Ability |
|-------------|------------------|
| `GET`       | `<module>.view`  |
| `POST`      | `<module>.edit`  |
| `PUT`       | `<module>.edit`  |
| `PATCH`     | `<module>.edit`  |
| `DELETE`    | `<module>.delete`|

### Modules

`users`, `hosts`, `agencies`, `resellers`, `sub_admins`, `roles`,
`gifts`, `banners`, `levels`, `notifications`, `settings`,
`vip_plans`, `recharges`, `streams`

### Special Rules

- Recharge endpoints (`user_recharge`, `recharges` POST,
  `wallet_adjust`) additionally require `users.edit`
- Blocking a sub-admin kills their active sessions immediately
- Sent notifications cannot be edited (PUT/PATCH return 422)

---

## 📊 API Count Summary

| # | Category | Endpoints |
|---|----------|-----------|
| 1 | Auth | 2 |
| 2 | Users | 6 |
| 3 | Hosts | 3 |
| 4 | Agencies | 2 |
| 5 | Resellers | 2 |
| 6 | Sub-admins | 4 |
| 7 | Roles | 2 |
| 8 | Gifts | 3 |
| 9 | Banners | 3 |
| 10 | Levels | 2 |
| 11 | Notifications | 3 |
| 12 | Settings | 3 |
| 13 | VIP Plans | 2 |
| 14 | Recharges | 3 |
| 15 | Streams | 2 |
| 16 | Wallet Adjust | 1 |
| **Total** | | **43** |

---

## 1. Authentication (2 APIs)

### 1.1 Super Admin Login

`POST /Admin/login.php`

See [Super Admin Login](#super-admin-login) above.

### 1.2 Sub-Admin Login

`POST /Admin/sub_admin_login.php`

See [Sub-Admin Login](#sub-admin-login) above.

---

## 2. Users (6 APIs)

### 2.1 List Users

`GET /Admin/users.php`

**Query params:**
- `?search=name-or-email`
- `?status=active|blocked`
- `?page=1&limit=20`

**Response:**

```json
{
  "success": true,
  "message": "Users loaded",
  "data": {
    "users": [
      {
        "id": 5,
        "name": "John Doe",
        "email": "john@example.com",
        "phone": "+911234567890",
        "country": "IN",
        "avatar": null,
        "bio": "Live streamer",
        "vip": 1,
        "level": 10,
        "status": "active",
        "created_at": "2026-10-02T10:00:00Z",
        "updated_at": "2026-10-02T12:00:00Z"
      }
    ],
    "pagination": {
      "page": 1,
      "limit": 20,
      "total": 150,
      "total_pages": 8
    }
  }
}
```

---

### 2.2 Get User

`GET /Admin/user_get.php?id=5`

**Response:**

```json
{
  "success": true,
  "message": "User loaded",
  "data": {
    "user": { ...user object... }
  }
}
```

---

### 2.3 Create User

`POST /Admin/user_create.php`

**Body:**

```json
{
  "name": "New User",
  "email": "new@example.com",
  "password": "secret123",
  "phone": "+919876543210",
  "country": "IN",
  "status": "active",
  "vip": false,
  "level": 1,
  "coins": 0
}
```

> `phone`, `country`, `status`, `vip`, `level`, `coins` are optional.
> A wallet is auto-created; `coins` sets the starting balance.

**Response (201):**

```json
{
  "success": true,
  "message": "User created",
  "data": {
    "user": { ...created user object... },
    "coins": 0
  }
}
```

---

### 2.4 Update User

`PUT /Admin/user_update.php?id=5`
or `PATCH /Admin/user_update.php?id=5`
or `POST /Admin/user_update.php?id=5`
or `POST /Admin/user_update.php` with `user_id` in body (legacy)

**Body** (any subset):

```json
{
  "name": "Updated Name",
  "email": "newemail@example.com",
  "password": "newpass123",
  "phone": "+919876543210",
  "country": "US",
  "avatar": "https://example.com/avatar.jpg",
  "bio": "Updated bio",
  "status": "active",
  "vip": true,
  "level": 5
}
```

> Setting `status: blocked` immediately kills all the user's sessions.

---

### 2.5 Recharge User Wallet

`POST /Admin/user_recharge.php`

**Body:**

```json
{
  "user_id": 5,
  "coins": 1000,
  "note": "Bonus coins",
  "amount": 10.00,
  "currency": "USD"
}
```

> `note`, `amount`, `currency` are optional.

**Response:**

```json
{
  "success": true,
  "message": "Recharge successful",
  "data": {
    "recharge_id": 20,
    "user_id": 5,
    "coins_added": 1000,
    "balance": 3500
  }
}
```

---

### 2.6 Set User VIP

`POST /Admin/user_vip.php`

**Body:**

```json
{
  "user_id": 5,
  "vip": true,
  "days": 30
}
```

> `days` is ignored when `vip: false`.

**Response:**

```json
{
  "success": true,
  "message": "VIP granted for 30 days",
  "data": {
    "user": {
      "id": 5,
      "name": "John Doe",
      "vip": 1,
      "vip_expires_at": "2026-11-02T10:00:00Z"
    }
  }
}
```

---

### 2.7 Delete User

`POST /Admin/user_delete.php`

**Body:**

```json
{
  "user_id": 5
}
```

**Response:**

```json
{
  "success": true,
  "message": "User deleted successfully",
  "data": {
    "user_id": 5
  }
}
```

---

## 3. Hosts (3 APIs)

### 3.1 Host CRUD

`GET /Admin/hosts.php` — list (with `?page=&limit=&search=&status=online|offline|banned`)
`GET /Admin/hosts.php?id=5` — detail
`POST /Admin/hosts.php` — create
`PUT /Admin/hosts.php?id=5` — full update
`PATCH /Admin/hosts.php?id=5` — partial update
`DELETE /Admin/hosts.php?id=5` — delete

**Create / Update Fields:**

| Field | Type | Notes |
|-------|------|-------|
| `name` | string | required on create |
| `agency_id` | int | optional; FK to agencies |
| `email` | string | optional |
| `phone` | string | optional |
| `followers` | int | default 0 |
| `level` | int | default 1 |
| `earnings` | decimal | default 0.00 |
| `status` | enum | `online` / `offline` / `banned` |

> camelCase aliases accepted (e.g. `agencyId`, `ownerName`).

**List Response:**

```json
{
  "success": true,
  "message": "Loaded",
  "data": {
    "hosts": [
      {
        "id": 3,
        "user_id": 5,
        "name": "Elena Rodriguez",
        "agency_id": 1,
        "email": "elena@example.com",
        "phone": null,
        "followers": 45000,
        "level": 85,
        "earnings": 8900.00,
        "status": "offline",
        "created_at": "...",
        "updated_at": "...",
        "agency_name": "Star Agency"
      }
    ],
    "pagination": { "page": 1, "limit": 20, "total": 10, "total_pages": 1 }
  }
}
```

### 3.2 Toggle Host Status

`PATCH /Admin/host_status.php`
or `POST /Admin/host_status.php`

**Body:**

```json
{
  "id": 5,
  "status": "online"
}
```

> Allowed: `online`, `offline`, `banned`.

### 3.3 Record Host Salary

`POST /Admin/host_salary.php`

**Body:**

```json
{
  "host_id": 5,
  "amount": 500.00,
  "month": "2026-10",
  "note": "October payout"
}
```

> `month` defaults to current month (`YYYY-MM`). `note` is optional.

**Response:**

```json
{
  "success": true,
  "message": "Salary recorded",
  "data": {
    "salary_id": 10,
    "host_id": 5,
    "amount": 500.00,
    "month": "2026-10"
  }
}
```

---

## 4. Agencies (2 APIs)

### 4.1 Agency CRUD

`GET /Admin/agencies.php` — list (with `?page=&limit=&search=&status=active|inactive|banned`)
`GET /Admin/agencies.php?id=5` — detail
`POST /Admin/agencies.php` — create
`PUT /Admin/agencies.php?id=5` — full update
`PATCH /Admin/agencies.php?id=5` — partial update
`DELETE /Admin/agencies.php?id=5` — delete

**Create / Update Fields:**

| Field | Type | Notes |
|-------|------|-------|
| `name` | string | required on create |
| `owner_name` | string | required on create |
| `email` | string | optional |
| `phone` | string | optional |
| `rating` | decimal | default 0.0 |
| `revenue` | decimal | default 0.00 |
| `status` | enum | `active` / `inactive` / `banned` |

### 4.2 Toggle Agency Status

`PATCH /Admin/agency_status.php`
or `POST /Admin/agency_status.php`

**Body:**

```json
{
  "id": 5,
  "status": "inactive"
}
```

> Allowed: `active`, `inactive`, `banned`.

---

## 5. Resellers (2 APIs)

### 5.1 Reseller CRUD

`GET /Admin/resellers.php` — list (with `?page=&limit=&search=&status=active|inactive|banned`)
`GET /Admin/resellers.php?id=5` — detail
`POST /Admin/resellers.php` — create
`PUT /Admin/resellers.php?id=5` — full update
`PATCH /Admin/resellers.php?id=5` — partial update
`DELETE /Admin/resellers.php?id=5` — delete

**Create / Update Fields:**

| Field | Type | Notes |
|-------|------|-------|
| `name` | string | required on create |
| `owner_name` | string | required on create |
| `email` | string | optional |
| `phone` | string | optional |
| `users_served` | int | default 0 |
| `packages` | int | default 0 |
| `revenue` | decimal | default 0.00 |
| `status` | enum | `active` / `inactive` / `banned` |

### 5.2 Toggle Reseller Status

`PATCH /Admin/reseller_status.php`
or `POST /Admin/reseller_status.php`

**Body:**

```json
{
  "id": 5,
  "status": "banned"
}
```

> Allowed: `active`, `inactive`, `banned`.

---

## 6. Sub-admins (4 APIs)

### 6.1 Sub-admin CRUD

`GET /Admin/sub_admins.php` — list (with `?page=&limit=&search=&status=active|blocked`)
`GET /Admin/sub_admins.php?id=5` — detail
`POST /Admin/sub_admins.php` — create
`PUT /Admin/sub_admins.php?id=5` — full update
`PATCH /Admin/sub_admins.php?id=5` — partial update
`DELETE /Admin/sub_admins.php?id=5` — delete

**Create / Update Fields:**

| Field | Type | Notes |
|-------|------|-------|
| `name` | string | required on create |
| `email` | string | required, unique |
| `password` | string | required on create (min 6 chars) |
| `role` | string | optional display name |
| `role_id` | int | optional FK to roles |
| `permissions` | array | optional `["users.view", ...]` |
| `status` | enum | `active` / `blocked` |

**Create Response (201):**

```json
{
  "success": true,
  "message": "Created successfully",
  "data": {
    "id": 3,
    "record": { ...sub-admin object without password_hash... }
  }
}
```

### 6.2 Toggle Sub-admin Status

`PATCH /Admin/sub_admin_status.php`
or `POST /Admin/sub_admin_status.php`

**Body:**

```json
{
  "id": 5,
  "status": "blocked"
}
```

> Blocking a sub-admin kills their active sessions immediately.
> Allowed: `active`, `blocked`.

### 6.3 Set Sub-admin Permissions

`PUT /Admin/sub_admin_permissions.php?id=5`
or `POST /Admin/sub_admin_permissions.php?id=5`

**Body:**

```json
{
  "permissions": ["users.view", "users.edit", "hosts.view"]
}
```

**Response:**

```json
{
  "success": true,
  "message": "Permissions updated",
  "data": {
    "id": 5,
    "permissions": ["users.view", "users.edit", "hosts.view"]
  }
}
```

### 6.4 Sub-admin Login

`POST /Admin/sub_admin_login.php`

See [Sub-Admin Login](#sub-admin-login) above.

---

## 7. Roles (2 APIs)

### 7.1 Role CRUD

`GET /Admin/roles.php` — list (with `?page=&limit=&search=`)
`GET /Admin/roles.php?id=5` — detail
`POST /Admin/roles.php` — create
`PUT /Admin/roles.php?id=5` — full update
`PATCH /Admin/roles.php?id=5` — partial update
`DELETE /Admin/roles.php?id=5` — delete

**Create / Update Fields:**

| Field | Type | Notes |
|-------|------|-------|
| `name` | string | required on create |
| `description` | string | optional |
| `permissions` | object | optional `{"users": {"view": true, "edit": false}}` |

**Permissions format for roles:** nested object.

```json
{
  "permissions": {
    "users": { "view": true, "edit": true, "delete": false },
    "hosts": { "view": true, "edit": false },
    "settings": { "view": true }
  }
}
```

### 7.2 Set Role Permissions

`PUT /Admin/role_permissions.php?id=5`
or `POST /Admin/role_permissions.php?id=5`

**Body:** same as role `permissions` field above.

**Response:**

```json
{
  "success": true,
  "message": "Role permissions updated",
  "data": {
    "id": 5,
    "permissions": { "users": { "view": true, "edit": true } }
  }
}
```

---

## 8. Gifts (3 APIs)

### 8.1 Gift CRUD

`GET /Admin/gifts.php` — list (with `?page=&limit=&search=&status=1|0`)
`GET /Admin/gifts.php?id=5` — detail
`POST /Admin/gifts.php` — create
`PUT /Admin/gifts.php?id=5` — full update
`PATCH /Admin/gifts.php?id=5` — partial update
`DELETE /Admin/gifts.php?id=5` — delete

**Create / Update Fields:**

| Field | Type | Notes |
|-------|------|-------|
| `name` | string | required, unique |
| `category` | string | optional |
| `coins` | int | required (coin cost) |
| `price` | decimal | optional (real-money price) |
| `emoji` | string | optional |
| `icon` | string | optional (default: `gift`) |
| `image_url` | string | optional; or upload via `image` file field |
| `active` | bool | default `true` on create |

**Multipart upload:** Send `image` as a file field (JPG/PNG/GIF/WebP, max 5 MB).
The uploaded file is stored in `/uploads/gifts/` and `image_url` is set
automatically.

### 8.2 Toggle Gift Active

`PATCH /Admin/gift_status.php`
or `POST /Admin/gift_status.php`

**Body:**

```json
{
  "id": 5,
  "active": false
}
```

> Also accepts `status: 0` / `status: 1`.

**Response:**

```json
{
  "success": true,
  "message": "Gift status updated",
  "data": {
    "id": 5,
    "name": "Rose",
    "active": false
  }
}
```

### 8.3 Gift List (App also reads this)

`GET /gifts.php` — public gift catalog (App base URL)

See [Gift Catalog](#51-gift-catalog) in appconnect.md.

---

## 9. Banners (3 APIs)

### 9.1 Banner CRUD

`GET /Admin/banners.php` — list (with `?page=&limit=&search=&status=1|0`)
`GET /Admin/banners.php?id=5` — detail
`POST /Admin/banners.php` — create
`PUT /Admin/banners.php?id=5` — full update
`PATCH /Admin/banners.php?id=5` — partial update
`DELETE /Admin/banners.php?id=5` — delete

**Create / Update Fields:**

| Field | Type | Notes |
|-------|------|-------|
| `name` | string | required |
| `image_url` | string | required; or upload via `image` file field |
| `category` | string | optional |
| `price` | decimal | optional |
| `coins` | int | optional |
| `active` | bool | default `true` on create |

**Multipart upload:** Send `image` as a file field. Stored in `/uploads/banners/`.

### 9.2 Toggle Banner Active

`PATCH /Admin/banner_status.php`
or `POST /Admin/banner_status.php`

**Body:**

```json
{
  "id": 5,
  "active": false
}
```

### 9.3 Global Banners (App reads this)

`GET /banners.php` (not yet implemented; planned)
> Uses App base URL: `https://saptahikgyan.space/smart/php-api/App`

---

## 10. Levels (2 APIs)

### 10.1 Level CRUD

`GET /Admin/levels.php` — list (with `?page=&limit=&search=title`, ordered by level asc)
`GET /Admin/levels.php?id=5` — detail
`POST /Admin/levels.php` — create
`PUT /Admin/levels.php?id=5` — full update
`PATCH /Admin/levels.php?id=5` — partial update
`DELETE /Admin/levels.php?id=5` — delete

**Create / Update Fields:**

| Field | Type | Notes |
|-------|------|-------|
| `level` | int | required, unique |
| `title` | string | required |
| `xp_required` | int | optional |
| `reward` | int | optional (coins granted on reaching level) |
| `color` | string | optional hex (default: `#3b82f6`) |

### 10.2 Default Levels (seeded)

| Level | Title | XP Required | Reward |
|-------|-------|-------------|--------|
| 1 | Newbie | 0 | 0 |
| 10 | Bronze | 5000 | 100 |
| 20 | Silver | 20000 | 500 |
| 30 | Gold | 50000 | 1000 |
| 50 | Platinum | 150000 | 5000 |
| 70 | Diamond | 300000 | 10000 |
| 90 | Supreme | 500000 | 25000 |

---

## 11. Notifications (3 APIs)

### 11.1 Notification CRUD

`GET /Admin/notifications.php` — list (with `?page=&limit=&search=&status=draft|sent`)
`GET /Admin/notifications.php?id=5` — detail
`POST /Admin/notifications.php` — create
`PUT /Admin/notifications.php?id=5` — full update
`PATCH /Admin/notifications.php?id=5` — partial update
`DELETE /Admin/notifications.php?id=5` — delete

**Create / Update Fields:**

| Field | Type | Notes |
|-------|------|-------|
| `title` | string | required |
| `message` | string | required |
| `type` | enum | `info` / `promo` / `alert` (default: `info`) |
| `audience` | enum | `all_users` / `vip_users` / `hosts` / `active_users` (default: `all_users`) |
| `status` | enum | `draft` / `sent` (default: `draft`) |

> Sent notifications are **immutable** — PUT/PATCH return 422.

**Create Response (201):**

```json
{
  "success": true,
  "message": "Created successfully",
  "data": {
    "id": 5,
    "record": {
      "id": 5,
      "title": "Double Beans Event",
      "message": "Send gifts today for 2x beans back",
      "type": "promo",
      "audience": "all_users",
      "status": "draft"
    }
  }
}
```

### 11.2 Send Notification

`POST /Admin/notification_send.php?id=5`

**Response:**

```json
{
  "success": true,
  "message": "Notification sent",
  "data": {
    "id": 5,
    "status": "sent",
    "sent_at": "2026-10-02 18:00:00",
    "audience": "all_users"
  }
}
```

> Local mode: marks as sent. A push provider (FCM/OneSignal) hooks in here.

### 11.3 App Notifications

`GET /notifications.php` (not yet implemented; planned)
> Uses App base URL: `https://saptahikgyan.space/smart/php-api/App`

---

## 12. Settings (3 APIs)

### 12.1 Get All Settings

`GET /Admin/settings.php`

**Response:**

```json
{
  "success": true,
  "message": "Settings loaded",
  "data": {
    "settings": {
      "audioEnabled": "1",
      "videoEnabled": "1",
      "maintenanceMode": "0",
      "registrationEnabled": "1",
      "giftsEnabled": "1",
      "vipEnabled": "1",
      "agencyEnabled": "1",
      "resellerEnabled": "1",
      "pushNotifications": "1",
      "emailNotifications": "0"
    }
  }
}
```

### 12.2 Upsert Settings (Bulk)

`PUT /Admin/settings.php`
or `POST /Admin/settings.php`

**Body:**

```json
{
  "maintenanceMode": true,
  "giftsEnabled": false
}
```

> Booleans stored as `1`/`0`. Arrays are JSON-encoded.

**Response:**

```json
{
  "success": true,
  "message": "Settings saved",
  "data": {
    "settings": {
      "maintenanceMode": "1",
      "giftsEnabled": "0"
    },
    "known_keys": [
      "audioEnabled", "videoEnabled", "maintenanceMode",
      "registrationEnabled", "giftsEnabled", "vipEnabled",
      "agencyEnabled", "resellerEnabled",
      "pushNotifications", "emailNotifications"
    ]
  }
}
```

### 12.3 Update Single Setting

`PATCH /Admin/setting_update.php`
or `PUT /Admin/setting_update.php`
or `POST /Admin/setting_update.php`

**Body:**

```json
{
  "key": "maintenanceMode",
  "value": true
}
```

> `key` can also be passed as `?key=maintenanceMode` in the query string.

**Response:**

```json
{
  "success": true,
  "message": "Setting saved",
  "data": {
    "key": "maintenanceMode",
    "value": "1"
  }
}
```

---

## 13. VIP Plans (2 APIs)

### 13.1 VIP Plan CRUD

`GET /Admin/vip_plans.php` — list (with `?page=&limit=&search=`)
`GET /Admin/vip_plans.php?id=5` — detail
`POST /Admin/vip_plans.php` — create
`PUT /Admin/vip_plans.php?id=5` — full update
`PATCH /Admin/vip_plans.php?id=5` — partial update
`DELETE /Admin/vip_plans.php?id=5` — delete

**Create / Update Fields:**

| Field | Type | Notes |
|-------|------|-------|
| `name` | string | required |
| `price` | decimal | required |
| `currency` | string | optional (default: `USD`) |
| `duration_days` | int | optional (default: 30) |
| `bonus_coins` | int | optional |
| `active` | bool | default `true` on create |

### 13.2 Default VIP Plans (seeded)

| Name | Price | Duration | Bonus Coins |
|------|-------|----------|-------------|
| VIP Bronze | $4.99 | 30 days | 500 |
| VIP Silver | $9.99 | 30 days | 1500 |
| VIP Gold | $19.99 | 90 days | 6000 |

---

## 14. Recharges (3 APIs)

### 14.1 Recharge History

`GET /Admin/recharges.php`

**Query params:**
- `?user_id=5`
- `?type=coins|balance`
- `?page=1&limit=20`

**Response:**

```json
{
  "success": true,
  "message": "Recharges loaded",
  "data": {
    "recharges": [
      {
        "id": 10,
        "user_id": 5,
        "admin_id": 1,
        "amount": 10.00,
        "currency": "USD",
        "type": "coins",
        "coins": 1000,
        "note": "Bonus",
        "created_at": "2026-10-02T12:00:00Z",
        "user_name": "John Doe",
        "admin_name": "Super Admin"
      }
    ],
    "pagination": {
      "page": 1,
      "limit": 20,
      "total": 50,
      "total_pages": 3
    }
  }
}
```

### 14.2 Create Recharge

`POST /Admin/recharges.php`

**Body:**

```json
{
  "user_id": 5,
  "coins": 1000,
  "amount": 10.00,
  "currency": "USD",
  "note": "Bonus recharge"
}
```

> Credits the user's wallet and records a recharge row.
> Requires `users.edit` permission.

**Response (201):**

```json
{
  "success": true,
  "message": "Recharge successful",
  "data": {
    "recharge_id": 15,
    "user_id": 5,
    "coins_added": 1000,
    "balance": 3500
  }
}
```

### 14.3 App Recharge

`POST /wallet_recharge.php` — user-side local recharge
> Uses App base URL: `https://saptahikgyan.space/smart/php-api/App`

See [Recharge Wallet](#33-recharge-wallet) in appconnect.md.

---

## 15. Streams (2 APIs)

### 15.1 List Streams (Admin)

`GET /Admin/streams.php`

**Query params:**
- `?status=live|ended|all` (default: `all`)
- `?search=title-or-host`
- `?page=1&limit=20`

**Response:**

```json
{
  "success": true,
  "message": "Streams loaded",
  "data": {
    "streams": [
      {
        "id": 5,
        "host_id": 3,
        "channel": "stream_3_1727890000",
        "title": "My Live Show",
        "status": "live",
        "viewer_count": 150,
        "peak_viewers": 200,
        "total_views": 150,
        "coins_earned": 5000,
        "started_at": "2026-10-02T18:00:00Z",
        "ended_at": null,
        "host_name": "HostName",
        "host_email": "host@example.com"
      }
    ],
    "pagination": {
      "page": 1,
      "limit": 20,
      "total": 50,
      "total_pages": 3
    }
  }
}
```

### 15.2 Force-End Stream (Moderation)

`POST /Admin/stream_end.php`

**Body:**

```json
{
  "stream_id": 5
}
```

> Force-ends a live stream and removes all active viewers.
> Requires `streams.edit` permission.

**Response:**

```json
{
  "success": true,
  "message": "Stream force-ended",
  "data": {
    "stream_id": 5
  }
}
```

---

## 16. Wallet Adjust (Admin)

### Manual Wallet Adjustment

`POST /Admin/wallet_adjust.php`

**Body:**

```json
{
  "user_id": 5,
  "coins": 100,
  "action": "credit"
}
```

> `action` must be `credit` or `debit`.
> Requires `users.edit` permission.

**Response:**

```json
{
  "success": true,
  "message": "Wallet credit successful",
  "data": {
    "user_id": 5,
    "balance": 3600
  }
}
```

---

## 📂 File Structure

```
├── App/               → App (user-facing) API endpoints
│   ├── signup.php
│   ├── login.php
│   ├── logout.php
│   ├── profile.php
│   ├── update_profile.php
│   ├── change_password.php
│   ├── wallet.php
│   ├── wallet_recharge.php
│   ├── wallet_transactions.php
│   ├── stream_list.php
│   ├── stream_get.php
│   ├── stream_create.php
│   ├── stream_join.php
│   ├── stream_leave.php
│   ├── stream_end.php
│   ├── stream_send_gift.php
│   └── gifts.php
│
├── Admin/             → Admin panel API endpoints
│   ├── login.php
│   ├── sub_admin_login.php
│   ├── users.php
│   ├── user_get.php
│   ├── user_create.php
│   ├── user_update.php
│   ├── user_recharge.php
│   ├── user_vip.php
│   ├── user_delete.php
│   ├── hosts.php
│   ├── host_status.php
│   ├── host_salary.php
│   ├── agencies.php
│   ├── agency_status.php
│   ├── resellers.php
│   ├── reseller_status.php
│   ├── sub_admins.php
│   ├── sub_admin_status.php
│   ├── sub_admin_permissions.php
│   ├── roles.php
│   ├── role_permissions.php
│   ├── gifts.php
│   ├── gift_status.php
│   ├── banners.php
│   ├── banner_status.php
│   ├── levels.php
│   ├── notifications.php
│   ├── notification_send.php
│   ├── settings.php
│   ├── setting_update.php
│   ├── vip_plans.php
│   ├── recharges.php
│   ├── streams.php
│   ├── stream_end.php
│   └── wallet_adjust.php
│
├── utils/             → Shared codebase
│   ├── config.php     → DB credentials + token expiry
│   ├── db.php         → MySQL connection
│   ├── cors.php       → CORS headers
│   ├── response.php   → JSON envelope (json_success / json_error)
│   ├── validation.php → field(), is_valid_*, request_data()
│   ├── auth.php       → token parsing, require_auth, require_admin, permissions
│   ├── wallet.php     → ensure_wallet, wallet_adjust
│   └── crud.php       → Generic CRUD engine for admin modules
│
├── Admin/*.def.php    → Module definitions for the CRUD engine
├── uploads/           → Uploaded images (gifts/, banners/)
├── database.sql       → Full schema + seed data
└── README.md          → Setup instructions
```

---

## 🚀 Quick Start

### 1. Start MySQL

Start MySQL from XAMPP control panel.

### 2. Import Database

Fresh DB:
```bash
mysql -u root --default-character-set=utf8mb4 < database.sql
```

Existing DB (add admin modules):
```bash
mysql -u root --default-character-set=utf8mb4 < database_migrate_3.sql
```

### 3. Start Dev Server

```bash
php -S localhost:8000
```

### 4. Test Login

```bash
curl -X POST http://localhost:8000/Admin/login.php \
  -d "email=admin@livestream.com&password=Admin@123"
```

### 5. Use the Token

```bash
curl http://localhost:8000/Admin/users.php \
  -H "Authorization: Bearer <TOKEN>"
```

---

## 🔧 Configuration

All config is in `utils/config.php`:

```php
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'live_streaming');
define('TOKEN_EXPIRY_DAYS', 7);
```

---

*Document Version: 1.0*
*Last Updated: 2026-10-12*
