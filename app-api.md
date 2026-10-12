# SmartLive — API Documentation

Backend API requirements for the SmartLive live-streaming app (Flutter + GetX).

- **Base URL**: `https://saptahikgyan.space/smart/php-api/App`
- **Note**: This document describes the intended full-featured API. The current PHP implementation in this project only covers a subset (see `appconnect.md` for the actual implemented endpoints).
- **Auth**: `Authorization: Bearer <token>` on every endpoint except auth endpoints
- **Content-Type**: `application/json` for all requests/responses

---

## 📊 API Count Summary

| # | Category                     | REST Endpoints | WebSocket Events | Total APIs |
|---|------------------------------|----------------|------------------|------------|
| 1 | Authentication               | 4              | 0                | 4          |
| 2 | User / Profile               | 5              | 0                | 5          |
| 3 | Live Streams (Video)         | 6              | 2                | 8          |
| 4 | Audio Rooms                  | 9              | 4                | 13         |
| 5 | Chat                         | 3              | 2                | 5          |
| 6 | Gifts & Wallet               | 6              | 1                | 7          |
| 7 | Games                        | 5              | 1                | 6          |
| 8 | Leaderboard                  | 3              | 1                | 4          |
| 9 | PK Battles                   | 4              | 2                | 6          |
| 10| Follow / Social              | 4              | 1                | 5          |
| 11| Search & Discover            | 3              | 0                | 3          |
| 12| Store (Items)                | 4              | 0                | 4          |
| 13| Store (Themes)               | 5              | 1                | 6          |
| 14| Broadcast / Notices          | 2              | 1                | 3          |
| 15| Moderation                   | 3              | 0                | 3          |
| 16| Messages (Inbox)             | 3              | 0                | 3          |
| 17| Referral / Rewards           | 2              | 0                | 2          |
| 18| Notifications                | 2              | 0                | 2          |
| **Total** |                         | **73**         | **15**           | **88**     |

### Summary by Type

| Type        | Count |
|-------------|-------|
| REST APIs   | 73    |
| WebSocket Events | 15    |
| **Grand Total** | **88**    |

---

## 1. Authentication (4 APIs)

### 1.1 Login with Phone — Send OTP
`POST /auth/login`

**Request:**
```json
{
  "phone": "+911234567890",
  "countryCode": "IN"
}
```

**Response:**
```json
{
  "otpSent": true,
  "expiresIn": 300
}
```

### 1.2 Verify OTP — Get Token
`POST /auth/verify-otp`

**Request:**
```json
{
  "phone": "+911234567890",
  "otp": "123456"
}
```

**Response:**
```json
{
  "token": "jwt-token-here",
  "refreshToken": "refresh-token-here",
  "user": {
    "id": "SLabc12345",
    "username": "SmartLive User",
    "phone": "+911234567890",
    "avatar": "SL",
    "level": 1,
    "beans": 2500,
    "gems": 0,
    "followers": 0,
    "following": 0
  }
}
```

### 1.3 Social Login
`POST /auth/social`

**Request:**
```json
{
  "provider": "google",
  "idToken": "google-id-token-here"
}
```

**Response:** Same as 1.2

### 1.4 Refresh Token
`POST /auth/refresh`

**Request:**
```json
{
  "refreshToken": "refresh-token-here"
}
```

**Response:**
```json
{
  "token": "new-jwt-token"
}
```

### 1.5 Logout
`POST /auth/logout`

**Response:**
```json
{
  "success": true
}
```

---

## 2. User / Profile (5 APIs)

### 2.1 Get My Profile
`GET /users/me`

**Response:**
```json
{
  "id": "SLabc12345",
  "username": "SmartLive User",
  "avatar": "SL",
  "avatarUrl": null,
  "bio": "Go Live, Connect, Share 🎙️",
  "gender": "Male",
  "birthday": "1995-01-15",
  "phone": "+911234567890",
  "country": {
    "code": "IN",
    "name": "India",
    "dialCode": "+91",
    "flag": "🇮🇳"
  },
  "level": 1,
  "beans": 2500,
  "gems": 0,
  "diamonds": 0,
  "followers": 0,
  "following": 0,
  "createdAt": "2026-09-10T12:00:00Z"
}
```

### 2.2 Update Profile
`PUT /users/me`

**Request:**
```json
{
  "username": "NewUsername",
  "bio": "Updated bio",
  "gender": "Female",
  "birthday": "1995-01-15",
  "phone": "+919876543210",
  "countryCode": "IN"
}
```

**Response:** Same as 2.1

### 2.3 Get User by ID
`GET /users/{userId}`

**Response:** Same structure as 2.1

### 2.4 Upload Avatar
`POST /users/me/avatar`

**Content-Type:** `multipart/form-data`

**Response:**
```json
{
  "avatarUrl": "https://cdn.smartlive.app/avatars/SLabc12345.jpg"
}
```

### 2.5 Change Password
`POST /users/me/password`

**Request:**
```json
{
  "currentPassword": "oldpass123",
  "newPassword": "newpass456"
}
```

**Response:**
```json
{
  "success": true
}
```

---

## 3. Live Streams — Video (8 APIs: 6 REST + 2 WS)

### 3.1 List Live Streams
`GET /streams`

**Query Params:**
- `type=video` (default: video)
- `page=1`
- `limit=20`
- `categoryId=general` (optional)
- `tab=freshers|popular|spotlight|pk` (optional filtering)

