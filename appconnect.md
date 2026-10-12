# SmartLive — App API Documentation

Backend API for the SmartLive live-streaming app (mobile/web client).

- **Base URL**: `https://saptahikgyan.space/smart/php-api/App`
- **Auth**: `Authorization: Bearer <token>` on every endpoint except signup/login
- **Content-Type**: `application/json` for request/response body (form-encoded also accepted for some endpoints)
- **Response envelope**: `{ "success": true|false, "message": "...", "data": {...} }`

---

## 📊 API Count Summary

| # | Category            | Endpoints |
|---|---------------------|-----------|
| 1 | Authentication      | 4         |
| 2 | User / Profile      | 4         |
| 3 | Wallet              | 3         |
| 4 | Live Streams        | 6         |
| 5 | Gifts               | 2         |
| **Total** | | **19** |

---

## 1. Authentication (4 APIs)

### 1.1 Signup

`POST /signup.php`

**Body:**

```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "secret123",
  "phone": "+911234567890"
}
```

> `phone` is optional.

**Response (201):**

```json
{
  "success": true,
  "message": "Account created successfully",
  "data": {
    "id": 5,
    "name": "John Doe",
    "email": "john@example.com"
  }
}
```

> A wallet (0 coins) is auto-created for the new user.

---

### 1.2 Login

`POST /login.php`

**Body:**

```json
{
  "email": "john@example.com",
  "password": "secret123"
}
```

**Response:**

```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "token": "abc123...",
    "user": {
      "id": 5,
      "name": "John Doe",
      "email": "john@example.com",
      "phone": "+911234567890",
      "avatar": null,
      "bio": null,
      "vip": 0,
      "level": 1,
      "status": "active",
      "created_at": "2026-10-02T10:00:00Z",
      "updated_at": "2026-10-02T10:00:00Z"
    }
  }
}
```

> Save the `token`. It is valid for **7 days**.
> Send it as `Authorization: Bearer <token>` on all subsequent requests.

---

### 1.3 Logout

`POST /logout.php`

**Auth:** Required

**Response:**

```json
{
  "success": true,
  "message": "Logged out successfully",
  "data": null
}
```

---

### 1.4 Change Password

`POST /change_password.php`

**Auth:** Required

**Body:**

```json
{
  "old_password": "secret123",
  "new_password": "newsecret456"
}
```

**Response:**

```json
{
  "success": true,
  "message": "Password changed successfully, please login again",
  "data": null
}
```

> Changing password invalidates all existing sessions for that user.

---

## 2. User / Profile (4 APIs)

### 2.1 Get My Profile

`GET /profile.php`

**Auth:** Required

**Response:**

```json
{
  "success": true,
  "message": "Profile loaded",
  "data": {
    "user": {
      "id": 5,
      "name": "John Doe",
      "email": "john@example.com",
      "phone": "+911234567890",
      "country": null,
      "avatar": null,
      "bio": "Live streamer 🎥",
      "vip": 1,
      "level": 10,
      "status": "active",
      "created_at": "2026-10-02T10:00:00Z",
      "updated_at": "2026-10-02T12:00:00Z"
    }
  }
}
```

---

### 2.2 Update Profile

`POST /update_profile.php`

**Auth:** Required

**Body** (all fields optional, at least one required):

```json
{
  "name": "John Updated",
  "phone": "+919876543210",
  "bio": "Updated bio text",
  "avatar": "https://example.com/avatar.jpg"
}
```

**Response:**

```json
{
  "success": true,
  "message": "Profile updated successfully",
  "data": {
    "user": { ...updated user object... }
  }
}
```

**Validation:**
- `name`: 2–60 characters
- `bio`: max 255 characters

---

### 2.3 Get User by ID (Admin only)

`GET /Admin/user_get.php?id=5`

> Uses Admin base URL: `https://saptahikgyan.space/smart/php-api/Admin`

**Auth:** Admin token required

**Response:**

```json
{
  "success": true,
  "message": "User loaded",
  "data": {
    "user": {
      "id": 5,
      "name": "John Doe",
      "email": "john@example.com",
      "phone": "+911234567890",
      "avatar": null,
      "bio": "Live streamer 🎥",
      "status": "active",
      "created_at": "2026-10-02T10:00:00Z",
      "updated_at": "2026-10-02T12:00:00Z"
    }
  }
}
```

---

### 2.4 User List (Admin only)

`GET /Admin/users.php`

> Uses Admin base URL: `https://saptahikgyan.space/smart/php-api/Admin`

**Auth:** Admin token required

**Query params:**
- `?search=john` — filter by name or email
- `?status=active|blocked`
- `?page=1&limit=20`

