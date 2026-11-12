# SmartLive — App API Integration Guide (`appapiconnect.md`)

Master connection guide for the Flutter Mobile Application connecting to:
1. **PHP + MySQL Backend:** Transactional REST APIs (`https://saptahikgyan.space/smart/php-api/App`).
2. **Node.js Socket Gateway:** Realtime Room Events & Seat Matrix (`wss://socket.smartlive.space` / `http://YOUR_SERVER:4000`).
3. **Voice RTC Engine:** ZegoCloud / Agora for 12-Chair Multi-Mic Party Rooms.

---

## 🌐 1. Server Endpoints & Environments

| Component | Protocol | Production URL |
|---|---|---|
| **PHP REST API** | HTTPS | `https://saptahikgyan.space/smart/php-api/App` |
| **Node.js WebSocket** | WSS / Socket.io | `wss://socket.smartlive.space` (or `http://YOUR_IP:4000`) |
| **Voice RTC Engine** | UDP / WebRTC | ZegoCloud Express SDK / Agora RTC Engine |

---

## 📋 2. Complete PHP REST API Endpoints

All protected endpoints require:
`Authorization: Bearer <TOKEN>` (or `?token=<TOKEN>`)

### A. Authentication & User Profile
| Endpoint | Method | Purpose | Payload / Notes |
|---|:---:|---|---|
| `/signup.php` | `POST` | Register new user | `{ "name", "email", "password", "phone", "country_code", "country" }` |
| `/login.php` | `POST` | User login | `{ "email" OR "user_id", "password" }` -> returns token & `smart-lv-XXXXXX` |
| `/google_auth.php` | `POST` | Google Sign-in / Sign-up | `{ "google_id", "email", "name", "avatar", "country_code" }` |
| `/logout.php` | `POST` | User logout | Invalidate session token |
| `/profile.php` | `GET` | Get current profile | Returns user details, level, coins balance |
| `/update_profile.php` | `POST` | Update name, avatar, DOB | Supports `multipart/form-data`, Base64, or JSON (`dob`, `gender`, `avatar`) |
| `/upload_image.php` | `POST` | Standalone Image Upload | Uploads image file to server `uploads/avatars/` |
| `/change_password.php` | `POST` | Change password | `{ "old_password", "new_password" }` |

---

### B. Audio Party Rooms (12-Mic Voice Rooms)
| Endpoint | Method | Purpose | Payload / Notes |
|---|:---:|---|---|
| `/room_list.php` | `GET` | List active audio rooms | `?category=All&page=1&limit=20` |
| `/room_create.php` | `POST` | Create audio party room | `{ "title", "category", "cover" }` -> returns `room_id`, `channel` |
| `/room_get.php` | `GET` | Full room info & host details | `?id=105` |
| `/room_join.php` | `POST` | Join audio room as listener | `{ "room_id": 105 }` -> increments listener count |
| `/room_leave.php` | `POST` | Leave audio room | `{ "room_id": 105 }` -> decrements listener count |
| `/room_end.php` | `POST` | Host ends audio room | `{ "room_id": 105 }` (Host only) |
| `/room_seats.php` | `GET` | Fetch 12 seats matrix | `?room_id=105` -> returns all 12 chair statuses |
| `/room_seat_take.php` | `POST` | Take empty microphone chair | `{ "room_id": 105, "seat_index": 3 }` |
| `/room_seat_leave.php` | `POST` | Step down from mic to audience | `{ "room_id": 105, "seat_index": 3 }` |
| `/room_seat_mute.php` | `POST` | Mute/unmute microphone | `{ "room_id": 105, "seat_index": 3, "is_muted": true }` |
| `/room_seat_kick.php` | `POST` | Host kicks speaker to audience | `{ "room_id": 105, "seat_index": 3 }` (Host only) |
| `/room_token.php` | `POST` | **ZegoCloud / Agora RTC Token** | `{ "room_id": 105, "role": "publisher" \| "subscriber", "provider": "zego" }` |
| `/room_send_gift.php` | `POST` | **Send gift in audio room** | `{ "room_id": 105, "gift_id": 4, "receiver_id": 1, "seat_index": 1, "quantity": 1 }` |

---

### C. Live Video Streams
| Endpoint | Method | Purpose | Payload / Notes |
|---|:---:|---|---|
| `/stream_list.php` | `GET` | Live video streams grid | `?status=live&category=Popular&limit=20` |
| `/stream_create.php` | `POST` | Go live video | `{ "title", "category", "tag", "cover" }` |
| `/stream_get.php` | `GET` | Single stream details | `?id=1` |
| `/stream_join.php` | `POST` | Join video stream | `{ "stream_id": 1 }` |
| `/stream_leave.php` | `POST` | Leave video stream | `{ "stream_id": 1 }` |
| `/stream_end.php` | `POST` | Host ends stream | `{ "stream_id": 1 }` |
| `/stream_send_gift.php` | `POST` | Send gift in video stream | `{ "stream_id": 1, "gift_id": 2, "quantity": 1 }` |

---