**Response:**
```json
{
  "items": [
    {
      "id": "1",
      "title": "Hey everyone! Welcome 🌸",
      "hostName": "Pari✨",
      "hostAvatar": "P",
      "hostLevel": "28",
      "viewerCount": 2200,
      "type": "video",
      "isLive": true,
      "thumbnailUrl": "https://cdn...",
      "accentColor": "#FF6B9D",
      "categoryId": "general",
      "badge": "superStar",
      "tag": "Super Star",
      "hasPK": false
    }
  ],
  "total": 150,
  "page": 1
}
```

### 3.2 Get Stream Details
`GET /streams/{streamId}`

**Response:** Single stream object (same as list item, with additional `host` user object)

### 3.3 Start Streaming (Go Live)
`POST /streams`

**Request:**
```json
{
  "title": "My Live Stream",
  "type": "video",
  "categoryId": "general",
  "isVideoStream": true
}
```

**Response:**
```json
{
  "streamId": "LSxyz789",
  "publishUrl": "rtmp://live.smartlive.app/live/LSxyz789",
  "playUrl": "https://cdn.smartlive.app/streams/LSxyz789.m3u8",
  "rtmpKey": "stream-key-here"
}
```

### 3.4 End Stream
`POST /streams/{streamId}/end`

**Response:**
```json
{
  "durationSec": 1200,
  "peakViewers": 300,
  "averageViewers": 150,
  "beansEarned": 450,
  "giftsReceived": 12,
  "likesReceived": 250
}
```

### 3.5 Like a Stream
`POST /streams/{streamId}/like`

**Response:**
```json
{
  "liked": true,
  "likeCount": 251
}
```

### 3.6 Heartbeat (Keep Alive)
`POST /streams/{streamId}/heartbeat`

**Request:**
```json
{
  "viewerPosition": "watching",
  "watchTimeSec": 120
}
```

**Response:**
```json
{
  "ok": true,
  "viewerCount": 2201
}
```

### 3.7 ⚡ WebSocket Events (Video Streams)

| Event | Direction | Payload |
|-------|-----------|---------|
| `viewer.count` | Server → Client | `{ "streamId": "...", "count": 2201 }` |
| `stream.like` | Server → Client | `{ "streamId": "...", "count": 251, "user": { "name": "User123" } }` |

---

## 4. Audio Rooms (13 APIs: 9 REST + 4 WS)

### ⚡ Socket Requirement
All audio room features REQUIRE WebSocket. REST endpoints only trigger changes; the UI updates from WS events for real-time sync.

| REST Action | WS Event |
|-------------|----------|
| Join/Leave room | `user.join` / `user.leave` |
| Take/Leave seat | `seat.update` |
| Mute/Unmute seat | `seat.mute` |
| Listener count changes | `viewer.count` |

### 4.1 List Audio Rooms
`GET /audio-rooms`

**Query Params:** `page=1`, `limit=20`

**Response:**
```json
{
  "items": [
    {
      "id": "a1",
      "title": "Midnight Radio 🎧",
      "hostName": "DJ Nightowl",
      "hostAvatar": "DN",
      "listenerCount": 890,
      "isLive": true,
      "speakerAvatars": ["SK", "AG", "RM"],
      "roomColor": "#6C63FF",
      "maxSeats": 12,
      "currentSeats": 4
    }
  ],
  "total": 25,
  "page": 1
}
```

### 4.2 Create Audio Room
`POST /audio-rooms`

**Request:**
```json
{
  "title": "My Voice Room",
  "roomColor": "#6C63FF",
  "maxSeats": 12,
  "isVideoStream": false
}
```

**Response:**
```json
{
  "room": {
    "id": "a1",
    "title": "My Voice Room",
    "hostName": "You",
    "hostAvatar": "ME",
    "listenerCount": 0,
    "isLive": true,
    "speakerAvatars": [],
    "roomColor": "#6C63FF",
    "maxSeats": 12
  },
  "rtcToken": "agora-rtc-token-here",
  "channelName": "room-a1",
  "isRoomCreator": true
}
```

### 4.3 Get Room Details
`GET /audio-rooms/{roomId}`

**Response:** Full room object with complete seat map

```json
{
  "id": "a1",
  "title": "Midnight Radio 🎧",
  "hostName": "DJ Nightowl",
  "hostAvatar": "DN",
  "listenerCount": 890,
  "isLive": true,
  "speakerAvatars": ["SK", "AG", "RM"],
  "roomColor": "#6C63FF",
  "maxSeats": 12,
  "seats": [
    { "seatNo": 1, "user": {"id": "host", "name": "DJ Nightowl", "avatar": "DN"}, "isOccupied": true, "isMuted": false },
    { "seatNo": 2, "user": {"id": "u1", "name": "Speaker 1", "avatar": "SK"}, "isOccupied": true, "isMuted": false },
    ...
  ],
  "isRoomCreator": false
}
```

### 4.4 Join Room
`POST /audio-rooms/{roomId}/join`

**Response:**
```json
{
  "rtcToken": "agora-rtc-token-here",
  "channelName": "room-a1",
  "seatMap": [...]
}
```

### 4.5 Leave Room
`POST /audio-rooms/{roomId}/leave`

**Response:**
```json
{
  "success": true
}
```

### 4.6 Take a Seat
`POST /audio-rooms/{roomId}/seats/{seatNo}/take`

**Response:**
```json
{
  "seatNo": 3,
  "user": { "id": "u-me", "name": "You", "avatar": "ME" },
  "isOccupied": true
}
```

### 4.7 Leave Seat
`POST /audio-rooms/{roomId}/seats/{seatNo}/leave`

**Response:**
```json
{
  "seatNo": 3,
  "isOccupied": false
}
```

### 4.8 Mute/Unmute Seat (Host Only for Others, Self for Own)
`POST /audio-rooms/{roomId}/seats/{seatNo}/mute`

**Request:**
```json
{
  "muted": true
}
```