**Response:**

```json
{
  "success": true,
  "message": "Users loaded",
  "data": {
    "users": [ ...user objects... ],
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

## 3. Wallet (3 APIs)

### 3.1 Wallet Balance

`GET /wallet.php`

**Auth:** Required

**Response:**

```json
{
  "success": true,
  "message": "Wallet balance",
  "data": {
    "coins": 2500
  }
}
```

---

### 3.2 Transaction History

`GET /wallet_transactions.php`

**Auth:** Required

**Query params:**
- `?type=recharge|gift_sent|gift_received|admin_credit|admin_debit`
- `?page=1&limit=20`

**Response:**

```json
{
  "success": true,
  "message": "Transactions loaded",
  "data": {
    "balance": 2500,
    "transactions": [
      {
        "id": 10,
        "type": "recharge",
        "coins": 500,
        "balance_after": 2500,
        "stream_id": null,
        "description": "Local recharge",
        "created_at": "2026-10-02T12:00:00Z"
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

---

### 3.3 Recharge Wallet

`POST /wallet_recharge.php`

**Auth:** Required

**Body:**

```json
{
  "coins": 500,
  "reference": "optional-payment-ref"
}
```

> **Local mode**: credits coins immediately without a payment gateway.
> Max recharge: 1,000,000 coins per request.

**Response:**

```json
{
  "success": true,
  "message": "Recharge successful",
  "data": {
    "coins_added": 500,
    "balance": 3000
  }
}
```

---

## 4. Live Streams (6 APIs)

### 4.1 List Streams

`GET /stream_list.php`

**Auth:** Not required

**Query params:**
- `?status=live|ended|all` (default: `live`)
- `?search=title` — search by title
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
        "description": "Welcome to my stream!",
        "cover": null,
        "status": "live",
        "viewer_count": 150,
        "peak_viewers": 200,
        "total_views": 150,
        "coins_earned": 5000,
        "started_at": "2026-10-02T18:00:00Z",
        "ended_at": null,
        "host_name": "HostName",
        "host_avatar": "H"
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

---

### 4.2 Get Stream Details

`GET /stream_get.php?id=5`

**Auth:** Not required

**Response:**

```json
{
  "success": true,
  "message": "Stream loaded",
  "data": {
    "stream": {
      "id": 5,
      "host_id": 3,
      "channel": "stream_3_1727890000",
      "title": "My Live Show",
      "description": "Welcome to my stream!",
      "cover": null,
      "status": "live",
      "viewer_count": 150,
      "peak_viewers": 200,
      "total_views": 150,
      "coins_earned": 5000,
      "started_at": "2026-10-02T18:00:00Z",
      "ended_at": null,
      "host_name": "HostName",
      "host_avatar": "H",
      "host_bio": "Streamer bio"
    }
  }
}
```

---

### 4.3 Create Stream (Go Live)

`POST /stream_create.php`

**Auth:** Required

**Body:**

```json
{
  "title": "My Live Stream",
  "description": "Join me!",
  "cover": "https://example.com/cover.jpg"
}
```

> **Validation:** Title is required, max 120 characters.
> A user can host only **one live stream at a time**.

**Response (201):**

```json
{
  "success": true,
  "message": "You are live! Stream created",
  "data": {
    "stream": {
      "id": 5,
      "host_id": 3,
      "channel": "stream_3_1727890000",
      "title": "My Live Stream",
      "description": "Join me!",
      "cover": null,
      "status": "live",
      "viewer_count": 1,
      "peak_viewers": 0,
      "total_views": 1,
      "coins_earned": 0,
      "started_at": "2026-10-02T18:00:00Z",
      "ended_at": null
    }
  }
}
```

> The `channel` value is what the realtime layer will use.

---

### 4.4 Join Stream

`POST /stream_join.php`

**Auth:** Required

**Body:**

```json
{
  "stream_id": 5
}
```

**Response:**

```json
{
  "success": true,
  "message": "Joined the stream",
  "data": {
    "stream_id": 5,
    "counts": {
      "viewer_count": 151,
      "peak_viewers": 201,
      "total_views": 151
    }
  }
}
```

> If already watching, returns `Already watching this stream` without duplicating.

---

### 4.5 Leave Stream

`POST /stream_leave.php`

**Auth:** Required

**Body:**

```json
{
  "stream_id": 5
}
```

**Response:**

```json
{
  "success": true,
  "message": "Left the stream",
  "data": {
    "stream_id": 5,
    "counts": {
      "viewer_count": 150
    }
  }
}
```

---

### 4.6 End Stream

`POST /stream_end.php`

**Auth:** Required (host only)

**Body:**

```json
{
  "stream_id": 5
}
```

**Response:**

```json
{
  "success": true,
  "message": "Stream ended",
  "data": {
    "stream_id": 5,
    "duration_sec": 3600,
    "peak_viewers": 200,
    "average_viewers": 100,
    "coins_earned": 5000,
    "gifts_received": 12,
    "likes_received": 250
  }
}
```

---

## 5. Gifts (2 APIs)

### 5.1 Gift Catalog

`GET /gifts.php`

**Auth:** Not required

**Query params:**
- `?all=1` — include inactive gifts

**Response:**

```json
{
  "success": true,
  "message": "Gifts loaded",
  "data": {
    "gifts": [
      {
        "id": 1,
        "name": "Rose",
        "category": "Flowers",
        "icon": "rose",
        "emoji": "🌹",
        "coins": 1,
        "price": 0.10,
        "image_url": null,
        "active": 1,
        "created_at": "2026-10-02T10:00:00Z",
        "updated_at": "2026-10-02T10:00:00Z"
      },
      {
        "id": 2,
        "name": "Heart",
        "category": "Love",
        "icon": "heart",
        "emoji": "❤️",
        "coins": 5,
        "price": 0.50,
        "image_url": null,
        "active": 1
      }
    ]
  }
}
```

---

### 5.2 Send Gift

`POST /stream_send_gift.php`

**Auth:** Required

**Body:**

```json
{
  "stream_id": 5,
  "gift_id": 2,
  "quantity": 1
}
```

> **Validation:** `quantity` max 100 per request. Cannot gift your own stream.

**Response:**

```json
{
  "success": true,
  "message": "Gift sent successfully",
  "data": {
    "stream_id": 5,
    "gift": "Heart",
    "quantity": 1,
    "coins_spent": 5,
    "your_balance": 2495
  }
}
```

> Atomic operation: debits sender, credits host, records the gift.

---

## 🔐 Authentication Details

### Token Types

| Token Type | Used For | Login Endpoint |
|------------|----------|----------------|
| `user`     | App APIs | `POST /login.php` |
| `admin`    | Admin APIs | `POST /Admin/login.php` (Admin base URL) |
| `sub_admin`| Admin APIs (limited) | `POST /Admin/sub_admin_login.php` (Admin base URL) |

### Sending the Token

The token can be sent in any of these ways:
- `Authorization: Bearer <token>`
- `X-Auth-Token: <token>`
- `?token=<token>` (query parameter)

### Token Expiry

Tokens expire after **7 days**. Expired tokens return `401 Unauthorized`.

### Response Codes

| Code | Meaning |
|------|---------|
| 200  | Success |
| 201  | Created |
| 400  | Bad request / server error |
| 401  | Unauthorized (missing/invalid token) |
| 403  | Forbidden (blocked account) |
| 404  | Not found |
| 405  | Method not allowed |
| 409  | Conflict (e.g. already watching) |
| 422  | Validation error |

---

## ⚙️ Settings

### App Settings (Admin only)

`GET /Admin/settings.php` — list all settings
> Uses Admin base URL: `https://saptahikgyan.space/smart/php-api/Admin`

`PUT /Admin/settings.php` — upsert any subset
> Uses Admin base URL: `https://saptahikgyan.space/smart/php-api/Admin`

**Known setting keys:**

| Key | Description |
|-----|-------------|
| `audioEnabled` | Audio rooms enabled |
| `videoEnabled` | Video streams enabled |
| `maintenanceMode` | Maintenance mode |
| `registrationEnabled` | User signup enabled |
| `giftsEnabled` | Gifts enabled |
| `vipEnabled` | VIP features enabled |
| `agencyEnabled` | Agency module enabled |
| `resellerEnabled` | Reseller module enabled |
| `pushNotifications` | Push notifications enabled |
| `emailNotifications` | Email notifications enabled |

---

## 📁 Default Data

After importing `database.sql`, the following is seeded:

- **Admin:** `admin@livestream.com` / `Admin@123`
- **Gifts:** Rose (1 coin), Heart (5), Teddy (20), Car (50), Rocket (100), Crown (500), Lion (1000)
- **Levels:** Newbie (1), Bronze (10), Silver (20), Gold (30), Platinum (50), Diamond (70), Supreme (90)
- **Settings:** All enabled by default
- **VIP Plans:** Bronze ($4.99/30d), Silver ($9.99/30d), Gold ($19.99/90d)

---

*Document Version: 1.0*
*Last Updated: 2026-10-12*
