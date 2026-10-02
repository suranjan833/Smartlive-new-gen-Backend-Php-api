# Live Streaming — API (PHP Core + MySQL)

No framework. One file per API. Shared code lives in `utils/`.

```
├── App/         → APIs for the user mobile/web app
├── Admin/       → APIs for the admin panel (users, hosts, agencies, resellers,
│                  sub-admins, roles, gifts, banners, levels, notifications,
│                  settings, vip plans, recharges)
├── utils/       → shared code (db, auth, wallet, validation, responses, cors,
│                  config, crud)
├── uploads/     → gift/banner images (auto-created on first upload)
├── database.sql → FULL schema (import once on a fresh DB)
├── database_migrate_2.sql → wallet + streaming migration (idempotent)
├── database_migrate_3.sql → admin modules migration (idempotent)
└── README.md
```

## 1. Setup (one time)

1. Start **MySQL** from the XAMPP control panel.
2. Import the schema:
   - fresh DB: `C:\xampp\mysql\bin\mysql.exe -u root --default-character-set=utf8mb4 < database.sql`
   - existing DB (pre-admin-modules): `mysql.exe -u root --default-character-set=utf8mb4 < database_migrate_3.sql`
   - or phpMyAdmin → Import.
3. Start the dev server (from the project root):

```bash
C:\xampp\php\php.exe -S localhost:8000
```

Base URL: `http://localhost:8000`
Default admin: `admin@livestream.com` / `Admin@123`
DB credentials: `utils/config.php`

## 2. Auth model

- Login returns a **token** (valid 7 days, stored in `sessions`).
- Send it as `Authorization: Bearer <token>` (or `X-Auth-Token` / `?token=`).
- Every response: `{ "success": true|false, "message": "...", "data": {...} }`

## 3. App APIs (`/App/...`)

**Account**

| Method | Endpoint | Auth | Body / Params |
|--------|----------|------|---------------|
| POST | `/App/signup.php` | — | `name, email, password, phone?` (wallet auto-created) |
| POST | `/App/login.php` | — | `email, password` → `{ token, user }` |
| POST | `/App/logout.php` | user | — |
| GET | `/App/profile.php` | user | — |
| POST | `/App/update_profile.php` | user | any of: `name, phone, bio, avatar` |
| POST | `/App/change_password.php` | user | `old_password, new_password` |

**Wallet**

| Method | Endpoint | Auth | Body / Params |
|--------|----------|------|---------------|
| GET | `/App/wallet.php` | user | — → `{ coins }` |
| GET | `/App/wallet_transactions.php` | user | `?type=&page=&limit=` |
| POST | `/App/wallet_recharge.php` | user | `coins, reference?` (local mode; gateway-ready) |

**Live streaming**

| Method | Endpoint | Auth | Body / Params |
|--------|----------|------|---------------|
| GET | `/App/stream_list.php` | — | `?status=live|ended|all&search=&page=&limit=` |
| GET | `/App/stream_get.php` | — | `?id=5` |
| POST | `/App/stream_create.php` | user | `title, description?, cover?` → `{ stream.channel }` for realtime later |
| POST | `/App/stream_join.php` | user | `stream_id` (bumps viewer counts) |
| POST | `/App/stream_leave.php` | user | `stream_id` |
| POST | `/App/stream_end.php` | user | `stream_id` (host only) |

**Gifts**

| Method | Endpoint | Auth | Body / Params |
|--------|----------|------|---------------|
| GET | `/App/gifts.php` | — | gift catalog with coin prices |
| POST | `/App/stream_send_gift.php` | user | `stream_id, gift_id, quantity` — atomic coin transfer sender → host |

## 4. Admin APIs (`/Admin/...`)

All admin endpoints need `Authorization: Bearer <admin token>`. Every CRUD module follows the same pattern: `GET` list (`?page=&limit=&search=&status=`), `GET ?id=` detail, `POST` create, `PUT/PATCH ?id=` update, `DELETE ?id=` delete.

**Login & users**