**Response:**
```json
{
  "seatNo": 3,
  "muted": true,
  "byUserId": "host-user-id"
}
```

**Permission Rules:**
- Room creator can mute any seat
- Regular users can only mute their own seat

### 4.9 Kick User from Seat (Host Only)
`POST /audio-rooms/{roomId}/seats/{seatNo}/kick`

**Response:**
```json
{
  "seatNo": 3,
  "kicked": true
}
```

### 4.10 Toggle Room Mute (Host)
`POST /audio-rooms/{roomId}/mute-all`

**Request:**
```json
{
  "muted": true
}
```

**Response:**
```json
{
  "muted": true,
  "mutedCount": 5
}
```

### 4.11 Get Room History
`GET /audio-rooms/{roomId}/history`

**Response:**
```json
{
  "items": [
    {
      "id": "hist-1",
      "hostId": "host-user-id",
      "hostName": "DJ Nightowl",
      "title": "Midnight Radio 🎧",
      "startTime": "2026-09-10T18:00:00Z",
      "endTime": "2026-09-10T19:30:00Z",
      "durationSec": 5400,
      "peakListeners": 1200,
      "totalGifts": 45
    }
  ]
}
```

### 4.12 ⚡ WebSocket Events (Audio Rooms)

| Event | Direction | Payload |
|-------|-----------|---------|
| `user.join` | Server → Client | `{ "roomId": "a1", "user": {"id": "u-me", "name": "You", "avatar": "ME"} }` |
| `user.leave` | Server → Client | `{ "roomId": "a1", "user": {"id": "u-other", "name": "User"} }` |
| `seat.update` | Server → Client | `{ "roomId": "a1", "seatNo": 3, "user": {...}, "isOccupied": true }` |
| `seat.mute` | Server → Client | `{ "roomId": "a1", "seatNo": 3, "muted": true, "byUserId": "host-id" }` |
| `viewer.count` | Server → Client | `{ "roomId": "a1", "count": 891 }` |

---

## 5. Chat (5 APIs: 3 REST + 2 WS)

### 5.1 Get Chat History
`GET /rooms/{roomId}/chat`

**Query Params:** `before=messageId` (optional), `limit=50`

**Response:**
```json
{
  "items": [
    {
      "messageId": "m-123",
      "user": { "userId": "u-7", "name": "Panda1", "level": 8, "avatar": "P", "accentColor": "#FF6B6B" },
      "type": "chat",
      "text": "Hello everyone!",
      "giftEmoji": null,
      "timestamp": "2026-09-10T18:31:00Z"
    },
    {
      "messageId": "m-124",
      "user": { "userId": "system", "name": "System", "level": 0, "avatar": "S", "accentColor": "#FFFFFF" },
      "type": "system",
      "text": "Welcome to the room! 🎉",
      "giftEmoji": null,
      "timestamp": "2026-09-10T18:30:00Z"
    }
  ],
  "hasMore": true,
  "firstId": "m-123"
}
```

### 5.2 Send Message
`POST /rooms/{roomId}/chat`

**Request:**
```json
{
  "type": "chat",
  "text": "Hello everyone!"
}
```

**Response:**
```json
{
  "messageId": "m-125",
  "user": { "userId": "u-me", "name": "You", "level": 1, "avatar": "ME", "accentColor": "#6C63FF" },
  "type": "chat",
  "text": "Hello everyone!",
  "timestamp": "2026-09-10T18:32:00Z"
}
```

**Message Types:** `chat`, `join`, `follow`, `gift`, `system`, `like`

### 5.3 Delete Message (Moderator)
`DELETE /chat/{messageId}`

**Response:**
```json
{
  "success": true,
  "messageId": "m-125"
}
```

### 5.4 ⚡ WebSocket: Chat New Message
**Event:** `chat.new`

**Payload:**
```json
{
  "event": "chat.new",
  "roomId": "a1",
  "payload": {
    "messageId": "m-126",
    "user": { "userId": "u-7", "name": "Panda1", "level": 8, "avatar": "P", "accentColor": "#FF6B6B" },
    "type": "chat",
    "text": "Great stream!",
    "giftEmoji": null,
    "timestamp": "2026-09-10T18:33:00Z"
  }
}
```

### 5.5 ⚡ WebSocket: Chat Delete
**Event:** `chat.delete`

**Payload:**
```json
{
  "event": "chat.delete",
  "roomId": "a1",
  "payload": {
    "messageId": "m-126"
  }
}
```

---

## 6. Gifts & Wallet (7 APIs: 6 REST + 1 WS)

### 6.1 Gift Catalog
`GET /gifts`

**Query Params:** `category=gifts|stickers|effects|vip` (optional)

**Response:**
```json
[
  {
    "id": "rose",
    "name": "Rose",
    "emoji": "🌹",
    "price": 5,
    "animation": "flowers",
    "durationMs": 3000,
    "minLevel": 0,
    "isPopular": true,
    "isNew": false,
    "assets": {
      "icon": "https://cdn.smartlive.app/gifts/rose_icon.png",
      "animation": "https://cdn.smartlive.app/gifts/rose_anim.svga",
      "preview": "https://cdn.smartlive.app/gifts/rose_preview.webp"
    }
  },
  {
    "id": "crown",
    "name": "Crown",
    "emoji": "👑",
    "price": 500,
    "animation": "crown",
    "durationMs": 3000,
    "minLevel": 5,
    "isPopular": false,
    "isNew": false,
    "assets": {
      "icon": "https://cdn.smartlive.app/gifts/crown_icon.png",
      "animation": "https://cdn.smartlive.app/gifts/crown_anim.svga",
      "preview": "https://cdn.smartlive.app/gifts/crown_preview.webp"
    }
  }
]
```

