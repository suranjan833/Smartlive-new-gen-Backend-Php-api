# SmartLive — Master API Specification & Realtime Audio Live Architecture (`needapi.md`)

> **Architecture Overview:**
> - **PHP + MySQL (REST API):** Persistent transactions, Auth, Wallet/Beans deduction, Room Lifecycle, Gifts, DB State, Admin Panel.
> - **Node.js + Socket.io (Realtime Gateway):** Room state broadcast, Chair/Seat sync, Realtime Chat, Floating Reactions, Live Gift Animations, Speaking indicators.
> - **WebRTC / RTC Voice Engine (Agora / LiveKit):** Low-latency voice channels for multi-mic party rooms (12 seats).

---

## 📑 Table of Contents
1. [Architecture & Protocol Segregation (PHP vs Node.js vs WebRTC)](#1-architecture--protocol-segregation)
2. [Audio Live Streaming Roadmap (Audio First Implementation)](#2-audio-live-streaming-roadmap)
3. [Complete API Endpoints List (PHP + MySQL Backend)](#3-complete-api-endpoints-list-php--mysql)
4. [Realtime WebSocket Events (Node.js Gateway)](#4-realtime-websocket-events-nodejs)
5. [Audio Room Seat Matrix & Voice RTC Signaling](#5-audio-room-seat-matrix--voice-rtc-signaling)
6. [App Status Audit & Implementation Checklist](#6-app-status-audit--implementation-checklist)

---

## 1. Architecture & Protocol Segregation

```
+-----------------------------------------------------------------------------------+
|                                  FLUTTER CLIENT                                   |
+-----------------------------------------------------------------------------------+
       |                                  |                                  |
       | (HTTPS / REST)                   | (WebSocket / WSS)                | (WebRTC / UDP)
       v                                  v                                  v
+-----------------------+      +-----------------------+      +-----------------------+
|      PHP + MySQL      |      |   Node.js + Socket    |      | Agora / LiveKit RTC   |
|   (Transactional)     |      |      (Realtime)       |      |     (Voice Engine)    |
+-----------------------+      +-----------------------+      +-----------------------+
| • Login / Register    |      | • Seat Sync (Take/Vac)|      | • 12 Multi-Mic Audio  |
| • Wallet / Recharge   |      | • Instant Room Chat   |      | • Speaker Voice Feed  |
| • Send Gift (Ledger)  |      | • Gift Broadcast Anim |      | • Mute / Unmute Mic   |
| • Create/End Room DB  |      | • Reaction Emojis     |      | • Volume Pitch Detect |
| • Leaderboard & VIP   |      | • Viewer Count Pulse  |      | • Low Latency (<200ms)|
+-----------------------+      +-----------------------+      +-----------------------+
       ^                                  ^
       |------------- Redis / PubSub -----|
```

### Why this division?
- **PHP + MySQL:** ACID compliance for wallets, user coins, gift purchases, fraud prevention, and admin panel records.
- **Node.js:** Non-blocking event loop handling thousands of concurrent listeners without DB query latency on every comment or heart.
- **WebRTC (Agora / LiveKit):** Dedicated ultra-low latency voice mesh for the 12 speaker seats.

---

## 2. Audio Live Streaming Roadmap (Audio First Implementation)

To launch **Audio Live (Party Rooms)** first, the development is divided into 4 sequential stages:

```
[Stage 1: PHP Room Lifecycle] -> [Stage 2: Voice RTC Token] -> [Stage 3: Node.js Seat & Chat] -> [Stage 4: Gift Transaction]
```

### Stage 1: Room Lifecycle (PHP)
- Host creates an Audio Party Room (`POST /room_create.php`) with category and title.
- Listeners browse active rooms (`GET /room_list.php`).
- Listeners join room (`POST /room_join.php`) and leave (`POST /room_leave.php`).
- Host terminates room (`POST /room_end.php`).

### Stage 2: RTC Voice Channel (Agora / LiveKit)
- Client requests RTC token: `POST /room_token.php`.
- Host joins channel as **Publisher**.
- Normal listeners join channel as **Audience (Subscriber)**.
- When a listener gets a seat, their role upgrades to **Publisher**.

### Stage 3: Realtime Seat Management & Chat (Node.js)
- 12 Seats Grid layout in Flutter (`audio_room_view.dart`).
- Listener taps an empty seat -> emits `seat:apply` or `seat:take`.
- Host can mute/unmute any chair -> emits `seat:mute_toggle`.
- Instant chat messages broadcast -> `chat:message`.

### Stage 4: Gift Transactions (PHP + Node.js Broadcast)
- User taps Gift -> App calls `POST /room_send_gift.php` (PHP checks bean balance, deducts sender, credits host/speaker).
- On success, PHP informs Node.js (via internal API or Redis) or client emits `gift:sent` with verified transaction payload.
- All 1,000+ viewers in the room receive `gift:broadcast` and render the SVG/Lottie gift animation simultaneously.

---

## 3. Complete API Endpoints List (PHP + MySQL)

### A. Authentication & User Profile
1. `POST /signup.php`
   - **Body:** `{ "username": "string", "phone": "string", "password": "string", "country_code": "+91" }`
   - **Returns:** `{ "success": true, "data": { "token": "...", "user_id": "SL1234" } }`
2. `POST /login.php`
   - **Body:** `{ "user_id": "string", "password": "string" }` (Supports username, phone, or email)
   - **Returns:** User info, token, balance.
3. `POST /logout.php`
   - **Headers:** `Authorization: Bearer <token>`
   - **Action:** Invalidate server session/token.
4. `GET /profile.php`
   - **Returns:** User details, level, VIP status, beans, followers, following.
5. `POST /update_profile.php`
   - **Body:** `{ "name": "...", "bio": "...", "avatar": "...", "gender": "..." }`
6. `POST /change_password.php`
   - **Body:** `{ "old_password": "...", "new_password": "..." }`

---

### B. Audio Party Room Lifecycle (CORE PRIORITY)
7. `GET /room_list.php`
   - **Query:** `?category=All&page=1&limit=20`
   - **Returns:** List of active rooms (id, title, host_name, host_avatar, listener_count, room_color, category).
8. `POST /room_create.php`
   - **Body:** `{ "title": "Late Night Jam", "category": "Music", "room_type": "12_seat", "cover": "url" }`
   - **Returns:** `{ "room_id": 105, "channel_name": "audio_105_1720000", "host_id": 12 }`
9. `GET /room_get.php`
   - **Query:** `?room_id=105`
   - **Returns:** Full room state, current 12 seat holders, speaker status, host info, gem count.
10. `POST /room_join.php`
    - **Body:** `{ "room_id": 105 }`
    - **Action:** Increments viewer count, logs entry in active listeners table.
11. `POST /room_leave.php`
    - **Body:** `{ "room_id": 105 }`
    - **Action:** Decrements viewer count, frees seat if user was sitting on a mic.
12. `POST /room_end.php`
    - **Body:** `{ "room_id": 105 }`
    - **Action:** Host only. Marks room as ended, calculates total beans received, session duration.

---

### C. Audio Seat Management & Moderation (PHP Fallback / State API)
13. `GET /room_seats.php`
    - **Query:** `?room_id=105`
    - **Returns:** `[{ "seat_index": 1, "user_id": "...", "name": "Host", "is_muted": false }, ...]`
14. `POST /room_seat_take.php`
    - **Body:** `{ "room_id": 105, "seat_index": 3 }`
    - **Action:** Verifies seat is empty, assigns to user.
15. `POST /room_seat_leave.php`
    - **Body:** `{ "room_id": 105, "seat_index": 3 }`
    - **Action:** Vacates seat.
16. `POST /room_seat_mute.php`
    - **Body:** `{ "room_id": 105, "seat_index": 3, "is_muted": true }`
    - **Action:** Host/Admin only. Enforces mute on seat.
17. `POST /room_seat_kick.php`
    - **Body:** `{ "room_id": 105, "seat_index": 3 }`
    - **Action:** Host only. Removes user from speaker seat back to audience.
18. `POST /room_lock_seat.php`
    - **Body:** `{ "room_id": 105, "seat_index": 5, "is_locked": true }`
    - **Action:** Locks chair with VIP or host permission requirement.

---

### D. RTC Voice Engine Token Generation
19. `POST /room_token.php`
    - **Body:** `{ "room_id": 105, "role": "publisher" | "subscriber" }`
    - **Action:** Generates signed Agora RTC token / LiveKit JWT with channel name and user UID.
    - **Returns:** `{ "token": "007eJxT...", "app_id": "...", "channel": "audio_105", "uid": 98234 }`

---

### E. Wallet, Coins & Financial Transactions
20. `GET /wallet.php`
    - **Returns:** `{ "beans": 25000, "diamonds": 1200, "recharge_options": [...] }`
21. `POST /wallet_recharge.php`
    - **Body:** `{ "package_id": 2, "payment_method": "razorpay" }`
    - **Returns:** Order details, payment link or SDK order ID.
22. `POST /wallet_verify_payment.php`
    - **Body:** `{ "order_id": "...", "payment_id": "...", "signature": "..." }`
    - **Action:** Validates gateway cryptographic hash, increments user beans.
23. `GET /wallet_transactions.php`
    - **Query:** `?page=1&limit=20`
    - **Returns:** History of bean recharges, gifts sent, earnings withdrawn.
24. `GET /earnings.php`
    - **Returns:** Creator host earnings, converted diamonds, withdrawable amount.
25. `POST /withdraw_request.php`
    - **Body:** `{ "diamonds": 5000, "upi_id": "user@bank" }`

---

### F. Gifting & Economy System
26. `GET /gifts.php`
    - **Returns:** List of gifts grouped by category (Popular, Luxury, VIP, Event) with bean costs, SVGA/Lottie assets.
27. `POST /room_send_gift.php`
    - **Body:**
      ```json
      {
        "room_id": 105,
        "gift_id": 14,
        "receiver_user_id": "SL9821",
        "seat_index": 1,
        "count": 1
      }
      ```
    - **Action:**
      1. Locks sender's wallet in MySQL transaction (`SELECT ... FOR UPDATE`).
      2. Validates `balance >= total_cost`.
      3. Deducts `total_cost` beans from sender.
      4. Credits diamond commission (e.g. 70%) to host/seat receiver.
      5. Inserts audit log in `gift_transactions`.
      6. Triggers webhook to Node.js server.
    - **Returns:** `{ "success": true, "remaining_beans": 1450, "txn_id": "TXN_99182" }`

---

### G. Room Games (Fun & Retention)
28. `POST /game_spin.php`
    - **Body:** `{ "room_id": 105, "bet_beans": 10 }`
    - **Action:** RNG wheel spin, deducts 10 beans, awards prize beans.
29. `POST /game_dice.php`
    - **Body:** `{ "room_id": 105, "bet_beans": 5 }`
    - **Action:** Dice roll 1-6 with multiplier payouts.
30. `POST /game_lucky_draw.php`
    - **Body:** `{ "room_id": 105, "ticket_cost": 20 }`

---

### H. Social & Community
31. `POST /follow.php`
    - **Body:** `{ "target_user_id": "SL1234", "action": "follow" | "unfollow" }`
32. `GET /followers.php`
    - **Query:** `?user_id=SL1234`
33. `GET /leaderboard.php`
    - **Query:** `?type=daily | weekly | monthly&room_id=105`
    - **Returns:** Top gifters rank list.
34. `GET /banners.php`
    - **Returns:** Promotional event carousels for party screen.
35. `GET /store_items.php` & `POST /store_buy.php`
    - **Action:** Buy and equip avatar frames, entrance luxury effects, room wallpapers.
36. `POST /report_user.php` & `POST /block_user.php`
    - **Action:** User safety, harassment reporting.

---

## 4. Realtime WebSocket Events (Node.js Gateway)

Connect URI: `wss://socket.smartlive.space` (or via Socket.io client).  
Connection Auth: Handshake query `{ "token": "JWT_TOKEN", "user_id": "SL1234" }`.

### Client -> Node.js (Emits)

| Event Name | Payload | Description |
|---|---|---|
| `room:join` | `{ "room_id": 105 }` | Subscribes socket to room room-105 channel. |
| `room:leave` | `{ "room_id": 105 }` | Leaves room channel. |
| `chat:send` | `{ "room_id": 105, "message": "Hello mic!" }` | Sends instant text comment. |
| `seat:request` | `{ "room_id": 105, "seat_index": 3 }` | Requests an empty speaker seat. |
| `seat:vacate` | `{ "room_id": 105, "seat_index": 3 }` | Speaker voluntary leaves chair. |
| `seat:mute_self` | `{ "room_id": 105, "is_muted": true }` | Speaker toggles own mic state. |
| `host:seat_action` | `{ "room_id": 105, "action": "mute|kick|lock", "seat_index": 3 }` | Host moderates chair. |
| `reaction:send` | `{ "room_id": 105, "emoji": "🔥" }` | Emits floating screen reaction. |
| `gift:notify` | `{ "room_id": 105, "txn_id": "TXN_99182", "gift_id": 14 }` | Broadcasts after PHP confirms gift. |

---

### Node.js -> Client (Broadcast Listens)

| Event Name | Payload | Client Action in Flutter |
|---|---|---|
| `room:user_joined` | `{ "user_id": "...", "name": "...", "avatar": "...", "listeners_count": 45 }` | Shows entry banner & updates listener pill. |
| `room:user_left` | `{ "user_id": "...", "listeners_count": 44 }` | Decrements listener pill. |
| `chat:new_message` | `{ "user": "Riya", "message": "Hi!", "level": 5, "badge": "VIP" }` | Appends item to `controller.messages`. |
| `seat:updated` | `[{ "seat_index": 1, "user_id": "...", "name": "...", "is_muted": false }, ...]` | Re-renders `controller.seats[index]`. |
| `seat:speaking` | `{ "seat_index": 2, "is_speaking": true, "volume": 85 }` | Shows green pulsing soundwave ring around avatar. |
| `gift:broadcast` | `{ "sender": "NightOwl", "receiver": "Host", "gift_name": "Crown", "gift_id": 14, "cost": 500, "anim_type": "svga" }` | Triggers full-screen `ActiveGiftAnimation`. |
| `reaction:floating` | `{ "emoji": "❤️", "x_offset": 0.4 }` | Spawns floating animated heart bubbles. |
| `room:ended` | `{ "message": "Host closed the room" }` | Pops room view to home page with summary dialog. |

---

## 5. Audio Room Seat Matrix & Voice RTC Signaling

The party room UI (`audio_room_view.dart`) features a **12-seat layout**:
- **Seat 0 (Position 1):** Room Host / Creator (always reserved).
- **Seat 1 to 11 (Positions 2 to 12):** Co-hosts, VIPs, or Audience members who take chair.

```
+-----------------------------------------------------------+
|                    [Seat 1: HOST 👑]                      |
|                                                           |
| [Seat 2]      [Seat 3]      [Seat 4]      [Seat 5]        |
| [Seat 6]      [Seat 7]      [Seat 8]      [Seat 9]        |
| [Seat 10]     [Seat 11]     [Seat 12]                     |
+-----------------------------------------------------------+
```

### Voice Role Lifecycle on Seat Change:
1. **User enters room:**
   - RTC Engine initialized with `role = ClientRoleAudience`.
   - Client can hear all 12 microphones with zero delay.
   - Client microphone hardware is completely disabled.
2. **User taps Seat 4:**
   - Client emits `seat:request` to Node.js.
   - Node.js confirms seat available -> updates in-memory room state -> emits `seat:updated` to all users.
   - Client switches Agora/RTC role to `ClientRoleBroadcaster`.
   - Microphone opens; user's voice is instantly streamed to the room.
3. **User vacates Seat 4:**
   - Role switches back to `ClientRoleAudience`.
   - Microphone hardware closes.

---

## 6. App Status Audit & Implementation Checklist

| Component | Status in Flutter App | Backend API Status | Action Required |
|---|:---:|:---:|---|
| **Audio Room UI** | ✅ 100% Complete (`audio_room_view.dart`) | 🟡 Partial | Connect Node.js sockets instead of local `Timer` simulations. |
| **Room Creation** | ✅ Connected (`room_create.php`) | ✅ Ready | Returns channel & room ID. |
| **Room Listing** | ✅ Connected (`room_list.php`) | ✅ Ready | Home party tab displays active audio rooms. |
| **Voice Engine (Agora/LiveKit)** | ❌ Pending Package | ❌ Needs Token API | Add `agora_rtc_engine: ^6.3.0` to `pubspec.yaml` + build `POST /room_token.php`. |
| **Realtime Sockets** | 🟡 Mock Timers | ❌ Needs Node.js Server | Add `socket_io_client: ^3.0.0` and connect to Socket gateway. |
| **12 Seats Synchronization** | 🟡 Local Mock in Controller | ❌ In-memory Node state | Replace `_initSeats()` mock with `socket.on('seat:updated')`. |
| **Live Chat** | 🟡 Local simulated chats | ❌ In-memory Node state | Wire `sendMessage()` to `socket.emit('chat:send')`. |
| **Gifting Transaction** | 🟡 Local bean deduct | ✅ PHP logic exists | Call `POST /room_send_gift.php`, then broadcast via socket. |
| **Wallet Recharge** | ✅ Connected | 🟡 Simulation | Integrate real Razorpay/Cashfree webhook verification. |
| **Games (Wheel, Dice, Quiz)** | ✅ UI & Sounds 100% | 🟡 Client-side RNG | Connect to server payout verification to prevent coin cheating. |

---

## 7. Recommended Next Immediate Steps

1. **Step 1 (PHP):** Ensure `POST /room_token.php` and `POST /room_send_gift.php` are active on `https://saptahikgyan.space/smart/php-api/App`.
2. **Step 2 (Node.js):** Deploy simple Node.js WebSocket gateway with Redis or in-memory rooms for `room:join`, `chat:send`, and `seat:updated`.
3. **Step 3 (Flutter):**
   - Add `agora_rtc_engine` (or `livekit_client`) + `socket_io_client` to `pubspec.yaml`.
   - In `AudioRoomController`, replace the simulation timers with real socket event listeners.
   - Audio Live becomes 100% operational with multi-mic voice and realtime audience interactions!