| Method | Endpoint | Body / Params |
|--------|----------|---------------|
| POST | `/Admin/login.php` | `email, password` |
| GET | `/Admin/users.php` | `?search=&status=active|blocked&page=&limit=` |
| GET | `/Admin/user_get.php` | `?id=5` |
| POST | `/Admin/user_create.php` | `name, email, password, phone?, country?, status?, vip?, level?, coins?` |
| PUT/PATCH/POST | `/Admin/user_update.php?id=` | any of `name, email, password, phone, country, avatar, bio, status, vip, level` (legacy POST + `user_id` also works) |
| POST | `/Admin/user_recharge.php` | `user_id, coins, note?, amount?, currency?` |
| POST | `/Admin/user_vip.php` | `user_id, vip: true|false, days?` |
| POST | `/Admin/user_delete.php` | `user_id` |

**Hosts**

| Method | Endpoint | Body / Params |
|--------|----------|---------------|
| GET/POST/PUT/PATCH/DELETE | `/Admin/hosts.php` | CRUD; fields `name, agency_id?, email?, phone?, followers?, level?, earnings?, status?` (camelCase aliases like `agencyId` accepted) |
| PATCH/POST | `/Admin/host_status.php` | `id, status: online\|offline\|banned` |
| POST | `/Admin/host_salary.php` | `host_id, amount, month? (YYYY-MM), note?` |

**Agencies**

| Method | Endpoint | Body / Params |
|--------|----------|---------------|
| GET/POST/PUT/PATCH/DELETE | `/Admin/agencies.php` | CRUD; fields `name, owner_name, email?, phone?, rating?, revenue?, status?` |
| PATCH/POST | `/Admin/agency_status.php` | `id, status: active\|inactive\|banned` |

**Resellers**

| Method | Endpoint | Body / Params |
|--------|----------|---------------|
| GET/POST/PUT/PATCH/DELETE | `/Admin/resellers.php` | CRUD; fields `name, owner_name, email?, phone?, users_served?, packages?, revenue?, status?` |
| PATCH/POST | `/Admin/reseller_status.php` | `id, status: active\|inactive\|banned` |

**Sub-admins & roles**

| Method | Endpoint | Body / Params |
|--------|----------|---------------|
| POST | `/Admin/sub_admin_login.php` | `email, password` → `{ token, sub_admin, permissions }` |
| GET/POST/PUT/PATCH/DELETE | `/Admin/sub_admins.php` | CRUD; fields `name, email, password, role?, role_id?, permissions?: ["users.view",...], status?` |
| PATCH/POST | `/Admin/sub_admin_status.php` | `id, status: active\|blocked` (blocked kills sessions) |
| PUT/POST | `/Admin/sub_admin_permissions.php?id=` | `permissions: ["users.view", ...]` |
| GET/POST/PUT/PATCH/DELETE | `/Admin/roles.php` | CRUD; fields `name, description?, permissions?: {...}` |
| PUT/POST | `/Admin/role_permissions.php?id=` | `permissions: {"users":{"view":true,...}}` |

**How sub-admin permissions gate the API** — a sub-admin token works on every `/Admin/*` endpoint, but each endpoint first checks the matching permission:

- List/detail (`GET`) → `<module>.view` · Create/update/status (`POST/PUT/PATCH`) → `<module>.edit` · Delete (`DELETE`) → `<module>.delete`
- Modules: `users, hosts, agencies, resellers, sub_admins, roles, gifts, banners, levels, notifications, settings, vip_plans, recharges, streams`
- The effective grants = the sub-admin's own `permissions` **union** their role's `permissions` (when `role_id` is set)
- Formats accepted for permissions: `["users.view"]`, `{"users":{"view":true,"edit":false}}`, `{"users.view": true}` or `["*"]` (full access)
- Super admins (`/Admin/login.php`) bypass every permission check
- Recharge endpoints (`user_recharge`, `recharges` POST, `wallet_adjust`) additionally require `users.edit`
- Example: a sub-admin with `users.view` only can list users but gets `403 Permission denied: users.edit is required` when updating one

**Gifts & banners**