**Animation Types (must match client's `GiftAnimationType`):**
- `car` — 🚗 drives across screen
- `flowers` — 🌹 flowers rise from bottom (Rose)
- `rocket` — 🚀 launches upward
- `fireworks` — 🎆 bursts (Fireworks/Fire)
- `diamond` — 💎 spinning diamond
- `heartShower` — ❤️ hearts rain (fallback)
- `crown` — 👑 crown drops with glow
- `butterfly` — 🦋 butterflies fly across
- `rain` — 🎁 money rain (Gift)
- `starBurst` — 🌟 stars explode (Trophy)

**Gift Asset Requirements:**
| Asset | Format | Size | Notes |
|-------|--------|------|-------|
| Catalog icon | PNG | 128×128, ≤50KB | Transparent background |
| SVGA animation | SVGA | ≤300KB | Industry standard (Bigo/UpLive use SVGA) |
| Preview thumbnail | WebP | 200×200 | Store page |

### 6.2 Send Gift
`POST /rooms/{roomId}/gifts`

**Request:**
```json
{
  "giftId": "crown",
  "toUserId": "host-user-id",
  "seatNo": 1,
  "combo": 1
}
```

**Response (Success):**
```json
{
  "txId": "gift-tx-9f2c",
  "balance": 2000,
  "gift": {
    "id": "crown",
    "name": "Crown",
    "emoji": "👑",
    "price": 500,
    "animation": "crown",
    "durationMs": 3000
  },
  "sender": { "userId": "u-me", "name": "You", "avatar": "ME", "level": 8 },
  "receiver": { "userId": "u-host", "name": "DJ Nightowl", "seatNo": 1 }
}
```

**Response (Insufficient Beans — 402):**
```json
{
  "error": "INSUFFICIENT_BEANS",
  "required": 500,
  "balance": 320
}
```

### 6.2.1 ⚡ WebSocket: Gift Sent
**Event:** `gift.sent`

**Payload:**
```json
{
  "event": "gift.sent",
  "roomId": "a1",
  "payload": {
    "txId": "gift-tx-9f2c",
    "sender": { "userId": "u-me", "name": "You", "avatar": "ME", "level": 8 },
    "receiver": { "userId": "u-host", "name": "DJ Nightowl", "avatar": "DN", "seatNo": 1 },
    "gift": { "id": "crown", "name": "Crown", "emoji": "👑", "price": 500, "animation": "crown" },
    "combo": 1,
    "timestamp": "2026-09-10T18:30:00Z"
  }
}
```

### 6.3 Wallet Balance
`GET /wallet`

**Response:**
```json
{
  "beans": 2500,
  "gems": 0,
  "diamonds": 0,
  "totalEarned": 1000,
  "totalSpent": 500
}
```

### 6.4 Transaction History
`GET /wallet/transactions`

**Query Params:** `page=1`, `limit=20`, `type=gift|purchase|earn|game`

**Response:**
```json
{
  "items": [
    {
      "id": "tx-1",
      "type": "gift",
      "amount": -500,
      "balance": 2000,
      "description": "Sent Crown to DJ Nightowl",
      "timestamp": "2026-09-10T18:30:00Z"
    }
  ],
  "total": 50,
  "page": 1
}
```

### 6.5 Top-Up Packages
`GET /wallet/packages`

**Response:**
```json
[
  { "id": "pkg_100", "beans": 100, "price": 100, "currency": "INR", "badge": "" },
  { "id": "pkg_220", "beans": 220, "price": 200, "currency": "INR", "badge": "+10%" },
  { "id": "pkg_600", "beans": 600, "price": 500, "currency": "INR", "badge": "+20%" },
  { "id": "pkg_1300", "beans": 1300, "price": 1000, "currency": "INR", "badge": "+30%" }
]
```

### 6.6 Purchase Beans (In-App Purchase)
`POST /wallet/purchase`

**Request:**
```json
{
  "packageId": "pkg_220",
  "receipt": "iap-receipt-from-play-store"
}
```

**Response:**
```json
{
  "transactionId": "tx-purchase-123",
  "beans": 220,
  "balance": 2720,
  "status": "success"
}
```

### 6.7 ⚡ WebSocket: Wallet Update
**Event:** `wallet.update`

**Payload:**
```json
{
  "event": "wallet.update",
  "payload": {
    "beans": 2720,
    "gems": 0
  }
}
```

---

## 7. Games (6 APIs: 5 REST + 1 WS)

> ⚠️ All game outcomes MUST be computed server-side to prevent cheating.

### 7.1 Spin Wheel
`POST /games/spin`

**Request:**
```json
{
  "roomId": "a1",
  "cost": 10
}
```

**Response:**
```json
{
  "result": {
    "points": 50,
    "segment": 2
  },
  "balance": 2490,
  "cost": 10
}
```

### 7.2 Dice Roll
`POST /games/dice`

**Request:**
```json
{
  "roomId": "a1",
  "cost": 5
}
```

**Response:**
```json
{
  "dice": 4,
  "reward": 40,
  "balance": 2530,
  "cost": 5
}
```

### 7.3 Lucky Draw
`POST /games/lucky-draw`

**Request:**
```json
{
  "roomId": "a1",
  "cost": 20
}
```

**Response:**
```json
{
  "prize": "100 Beans",
  "beans": 100,
  "balance": 2630,
  "cost": 20
}
```

### 7.4 Get Quiz Questions
`GET /games/quiz`

**Query Params:** `roomId=a1`

**Response:**
```json
[
  {
    "id": "q1",
    "question": "What does 'Go Live' mean?",
    "options": ["Start streaming", "End stream", "Share screen", "Record video"]
  },
  {
    "id": "q2",
    "question": "Which planet is closest to the Sun?",
    "options": ["Venus", "Mercury", "Mars", "Earth"]
  }
]
```

> Note: `correctIndex` is NOT included — server validates answers

### 7.5 Submit Quiz Answer
`POST /games/quiz/answer`

**Request:**
```json
{
  "roomId": "a1",
  "questionId": "q1",
  "choice": 0
}
```

**Response:**
```json
{
  "correct": true,
  "reward": 25,
  "balance": 2655,
  "questionId": "q1"
}
```

### 7.6 ⚡ WebSocket: Game Result
**Event:** `game.result`

**Payload:**
```json
{
  "event": "game.result",
  "roomId": "a1",
  "payload": {
    "gameType": "spin",
    "user": { "name": "You", "avatar": "ME" },
    "result": { "points": 50 },
    "timestamp": "2026-09-10T18:35:00Z"
  }
}
```

---

## 8. Leaderboard (4 APIs: 3 REST + 1 WS)

### 8.1 Room Gifters
`GET /leaderboard/rooms/{roomId}`

**Query Params:** `period=today|weekly|monthly`

**Response:**
```json
{
  "period": "today",
  "items": [
    { "rank": 1, "name": "NightOwl", "avatar": "NO", "totalBeans": 2450, "giftName": "Crown 👑", "accentColor": "#FFD700" },
    { "rank": 2, "name": "SuperFan", "avatar": "SF", "totalBeans": 1820, "giftName": "Diamond 💎", "accentColor": "#6C63FF" }
  ]
}
```

### 8.2 Global Gifters
`GET /leaderboard/global`

**Query Params:** `period=today|weekly|monthly`

**Response:** Same structure as 8.1

### 8.3 Rich List (Top Earners)
`GET /leaderboard/rich`

**Query Params:** `period=weekly|monthly`

**Response:**
```json
{
  "period": "weekly",
  "items": [
    { "rank": 1, "name": "TopEarner", "avatar": "TE", "totalEarned": 50000, "accentColor": "#FFD700" }
  ]
}
```

### 8.4 ⚡ WebSocket: Leaderboard Update
**Event:** `leaderboard.update`

**Payload:**
```json
{
  "event": "leaderboard.update",
  "roomId": "a1",
  "payload": {
    "items": [
      { "rank": 1, "name": "NightOwl", "avatar": "NO", "totalBeans": 2500, "giftName": "Crown 👑" }
    ]
  }
}
```

---

## 9. PK Battles (6 APIs: 4 REST + 2 WS)

### 9.1 Start PK Battle
`POST /pk/start`

**Request:**
```json
{
  "opponentUserId": "SL opponent-id",
  "durationMin": 2
}
```

**Response:**
```json
{
  "pkId": "pk-123",
  "stream1": { "id": "...", "title": "...", "hostName": "..." },
  "stream2": { "id": "...", "title": "...", "hostName": "..." },
  "durationSec": 120,
  "status": "active",
  "rtcTokens": {
    "stream1": "rtc-token-1",
    "stream2": "rtc-token-2"
  }
}
```

### 9.2 Get PK State
`GET /pk/{pkId}`

**Response:**
```json
{
  "pkId": "pk-123",
  "stream1": { "id": "s1", "hostName": "DancerPriya", "score": 5000, "combo": 5 },
  "stream2": { "id": "s2", "hostName": "Raj Music", "score": 4500, "combo": 3 },
  "durationSec": 90,
  "totalDurationSec": 120,
  "status": "active",
  "winner": null
}
```

### 9.3 PK Vote/Gift Points
`POST /pk/{pkId}/vote`

**Request:**
```json
{
  "side": 1,
  "giftId": "rose",
  "points": 10
}
```

**Response:**
```json
{
  "score1": 5010,
  "score2": 4500,
  "combo1": 6
}
```

### 9.4 Get PK Result
`GET /pk/{pkId}/result`

**Response:**
```json
{
  "pkId": "pk-123",
  "winner": {
    "userId": "s1",
    "name": "DancerPriya",
    "score": 6200
  },
  "loser": {
    "userId": "s2",
    "name": "Raj Music",
    "score": 5800
  },
  "rewards": {
    "winnerBeans": 1000,
    "loserBeans": 200
  }
}
```

### 9.5 ⚡ WebSocket: PK Score Update
**Event:** `pk.score`

**Payload:**
```json
{
  "event": "pk.score",
  "pkId": "pk-123",
  "payload": {
    "score1": 5100,
    "score2": 4550,
    "combo1": 6,
    "combo2": 3,
    "timeRemaining": 85
  }
}
```

### 9.6 ⚡ WebSocket: PK End
**Event:** `pk.end`

**Payload:**
```json
{
  "event": "pk.end",
  "pkId": "pk-123",
  "payload": {
    "winner": { "userId": "s1", "name": "DancerPriya", "score": 6200 },
    "loser": { "userId": "s2", "name": "Raj Music", "score": 5800 }
  }
}
```

---

## 10. Follow / Social (5 APIs: 4 REST + 1 WS)

### 10.1 Follow a User
`POST /users/{userId}/follow`

**Response:**
```json
{
  "following": true,
  "followersCount": 1
}
```

### 10.2 Unfollow a User
`DELETE /users/{userId}/follow`

**Response:**
```json
{
  "following": false,
  "followersCount": 0
}
```

### 10.3 Get Followers List
`GET /users/{userId}/followers`

**Query Params:** `page=1`, `limit=20`

**Response:**
```json
{
  "items": [
    { "userId": "SL001", "name": "Pari✨", "avatar": "P", "level": 28, "accentColor": "#FF6B9D", "followedAt": "2026-09-10T12:00:00Z" }
  ],
  "total": 150,
  "page": 1
}
```

### 10.4 Get Following List
`GET /users/{userId}/following`

**Query Params:** `page=1`, `limit=20`

**Response:** Same structure as 10.3

### 10.5 ⚡ WebSocket: User Follow
**Event:** `user.follow`

**Payload:**
```json
{
  "event": "user.follow",
  "roomId": "a1",
  "payload": {
    "follower": { "name": "You", "avatar": "ME" },
    "followed": { "name": "DJ Nightowl", "avatar": "DN" }
  }
}
```

---

## 11. Search & Discover (3 APIs)

### 11.1 Search Everything
`GET /search`

**Query Params:**
- `q=panda1` (search query)
- `type=all|users|streams|rooms` (default: all)
- `limit=20`

**Response:**
```json
{
  "users": [
    {
      "id": "SL001",
      "name": "Pari✨",
      "avatar": "P",
      "accentColor": "#FF6B9D",
      "level": "28",
      "followers": 125000,
      "isLive": true,
      "liveTitle": "Hey everyone! Welcome 🌸",
      "badge": "superStar"
    }
  ],
  "streams": [...],
  "rooms": [...]
}
```

### 11.2 Trending Tags
`GET /discover/trending`

**Response:**
```json
{
  "tags": [
    { "tag": "dance", "count": 1500, "emoji": "💃" },
    { "tag": "gaming", "count": 1200, "emoji": "🎮" },
    { "tag": "music", "count": 980, "emoji": "🎵" }
  ]
}
```

### 11.3 Categories
`GET /discover/categories`

**Response:**
```json
{
  "categories": [
    { "id": "general", "name": "General", "emoji": "🎬", "streamCount": 500 },
    { "id": "music", "name": "Music", "emoji": "🎵", "streamCount": 350 },
    { "id": "dance", "name": "Dance", "emoji": "💃", "streamCount": 280 }
  ]
}
```

---

## 12. Store — Items (4 APIs)

### 12.1 Store Items
`GET /store/items`

**Query Params:** `category=gifts|stickers|effects|vip`, `page=1`, `limit=20`

**Response:**
```json
{
  "items": [
    {
      "id": "g1",
      "name": "Rose",
      "emoji": "🌹",
      "beanPrice": 5,
      "category": "gifts",
      "badge": null,
      "isNew": false,
      "isPopular": true
    },
    {
      "id": "v1",
      "name": "VIP Badge",
      "emoji": "🛡️",
      "beanPrice": 500,
      "category": "vip",
      "badge": "7 Days",
      "isNew": false,
      "isPopular": false
    }
  ],
  "total": 50,
  "page": 1
}
```

### 12.2 Buy Store Item
`POST /store/items/purchase`

**Request:**
```json
{
  "itemId": "v1",
  "paymentMethod": "beans"
}
```

**Response:**
```json
{
  "success": true,
  "item": { "id": "v1", "name": "VIP Badge", "emoji": "🛡️" },
  "balance": 2000
}
```

### 12.3 My Inventory
`GET /store/inventory`

**Response:**
```json
{
  "items": [
    { "id": "v1", "name": "VIP Badge", "emoji": "🛡️", "category": "vip", "purchasedAt": "2026-09-10T12:00:00Z", "expiresAt": "2026-09-17T12:00:00Z" }
  ]
}
```

### 12.4 Equip/Use Item
`POST /store/inventory/{itemId}/equip`

**Request:**
```json
{
  "equipped": true
}
```

**Response:**
```json
{
  "id": "v1",
  "name": "VIP Badge",
  "emoji": "🛡️",
  "equipped": true,
  "expiresAt": "2026-09-17T12:00:00Z"
}
```

---

## 13. Store — Room Themes (6 APIs: 5 REST + 1 WS)

### 13.1 Theme Catalog
`GET /store/themes`

**Query Params:** `price=free|premium`, `page=1`, `limit=20`

**Response:**
```json
{
  "items": [
    {
      "id": "theme_neon_city",
      "name": "Neon City",
      "price": 5000,
      "currency": "beans",
      "isFree": false,
      "preview": "https://cdn.smartlive.app/themes/neon_city_preview.webp",
      "assets": {
        "background": "https://cdn.smartlive.app/themes/neon_city_bg.webp",
        "seatIcon": "https://cdn.smartlive.app/themes/neon_city_seat.webp",
        "primaryColor": "#B388FF",
        "accentColor": "#00E5FF",
        "neonStyle": "arch"
      }
    },
    {
      "id": "theme_classic",
      "name": "Classic",
      "price": 0,
      "currency": "beans",
      "isFree": true,
      "preview": "https://cdn.smartlive.app/themes/classic_preview.webp",
      "assets": {
        "background": "https://cdn.smartlive.app/themes/classic_bg.webp",
        "seatIcon": "https://cdn.smartlive.app/themes/classic_seat.webp",
        "primaryColor": "#6C63FF",
        "accentColor": "#00BCD4",
        "neonStyle": "plain"
      }
    }
  ]
}
```

**Theme Asset Requirements:**
| Asset | Format | Size |
|-------|--------|------|
| Background | WebP/PNG | 1080×1920 (9:16), ≤400KB |
| Seat Icon | WebP/PNG | 256×256, transparent |
| Colors | Hex strings | — |
| neonStyle | enum | arch \| grid \| aurora \| plain |

### 13.2 Buy Theme
`POST /store/themes/{themeId}/purchase`

**Response:**
```json
{
  "success": true,
  "theme": { "id": "theme_neon_city", "name": "Neon City" },
  "balance": 0
}
```

### 13.3 My Themes
`GET /users/me/themes`

**Response:**
```json
{
  "owned": [
    { "id": "theme_neon_city", "name": "Neon City", "purchasedAt": "2026-09-10T12:00:00Z" }
  ],
  "equipped": {
    "roomId": "a1",
    "themeId": "theme_classic"
  }
}
```

### 13.4 Apply Theme to My Room
`POST /audio-rooms/{roomId}/theme`

**Request:**
```json
{
  "themeId": "theme_neon_city"
}
```

**Response:**
```json
{
  "roomId": "a1",
  "themeId": "theme_neon_city",
  "equippedAt": "2026-09-10T18:35:00Z"
}
```

### 13.5 Admin: Create Theme
`POST /admin/themes`

**Content-Type:** `multipart/form-data`

**Fields:**
- `background`: WebP/PNG, 1080×1920
- `seatIcon`: WebP/PNG, 256×256
- `name`: string
- `primaryColor`: hex
- `accentColor`: hex
- `neonStyle`: enum

**Response:**
```json
{
  "id": "theme_new",
  "name": "New Theme",
  "status": "active"
}
```

### 13.6 ⚡ WebSocket: Room Theme Update
**Event:** `room.theme`

**Payload:**
```json
{
  "event": "room.theme",
  "roomId": "a1",
  "payload": {
    "themeId": "theme_neon_city",
    "assets": {
      "background": "https://cdn...",
      "seatIcon": "https://cdn...",
      "primaryColor": "#B388FF",
      "accentColor": "#00E5FF",
      "neonStyle": "arch"
    }
  }
}
```

---

## 14. Broadcast / Notices (3 APIs: 2 REST + 1 WS)

### 14.1 Room Broadcasts (Vertical Ticker)
`GET /rooms/{roomId}/broadcasts`

**Response:**
```json
{
  "items": [
    { "id": "b-1", "text": "🎰 Parvez won 100,000 Beans in Fruit Challenges. Join now & win!", "icon": "🎰", "priority": "high", "expiresAt": "2026-09-10T19:00:00Z" },
    { "id": "b-2", "text": "💎 VIP room opening soon — lock your seat now!", "icon": "💎", "priority": "normal", "expiresAt": "2026-09-10T20:00:00Z" }
  ]
}
```

### 14.2 Global Banners
`GET /banners`

**Query Params:** `placement=audio_room|live_stream|home`

**Response:**
```json
{
  "banners": [
    {
      "id": "banner-1",
      "placement": "audio_room",
      "title": "Double Beans Event!",
      "subtitle": "Send gifts today and get 2x beans back",
      "imageUrl": "https://cdn.smartlive.app/banners/double_beans.webp",
      "actionUrl": "/store",
      "isActive": true
    }
  ]
}
```

### 14.3 ⚡ WebSocket: Broadcast New
**Event:** `broadcast.new`

**Payload:**
```json
{
  "event": "broadcast.new",
  "roomId": "a1",
  "payload": {
    "id": "b-9",
    "text": "🎁 Send any gift today — double beans back!",
    "icon": "🎰",
    "priority": "high"
  }
}
```

---

## 15. Moderation (3 APIs)

### 15.1 Report User/Room/Message
`POST /reports`

**Request:**
```json
{
  "targetType": "user",
  "targetId": "SL-reported-user",
  "reason": "spam",
  "description": "Sending spam messages"
}
```

**Response:**
```json
{
  "reportId": "rep-123",
  "status": "submitted",
  "createdAt": "2026-09-10T18:40:00Z"
}
```

### 15.2 Block User
`POST /users/{userId}/block`

**Response:**
```json
{
  "blocked": true,
  "blockedAt": "2026-09-10T18:40:00Z"
}
```

### 15.3 Unblock User
`DELETE /users/{userId}/block`

**Response:**
```json
{
  "blocked": false
}
```

### 15.4 Get Blocked Users List
`GET /users/me/blocks`

**Response:**
```json
{
  "items": [
    { "userId": "SL-blocked", "name": "Spammer", "blockedAt": "2026-09-10T18:40:00Z" }
  ]
}
```

### 15.5 Safety Notice Content
`GET /config/safety-notice`

**Response:**
```json
{
  "notice": "By continuing to use this app, you agree to our Community Guidelines. Be respectful to others.",
  "version": "1.0"
}
```

---

## 16. Messages — Inbox (3 APIs)

### 16.1 Get Conversations
`GET /messages/conversations`

**Response:**
```json
{
  "conversations": [
    {
      "userId": "SL001",
      "name": "Sarah Kings",
      "avatar": "SK",
      "lastMessage": "Thanks for the follow! 💕",
      "time": "2m ago",
      "unread": 3,
      "isOnline": true,
      "accentColor": "#FF4081"
    }
  ]
}
```

### 16.2 Get Messages
`GET /messages/{userId}`

**Query Params:** `page=1`, `limit=50`

**Response:**
```json
{
  "messages": [
    {
      "id": "msg-1",
      "senderId": "SL001",
      "receiverId": "SL-me",
      "text": "Hey! Thanks for the follow!",
      "timestamp": "2026-09-10T18:00:00Z",
      "read": true
    }
  ],
  "hasMore": false
}
```

### 16.3 Send Message
`POST /messages`

**Request:**
```json
{
  "toUserId": "SL001",
  "text": "Thanks! Love your streams! 🔥"
}
```

**Response:**
```json
{
  "id": "msg-2",
  "senderId": "SL-me",
  "receiverId": "SL001",
  "text": "Thanks! Love your streams! 🔥",
  "timestamp": "2026-09-10T18:01:00Z",
  "read": false
}
```

---

## 17. Referral / Rewards (2 APIs)

### 17.1 Get Referral Info
`GET /users/me/referral`

**Response:**
```json
{
  "referralCode": "SLABC123",
  "invitedCount": 3,
  "referralEarnings": 300,
  "inviteLink": "https://smartlive.app/invite/SLABC123"
}
```

### 17.2 Get Referral Rewards Tier
`GET /referral/rewards`

**Response:**
```json
{
  "tiers": [
    { "minInvites": 1, "bonusBeans": 100, "badge": "Bronze Inviter" },
    { "minInvites": 5, "bonusBeans": 500, "badge": "Silver Inviter" },
    { "minInvites": 10, "bonusBeans": 2000, "badge": "Gold Inviter" }
  ]
}
```

---

## 18. Notifications (2 APIs)

### 18.1 Get Notifications
`GET /notifications`

**Query Params:** `page=1`, `limit=20`, `read=false` (optional)

**Response:**
```json
{
  "notifications": [
    {
      "id": "notif-1",
      "title": "New Follower",
      "body": "HappyViewer started following you",
      "type": "follow",
      "time": "1m ago",
      "read": false,
      "data": { "userId": "SL-follower" }
    },
    {
      "id": "notif-2",
      "title": "Gift Received",
      "body": "You received a 💎 Diamond from NightOwl",
      "type": "gift",
      "time": "10m ago",
      "read": false,
      "data": { "giftId": "diamond", "fromUserId": "SL-nightowl" }
    }
  ],
  "unreadCount": 2
}
```

### 18.2 Mark Notifications Read
`POST /notifications/read`

**Request:**
```json
{
  "notificationIds": ["notif-1", "notif-2"]
}
```

**Response:**
```json
{
  "success": true,
  "unreadCount": 0
}
```

---

## 🎯 Realtime WebSocket Connection

### Connection Setup

```
WS  wss://api.smartlive.app/v1/ws?token=<jwt>
```

### Subscribe to Room Channels

```json
// Subscribe
{ "action": "subscribe", "channel": "room:a1" }

// Unsubscribe
{ "action": "unsubscribe", "channel": "room:a1" }
```

### Message Envelope (Uniform Format)

```json
{
  "event": "seat.mute",
  "roomId": "a1",
  "payload": { "seatNo": 3, "muted": true, "byUserId": "u-host" }
}
```

---

## 📋 Complete WebSocket Event List (15 Events)

| # | Event | Category | Description |
|---|-------|----------|-------------|
| 1 | `chat.new` | Chat | New chat message in room |
| 2 | `chat.delete` | Chat | Message deleted by moderator |
| 3 | `user.join` | Audio Room | User joined the room |
| 4 | `user.leave` | Audio Room | User left the room |
| 5 | `user.follow` | Social | User followed someone (shown in chat) |
| 6 | `gift.sent` | Gifts | Gift sent — triggers animation + chat |
| 7 | `seat.update` | Audio Room | Seat taken/occupied status changed |
| 8 | `seat.mute` | Audio Room | Seat mute state changed |
| 9 | `viewer.count` | Streams/Rooms | Live viewer/listener count update |
| 10 | `stream.like` | Streams | Live stream like count update |
| 11 | `leaderboard.update` | Leaderboard | Top gifters list re-sorted |
| 12 | `broadcast.new` | Notices | New promo message for ticker |
| 13 | `game.result` | Games | Spin/dice/lucky/quiz result announcement |
| 14 | `pk.score` | PK Battle | Real-time PK score update |
| 15 | `pk.end` | PK Battle | PK battle ended — winner announced |
| 16 | `room.theme` | Themes | Room theme changed by host |

---

## 🔴 RTC (Real-Time Communication) — Separate from WebSocket

| Feature | Transport | Token Source |
|---------|-----------|--------------|
| Audio room voice (12 seats) | Agora/Zego audio channel | `POST /audio-rooms` (4.2) & `POST /audio-rooms/{id}/join` (4.4) |
| Video live stream | WebRTC/RTMP publish + HLS/RTC play | `POST /streams` (3.3) |
| PK battle | Two merged RTC channels | `POST /pk/start` (9.1) |

---

## 📝 Implementation Notes

### Pagination
All list endpoints use `page` + `limit` parameters. Response format:
```json
{
  "items": [...],
  "total": 150,
  "page": 1,
  "hasMore": true
}
```

### Seat Authority Rules
- `POST /seats/{n}/mute` — only room creator can mute OTHER seats
- Regular users can only mute their own seat

### Money Safety
- Bean deductions (gifts, games, store purchases) MUST be atomic server-side transactions
- Use database transactions to prevent race conditions

### Level System
- User level chips (`Lv.8`) come from `GET /users/{id}`
- Cache user level client-side to reduce API calls

### Mock Data Reference
Current mock data is in `lib/app/data/models/stream_model.dart`:
- `getMockLiveStreams()` — defines LiveStreamModel shape
- `getMockAudioRooms()` — defines AudioRoomModel shape

These should match API response shapes for drop-in replacement.

### Gift Asset Pipeline
1. Admin uploads GIFTS through admin panel
2. Each gift needs: icon (PNG 128×128), SVGA animation (≤300KB), preview (WebP 200×200)
3. CDN serves with long cache headers
4. Client caches animations on first play

### Recommended Backend Services
Based on the real-time requirements, consider:
- **WebSocket Server**: Node.js (Socket.io) or Go (gorilla/websocket)
- **RTC**: Agora.io or ZegoCloud for audio/video
- **Database**: PostgreSQL for relational data, Redis for real-time state
- **File Storage**: AWS S3 or Cloudflare R2 for gift assets, avatars
- **CDN**: Cloudflare or AWS CloudFront for asset delivery

---

*Document Version: 1.0*
*Last Updated: 2026-10-12*
