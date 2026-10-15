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
| 4 | Live Video Streams  | 6         |
| 5 | Gifts               | 2         |
| 6 | Banners & Events    | 1         |
| 7 | Search & Discovery  | 1         |
| 8 | Leaderboard & Ranks | 1         |
| 9 | Party Audio Rooms   | 6         |
| 10| Store & Backpack    | 4         |
| 11| Social / Follows    | 2         |
| 12| Shorts (Reels)      | 2         |
| 13| Level & Earnings    | 2         |
| 14| Notifications       | 1         |
| **Total** | | **39** |


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
  "phone": "9876543210",
  "country_code": "IN",
  "country": "India"
}
```

> `phone`, `country_code` (or `countryCode`), and `country` are optional.

**Response (201):**

```json
{
  "success": true,
  "message": "Account created successfully",
  "data": {
    "token": "8a3efac6377459ea0bd955e96cf89784ccc904560d72ecc00c268f4a281283d5",
    "user_id": "smart-lv-000001",
    "user": {
      "id": 1,
      "user_id": "smart-lv-000001",
      "name": "John Doe",
      "email": "john@example.com",
      "phone": "9876543210",
      "country_code": "IN",
      "country": "India",
      "avatar": null,
      "bio": null,

      "vip": 0,
      "level": 1,
      "xp": 0,
      "status": "active",
      "created_at": "2026-10-14T21:00:00Z",
      "updated_at": "2026-10-14T21:00:00Z"
    }
  }
}
```

> A persistent, strictly monotonic `user_id` (e.g. `smart-lv-000001`) is generated automatically. If an account is deleted, its ID is never reused.
> A 7-day session `token` and wallet (0 coins) are auto-created on signup.

---

### 1.2 Login

`POST /login.php`

**Body (Email or Custom User ID):**

```json
{
  "email": "john@example.com",
  "password": "secret123"
}
```
*or:*
```json
{
  "user_id": "smart-lv-000001",
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
    "user_id": "smart-lv-000001",
    "user": {
      "id": 1,
      "user_id": "smart-lv-000001",
      "name": "John Doe",
      "email": "john@example.com",
      "phone": "+911234567890",
      "avatar": null,
      "bio": null,
      "vip": 0,
      "level": 1,
      "status": "active",
      "created_at": "2026-10-14T21:00:00Z",
      "updated_at": "2026-10-14T21:00:00Z"
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

## 6. Banners & Events (1 API)

### 6.1 Get Active Banners
`GET /banners.php?category=event|home`
- **Auth:** Not required
- **Response:**
```json
{
  "success": true,
  "message": "Banners loaded",
  "data": {
    "banners": [
      {
        "id": 1,
        "name": "Event Schedule",
        "category": "event",
        "price": "0.00",
        "coins": 0,
        "image_url": "https://images.unsplash.com/...",
        "active": 1,
        "created_at": "2026-10-15 13:48:15"
      }
    ]
  }
}
```

---

## 7. Search & Discovery (1 API)

### 7.1 Search
`GET /search.php?q=smart-lv-000001`
- **Auth:** Not required
- Returns matched `users` (by custom ID or name), live `streams`, and party audio `rooms`.

---

## 8. Leaderboard & Rankings (1 API)

### 8.1 Top Stars & Top Gifters
`GET /leaderboard.php?type=stars|gifters&period=daily|weekly|monthly|all`
- **Auth:** Not required
- Returns rankings with rank position (1, 2, 3...), user custom ID, name, avatar, and total coins.

---

## 9. Party Audio Rooms (6 APIs)

### 9.1 List Party Rooms
`GET /room_list.php?category=Party&status=live&page=1&limit=20`
- **Auth:** Not required
- Returns active party rooms, listener count, and active seated `speakers` (avatars/names).

### 9.2 Create Party Room
`POST /room_create.php`
- **Auth:** Required
- **Body:** `{ "title": "Bollywood Night 🎵", "category": "Party", "cover": "url" }`
- Assigns the host to microphone seat #1.

### 9.3 Get Room Details
`GET /room_get.php?id=1`
- **Auth:** Not required
- Returns room info, listener stats, and all 8 seats (showing which user occupies each seat).

### 9.4 Join Party Room
`POST /room_join.php`
- **Auth:** Required
- **Body:** `{ "room_id": 1, "seat_index": 2 }` (seat_index is optional)

### 9.5 Leave Party Room
`POST /room_leave.php`
- **Auth:** Required
- **Body:** `{ "room_id": 1 }` (vacates seat if occupied)

### 9.6 End Party Room
`POST /room_end.php`
- **Auth:** Required (Host only)
- **Body:** `{ "room_id": 1 }`

---

## 10. Store & Backpack (My Bag) (4 APIs)

### 10.1 Store Catalog
`GET /store_items.php?type=frame|entry|ride|badge|bubble`
- **Auth:** Not required
- Returns item catalog with coin price and duration (days).

### 10.2 Buy Store Item
`POST /store_buy.php`
- **Auth:** Required
- **Body:** `{ "item_id": 1 }`
- Deducts coins from wallet and adds the item to the user's bag.

### 10.3 My Bag (Backpack)
`GET /my_bag.php`
- **Auth:** Required
- Returns all purchased items with active status and equipped flag.

### 10.4 Equip / Unequip Item
`POST /equip_item.php`
- **Auth:** Required
- **Body:** `{ "item_id": 1, "equip": true }`

---

## 11. Social / Follows (2 APIs)

### 11.1 Follow / Unfollow
`POST /follow.php`
- **Auth:** Required
- **Body:** `{ "user_id": 5, "action": "follow|unfollow" }`

### 11.2 Followers / Following List
`GET /followers.php?user_id=5&type=followers|following`
- **Auth:** Not required

---

## 12. Shorts Feed (2 APIs)

### 12.1 Shorts List
`GET /shorts_list.php?page=1&limit=10`
- **Auth:** Optional (if token sent, returns `is_liked: true|false`)

### 12.2 Like Short
`POST /short_like.php`
- **Auth:** Required
- **Body:** `{ "short_id": 1, "action": "like|unlike" }`

---

## 13. Level & Host Earnings (2 APIs)

### 13.1 User Level & XP Progress
`GET /level.php`
- **Auth:** Required
- Returns current level, XP, next milestone, progress percentage, and rewards ladder.

### 13.2 Host Earnings Tracking
`GET /earnings.php`
- **Auth:** Required
- Returns total beans earned, this month's earnings, and salary payout history.

---

## 14. Notifications (1 API)

### 14.1 Notifications List
`GET /notifications.php?page=1&limit=20`
- **Auth:** Not required
- Returns platform announcements and promo alerts.

---

## 📁 Seeded Demo Data Available

The database is now populated with live test data:
- **Streamers / Users:** Pari✨ (`smart-lv-000001`), Kashish_singh (`smart-lv-000002`), Riya_Queen, Gaming_Aman, Raj Music, DJ Nightowl, ShayarKumar, GameGuru, MotivatorMax.
- **Banners:** Event Schedule ("Join exciting events & win rewards!"), Mega Star Hunt 2026, Weekend PK Championship.
- **Streams:** Live streams under `Popular`, `Freshers`, `Spotlight`, `PK Matches` with badges (`Super Star`, `Big Star`, `Rising Star`).
- **Party Audio Rooms:** Bollywood Night 🎵, Late Night Chill 🌙, Poetry & Shairi 📝, Gaming Talk 🎮, Morning Motivation ☀️ with seated speakers.
- **Store Items:** Golden Phoenix Frame, Neon Cyber Frame, Diamond Crown, Lamborghini Aventador, Royal Carriage, Cyberpunk Jet.
- **Shorts:** Trending reels with views and likes.