| Method | Endpoint | Body / Params |
|--------|----------|---------------|
| GET/POST/PUT/PATCH/DELETE | `/Admin/gifts.php` | CRUD; fields `name, coins, category?, emoji?, price?, image_url?, active?` — multipart `image` upload supported |
| PATCH/POST | `/Admin/gift_status.php` | `id, active: true\|false` |
| GET/POST/PUT/PATCH/DELETE | `/Admin/banners.php` | CRUD; fields `name, image_url, category?, price?, coins?, active?` — multipart `image` upload supported |
| PATCH/POST | `/Admin/banner_status.php` | `id, active: true\|false` |

**Levels**

| Method | Endpoint | Body / Params |
|--------|----------|---------------|
| GET/POST/PUT/PATCH/DELETE | `/Admin/levels.php` | CRUD; fields `level, title, xp_required?, reward?, color?` |

**Notifications**

| Method | Endpoint | Body / Params |
|--------|----------|---------------|
| GET/POST/PUT/PATCH/DELETE | `/Admin/notifications.php` | CRUD; fields `title, message, type?: info\|promo\|alert, audience?: all_users\|vip_users\|hosts\|active_users, status?: draft` |
| POST | `/Admin/notification_send.php?id=` | marks a draft as sent (push provider hooks in here) |

**Settings**

| Method | Endpoint | Body / Params |
|--------|----------|---------------|
| GET | `/Admin/settings.php` | all settings as `{ key: value }` |
| PUT/POST | `/Admin/settings.php` | any subset of setting keys (booleans stored as 1/0) |
| PATCH/PUT/POST | `/Admin/setting_update.php` | `key, value` (or `?key=` in query) |

**VIP plans & recharges**

| Method | Endpoint | Body / Params |
|--------|----------|---------------|
| GET/POST/PUT/PATCH/DELETE | `/Admin/vip_plans.php` | CRUD; fields `name, price, currency?, duration_days?, bonus_coins?, active?` |
| GET | `/Admin/recharges.php` | `?user_id=&type=coins|balance&page=&limit=` — recharge history |
| POST | `/Admin/recharges.php` | `user_id, coins, amount?, currency?, note?` (credits wallet + ledger) |
| POST | `/Admin/wallet_adjust.php` | `user_id, coins, action: credit|debit` |

**Streams**

| Method | Endpoint | Body / Params |
|--------|----------|---------------|
| GET | `/Admin/streams.php` | `?status=live|ended|all&search=&page=&limit=` |
| POST | `/Admin/stream_end.php` | `stream_id` (force-end / moderation) |

Uploads land in `/uploads/<gifts|banners>/` and are served relative to the project root (max 5 MB, images only).

## 5. Quick test (curl)

```bash
# Signup + login
curl -X POST http://localhost:8000/App/signup.php -d "name=John&email=john@test.com&password=secret1"
curl -X POST http://localhost:8000/App/login.php -d "email=john@test.com&password=secret1"

# Recharge wallet, go live
curl -X POST http://localhost:8000/App/wallet_recharge.php -H "Authorization: Bearer <TOKEN>" -d "coins=500"
curl -X POST http://localhost:8000/App/stream_create.php -H "Authorization: Bearer <TOKEN>" -d "title=My show"

# Live streams list
curl "http://localhost:8000/App/stream_list.php?status=live"

# Send a gift in a stream
curl -X POST http://localhost:8000/App/stream_send_gift.php -H "Authorization: Bearer <TOKEN>" -d "stream_id=1&gift_id=2&quantity=2"

# Admin
curl -X POST http://localhost:8000/Admin/login.php -d "email=admin@livestream.com&password=Admin@123"
curl "http://localhost:8000/Admin/streams.php?status=live" -H "Authorization: Bearer <ADMIN_TOKEN>"
```

## 6. Coming next

`realtime.php` (+ `realtime/`) — MongoDB + Socket handling for live streaming calculations (viewer ticks, chat, gift events). The stream's `channel` value returned by `stream_create.php` is what the realtime layer will use.
