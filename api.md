# Admin Panel API Requirements

This admin dashboard includes modules for users, hosts, agencies, resellers, sub-admins, roles, gifts, banners, levels, notifications, settings, and VIP/recharge actions. The backend should provide CRUD APIs for each of these modules.

## 1. Base API Format

Use a consistent structure for all API responses:

```json
{
  "success": true,
  "message": "Operation completed successfully",
  "data": {}
}
```

Error format:

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": []
}
```

Suggested base path:

```text
/api/admin
```

Authentication:

```http
Authorization: Bearer <token>
```

---

## 2. Required Add / Edit / Delete APIs

### 2.1 Users

Required endpoints:

```http
POST /api/admin/users
PUT /api/admin/users/:id
PATCH /api/admin/users/:id/status
DELETE /api/admin/users/:id
POST /api/admin/users/:id/recharge
PATCH /api/admin/users/:id/vip
```

Required fields for create/update:

```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "phone": "+123456789",
  "country": "USA",
  "password": "secret123",
  "status": "active",
  "vip": true,
  "level": 25,
  "coins": 15000,
  "diamonds": 5000
}
```

Notes:
- `POST /api/admin/users` creates a user.
- `PUT /api/admin/users/:id` updates user info.
- `PATCH /api/admin/users/:id/status` toggles active/banned state.
- `DELETE /api/admin/users/:id` permanently removes the user.
- `POST /api/admin/users/:id/recharge` adds coins/diamonds or balance.

---

### 2.2 Hosts

Required endpoints:

```http
POST /api/admin/hosts
PUT /api/admin/hosts/:id
PATCH /api/admin/hosts/:id/status
DELETE /api/admin/hosts/:id
POST /api/admin/hosts/:id/salary
```

Required fields:

```json
{
  "name": "Elena Rodriguez",
  "agencyId": 1,
  "email": "elena@example.com",
  "phone": "+123456789",
  "status": "online",
  "followers": 45000,
  "level": 85,
  "earnings": 8900
}
```

Notes:
- Host status can be `online`, `offline`, or `banned`.
- Salary API is needed for payout management.

---

### 2.3 Agencies

Required endpoints:

```http
POST /api/admin/agencies
PUT /api/admin/agencies/:id
PATCH /api/admin/agencies/:id/status
DELETE /api/admin/agencies/:id
```

Required fields:

```json
{
  "name": "Star Agency",
  "ownerName": "Alex Kumar",
  "email": "agency@example.com",
  "status": "active",
  "rating": 4.8,
  "revenue": 125000,
  "hostsCount": 45
}
```

---

### 2.4 Resellers

Required endpoints:

```http
POST /api/admin/resellers
PUT /api/admin/resellers/:id
PATCH /api/admin/resellers/:id/status
DELETE /api/admin/resellers/:id
```

Required fields:

```json
{
  "name": "Premium Reseller",
  "ownerName": "John Smith",
  "email": "reseller@example.com",
  "usersServed": 234,
  "revenue": 45000,
  "packages": 12,
  "status": "active"
}
```

---

### 2.5 Sub Admins

Required endpoints:

```http
POST /api/admin/sub-admins
PUT /api/admin/sub-admins/:id
PATCH /api/admin/sub-admins/:id/status
DELETE /api/admin/sub-admins/:id
PUT /api/admin/sub-admins/:id/permissions
```

Required fields:

```json
{
  "name": "Rachel Green",
  "email": "rachel@smartlive.com",
  "password": "secret123",
  "role": "User Manager",
  "status": "active",
  "permissions": [
    "users.view",
    "users.edit",
    "hosts.view"
  ]
}
```

Notes:
- Permissions are custom module-based access rules.
- `PUT /sub-admins/:id/permissions` should update access list.

---

### 2.6 Roles / Permissions

Required endpoints:

```http
POST /api/admin/roles
PUT /api/admin/roles/:id
DELETE /api/admin/roles/:id
PUT /api/admin/roles/:id/permissions
```

Required fields:

```json
{
  "name": "Marketing Admin",
  "description": "Handles campaigns and promotions",
  "permissions": {
    "users": { "view": true, "edit": false, "delete": false },
    "hosts": { "view": true, "edit": true },
    "settings": { "view": true }
  }
}
```

---

### 2.7 Gifts

Required endpoints:

```http
POST /api/admin/gifts
PUT /api/admin/gifts/:id
PATCH /api/admin/gifts/:id/status
DELETE /api/admin/gifts/:id
```

Required fields:

```json
{
  "name": "Rose",
  "category": "Flowers",
  "price": 5,
  "coins": 50,
  "emoji": "🌹",
  "imageUrl": "https://cdn.example.com/gifts/rose.png",
  "active": true
}
```

Notes:
- If an image is uploaded, use `multipart/form-data` with file upload support.
- `PATCH /gifts/:id/status` toggles active/inactive.

---

### 2.8 Banners

Required endpoints:

```http
POST /api/admin/banners
PUT /api/admin/banners/:id
PATCH /api/admin/banners/:id/status
DELETE /api/admin/banners/:id
```

Required fields:

```json
{
  "name": "Summer Splash",
  "category": "Seasonal",
  "price": 500,
  "coins": 5000,
  "imageUrl": "https://cdn.example.com/banners/summer.png",
  "active": true
}
```

Notes:
- Banner upload should use multipart upload for large image files.

---

### 2.9 User Levels

Required endpoints:

```http
POST /api/admin/levels
PUT /api/admin/levels/:id
DELETE /api/admin/levels/:id
```

Required fields:

```json
{
  "level": 90,
  "title": "Supreme",
  "xpRequired": 500000,
  "reward": 25000,
  "color": "#f59e0b"
}
```

Notes:
- This is used for XP progression and reward management.

---

### 2.10 Notifications

Required endpoints:

```http
POST /api/admin/notifications
PUT /api/admin/notifications/:id
DELETE /api/admin/notifications/:id
POST /api/admin/notifications/:id/send
```

Required fields:

```json
{
  "title": "New Gift Collection",
  "message": "Holiday special gifts are now available!",
  "type": "info",
  "audience": "all_users",
  "status": "draft"
}
```

Notes:
- `POST /notifications/:id/send` marks the notification as sent and delivers it to the target audience.

---

### 2.11 App Settings

Required endpoints:

```http
GET /api/admin/settings
PUT /api/admin/settings
PATCH /api/admin/settings/:key
```

Example payload:

```json
{
  "audioEnabled": true,
  "videoEnabled": true,
  "maintenanceMode": false,
  "registrationEnabled": true,
  "giftsEnabled": true,
  "vipEnabled": true,
  "agencyEnabled": true,
  "resellerEnabled": true,
  "pushNotifications": true,
  "emailNotifications": true
}
```

Notes:
- This module is global application configuration and should be saved in a system settings table.

---

### 2.12 VIP / Recharge / Payment Related APIs

Required endpoints:

```http
POST /api/admin/vip-plans
PUT /api/admin/vip-plans/:id
DELETE /api/admin/vip-plans/:id
POST /api/admin/recharges
```

Example recharge payload:

```json
{
  "userId": 12,
  "amount": 50,
  "currency": "USD",
  "type": "coins",
  "note": "Bulk top-up"
}
```

---

## 3. Recommended Standard CRUD Pattern

For consistency, use this pattern everywhere:

```http
POST   /api/admin/{module}         -> create
GET    /api/admin/{module}         -> list
GET    /api/admin/{module}/:id     -> detail
PUT    /api/admin/{module}/:id     -> update full record
PATCH  /api/admin/{module}/:id     -> partial update or status change
DELETE /api/admin/{module}/:id     -> delete
```

---

## 4. Minimum APIs to Build First

If you want the quickest working backend for this dashboard, prioritize these modules first:

1. Users
2. Hosts
3. Agencies
4. Resellers
5. Sub-admins
6. Gifts
7. Banners
8. Levels
9. Notifications
10. Settings

This covers all create, edit, and delete actions visible in the current admin panel.