### D. Wallet, Recharges & Earnings
| Endpoint | Method | Purpose | Payload / Notes |
|---|:---:|---|---|
| `/wallet.php` | `GET` | User coin/bean balance | Returns `coins`, recharge packages |
| `/wallet_recharge.php` | `POST` | Coin recharge package | `{ "package_id": 1, "payment_method": "manual" }` |
| `/wallet_transactions.php` | `GET` | Financial ledger history | `?page=1&limit=20` |
| `/earnings.php` | `GET` | Host earnings summary | Live stream & party room earnings |

---

### E. Social, Store, Shorts & Banners
| Endpoint | Method | Purpose | Payload / Notes |
|---|:---:|---|---|
| `/banners.php` | `GET` | Home carousel banners | List of event & promo banners |
| `/gifts.php` | `GET` | Catalog of virtual gifts | Grouped by category with coin values |
| `/store_items.php` | `GET` | Avatar frames, rides, badges | Store catalog |
| `/store_buy.php` | `POST` | Buy item with coins | `{ "item_id": 2 }` |
| `/my_bag.php` | `GET` | Backpack of purchased items | User inventory |
| `/equip_item.php` | `POST` | Equip frame/ride/badge | `{ "bag_id": 5, "action": "equip" \| "unequip" }` |
| `/leaderboard.php` | `GET` | Top gifters / creators | `?type=coins_sent&period=all` |
| `/follow.php` | `POST` | Follow / unfollow user | `{ "following_id": 1, "action": "follow" \| "unfollow" }` |
| `/followers.php` | `GET` | Followers & following lists | `?user_id=1&type=followers` |
| `/shorts_list.php` | `GET` | Video shorts / reels feed | `?page=1&limit=10` |
| `/short_like.php` | `POST` | Like / unlike short | `{ "short_id": 1 }` |
| `/notifications.php` | `GET` | App alerts & push notices | System and promo notifications |
| `/search.php` | `GET` | Global search | `?q=Pari` |
| `/level.php` | `GET` | Level XP progress | Current user level & badges |

---

## ⚡ 3. Realtime Audio Room Flow in Flutter

### Step-by-Step Execution:

```
[1. User opens Audio Room]
      │
      ├──> (A) PHP: Call POST /room_join.php
      ├──> (B) PHP: Call POST /room_token.php (Get ZegoCloud/Agora Channel credentials)
      ├──> (C) Node.js: Connect Socket & emit 'room:join', { room_id: 105 }
      └──> (D) RTC Engine: Initialize ZegoCloud / Agora with role = Audience
      
[2. User taps Seat 4 to Speak]
      │
      ├──> (A) Node.js: emit 'seat:take', { room_id: 105, seat_index: 4 }
      ├──> (B) Node.js broadcasts: 'seat:updated' (All users see Seat 4 occupied)
      ├──> (C) PHP: Optional background sync POST /room_seat_take.php
      └──> (D) RTC Engine: Switch role from Audience to Publisher (Microphone unmuted)

[3. User steps down from Seat 4]
      │
      ├──> (A) Node.js: emit 'seat:vacate', { room_id: 105, seat_index: 4 }
      ├──> (B) Node.js broadcasts: 'seat:updated' (Seat 4 is now empty)
      └──> (C) RTC Engine: Switch role back to Audience (Microphone disabled)

[4. Sending Gift]
      │
      ├──> (A) PHP: Call POST /room_send_gift.php
      │         └──> Wallet coin deduction verified securely in MySQL
      └──> (B) Node.js: Upon PHP success, broadcast 'gift:broadcast'
                └──> All viewers simultaneously render full-screen SVGA animation!
```

---

## 📱 4. Flutter Dependencies Recommended

Add to `pubspec.yaml`:

```yaml
dependencies:
  flutter:
    sdk: flutter

  # REST API & HTTP
  http: ^1.2.0

  # Realtime WebSockets
  socket_io_client: ^3.0.1

  # Audio Live Streaming (Choose ZegoCloud OR Agora)
  # Option 1: ZegoCloud Live Audio Room
  zego_uikit_prebuilt_live_audio_room: ^3.7.0
  # OR Option 2: Agora RTC Engine
  agora_rtc_engine: ^6.3.2

  # Rich Gift Animations
  svgaplayer_flutter: ^2.2.0
  lottie: ^3.1.0
```

---

## 🛡️ 5. Security & Verification Rules
1. **Never send coin balances directly from client sockets.** All balance deductions must pass through PHP (`/room_send_gift.php`, `/store_buy.php`).
2. **Token Authentication:** Send the session token in the Socket handshake query or headers:
```dart
IO.Socket socket = IO.io('https://socket.smartlive.space', 
  IO.OptionBuilder()
    .setTransports(['websocket'])
    .setAuth({'token': userToken, 'user_id': userId})
    .build()
);
```
3. **Database Consistency:** Both PHP and Node.js connect to the same MySQL database: `saptahi1_smartlive`. Node.js reads in-memory seats and occasionally persists, while PHP handles financial transactions.
