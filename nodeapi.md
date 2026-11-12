# SmartLive — Realtime Node.js Socket Gateway Architecture (`nodeapi.md`)

> **Protocol & Architectural Segregation:**
> - **PHP + MySQL:** Auth, Database Ledger, Beans/Coin Deductions, Gift Purchasing, Room Record Creation & Finalization.
> - **Node.js + Socket.io:** Realtime WebSocket Gateway for Low-latency Room Events, 12-Seat State Sync, Floating Emojis, Live Chat, and Gift Animations.
> - **Voice RTC (Agora / ZegoCloud):** Dedicated Multi-mic Voice Mesh streaming high-fidelity audio (<150ms latency) across all 12 speaker chairs.

---

## 🏗️ 1. Architecture Flow

```
   [ Flutter App ]
      |
      |-- (1) REST / HTTPS (PHP) -------------> [ PHP + MySQL API ]
      |     - User Login & Register                - Stores users, wallets, rooms
      |     - Room Creation & DB Entry             - Coin deduction & Ledger logs
      |     - Gift Purchase (/room_send_gift.php)  - Generates RTC Tokens
      |     - Generates RTC Voice Token
      |
      |-- (2) WebSocket / WSS (Node.js) ------> [ Node.js Socket.io Server ]
      |     - Instant In-room Chat                 - In-memory 12 Seats State Matrix
      |     - Seat Sync (Take / Vacate / Mute)     - Broadcasts Chat & Floating Emojis
      |     - Gift Animation Broadcast             - Broadcasts Full-screen Gift Animations
      |     - Speaker Soundwave Indicators         - Viewer count pulses
      |
      |-- (3) WebRTC / UDP (Agora / Zego) ----> [ ZegoCloud / Agora RTC Cloud ]
            - Multi-mic 12 Speaker Channels        - Audio Mixing & Low Latency Streaming
            - Listener Audience Audio Stream       - Pitch & Speaking Volume Detection
```

---

## 🔌 2. Socket Connection & Authentication

- **Socket Server URL:** `wss://socket.smartlive.space` (or `http://YOUR_SERVER_IP:4000`)
- **Transport:** `websocket`, `polling`

### Client Handshake Connection:
```javascript
const socket = io('https://socket.smartlive.space', {
  transports: ['websocket'],
  auth: {
    token: 'USER_SESSION_TOKEN',     // Token received from PHP /login.php
    user_id: 17,                     // MySQL user id
    custom_user_id: 'smart-lv-000014',
    user_name: 'Alex Kumar',
    avatar: 'https://...',
  }
});
```

---

## 📡 3. Socket Events Specification

### A. Room Lifecycle & Attendance

#### 1. `room:join` (Client -> Server)
Emitted by user when entering an Audio Room or Live Stream.
```json
// Emit:
{
  "room_id": 105,
  "room_type": "audio" // "audio" | "video"
}
```
**Server Actions:**
- Adds socket to Socket.io room channel `room_105`.
- Updates room viewer count in memory / Redis.
- Emits `room:user_joined` to all clients in `room_105`.
- Sends current full room state (`room:state`) to the joined socket.

#### 2. `room:user_joined` (Server -> Client Broadcast)
```json
{
  "user": {
    "id": 17,
    "user_id": "smart-lv-000014",
    "name": "Alex Kumar",
    "avatar": "https://...",
    "level": 12,
    "vip": 1
  },
  "listener_count": 48
}
```

#### 3. `room:leave` (Client -> Server)
```json
{
  "room_id": 105
}
```
**Server Actions:**
- Leaves channel `room_105`.
- If user was occupying any microphone seat (1 to 12), auto-vacates the chair and emits `seat:updated`.
- Broadcasts `room:user_left`.

#### 4. `room:user_left` (Server -> Client Broadcast)
```json
{
  "user_id": 17,
  "listener_count": 47
}
```

#### 5. `room:ended` (Server -> Client Broadcast)
Broadcast when the host terminates the room.
```json
{
  "room_id": 105,
  "message": "Host has ended the audio room",
  "duration_seconds": 3600,
  "total_coins": 12500
}
```

---

### B. 12-Seat Microphone Management (Multi-Mic Party Room)

#### 6. `seat:take` (Client -> Server)
Emitted when a listener taps an empty chair (Seat 1 to 12).
```json
// Emit:
{
  "room_id": 105,
  "seat_index": 3
}
```
**Server Actions:**
- Validates seat availability in memory / syncs with MySQL.
- Assigns user to Seat 3.
- Broadcasts `seat:updated` to all room listeners.
- Signals client to elevate Agora / ZegoCloud role from `Audience` to `Publisher`.

#### 7. `seat:vacate` (Client -> Server)
Emitted when speaker steps down from the mic back to audience.
```json
// Emit:
{
  "room_id": 105,
  "seat_index": 3
}
```
**Server Actions:**
- Clears Seat 3.
- Broadcasts `seat:updated`.
- Signals client to downgrade RTC role to `Audience` (mute microphone).

#### 8. `seat:updated` (Server -> Client Broadcast)
Broadcasts complete 12-seat matrix whenever any seat changes:
```json
{
  "room_id": 105,
  "seats": [
    {
      "seat_index": 1,
      "user_id": 1,
      "custom_user_id": "smart-lv-000001",
      "name": "Host Pari✨",
      "avatar": "https://...",
      "level": 45,
      "is_muted": false,
      "is_host": true
    },
    {
      "seat_index": 2,
      "user_id": 17,
      "custom_user_id": "smart-lv-000014",
      "name": "Alex Kumar",
      "avatar": "https://...",
      "level": 12,
      "is_muted": false,
      "is_host": false
    },
    {
      "seat_index": 3,
      "user_id": null,
      "custom_user_id": null,
      "name": null,
      "avatar": null,
      "level": 0,
      "is_muted": false,
      "is_host": false
    }
    // ... up to seat_index 12
  ]
}
```

#### 9. `seat:mute_toggle` (Client -> Server)
Host mutes any seat, or speaker mutes own mic.
```json
// Emit:
{
  "room_id": 105,
  "seat_index": 3,
  "is_muted": true
}
```
**Server Broadcasts:**
```json
// Event: "seat:muted_state"
{
  "room_id": 105,
  "seat_index": 3,
  "is_muted": true,
  "muted_by": "host" // "host" | "self"
}
```

#### 10. `seat:speaking` (Server -> Client Broadcast)
Realtime voice volume wave broadcast (detected by Zego / Agora soundwave pulse):
```json
{
  "room_id": 105,
  "seat_index": 2,
  "is_speaking": true,
  "volume": 85
}
```
*Flutter Action:* Shows green animated soundwave ripple around Seat 2 avatar.

---

### C. Live Chat & Instant Messages

#### 11. `chat:send` (Client -> Server)
```json
// Emit:
{
  "room_id": 105,
  "message": "Welcome everyone to the party! 🎵"
}
```

#### 12. `chat:message` (Server -> Client Broadcast)
```json
{
  "id": "msg_1720092831",
  "room_id": 105,
  "user": {
    "id": 17,
    "user_id": "smart-lv-000014",
    "name": "Alex Kumar",
    "avatar": "https://...",
    "level": 12,
    "vip": 1
  },
  "message": "Welcome everyone to the party! 🎵",
  "timestamp": 1720092831000
}
```

---

### D. Gift Animations Broadcast

> **Security Rule:** Gifts are NEVER originated on the socket! The client must first execute `POST /room_send_gift.php` in PHP. Upon successful wallet coin deduction, PHP notifies Node.js (via internal HTTP hook or client emit with PHP response data), and Node.js broadcasts the animation to the room.

#### 13. `gift:broadcast` (Server -> Client Broadcast)
```json
{
  "room_id": 105,
  "sender": {
    "id": 17,
    "name": "Alex Kumar",
    "avatar": "https://..."
  },
  "receiver": {
    "id": 1,
    "name": "Pari✨",
    "avatar": "https://..."
  },
  "seat_index": 1,
  "gift": {
    "id": 5,
    "name": "Royal Horse Carriage",
    "coins": 3500,
    "quantity": 1,
    "anim_type": "svga",
    "anim_url": "https://assets.smartlive.space/gifts/carriage.svga"
  },
  "total_coins": 3500,
  "timestamp": 1720092850
}
```
*Flutter Action:* Renders the SVG / Lottie full-screen gift animation for all room participants!

---

### E. Floating Reactions (Hearts & Emojis)

#### 14. `reaction:send` (Client -> Server)
```json
// Emit:
{
  "room_id": 105,
  "emoji": "🔥" // "❤️" | "🔥" | "🎉" | "👏"
}
```

#### 15. `reaction:broadcast` (Server -> Client Broadcast)
```json
{
  "room_id": 105,
  "user_id": 17,
  "emoji": "🔥",
  "x_offset": 0.65 // Random float between 0.1 and 0.9 for screen floating trajectory
}
```

---

## 🛠️ 4. Recommended Node.js Project Structure

```
smartlive-socket/
├── package.json
├── server.js               // Main Express + Socket.io Server (Port 4000)
├── config/
│   └── database.js         // MySQL connection pool (same DB: saptahi1_smartlive)
├── managers/
│   ├── RoomManager.js      // In-memory 12-seats matrix & listener management
│   └── SocketAuth.js       // Validates PHP sessions table
└── routes/
    └── internalApi.js      // POST /internal/broadcast-gift (invoked by PHP)
```

---

## 🚀 5. Quick Sample Node.js Server (`server.js`)

```javascript
const express = require('express');
const http = require('http');
const { Server } = require('socket.io');
const cors = require('cors');

const app = express();
app.use(cors());
app.use(express.json());

const server = http.createServer(app);
const io = new Server(server, {
  cors: { origin: '*', methods: ['GET', 'POST'] }
});

// In-memory Room State for 12 Seats
const roomSeats = {}; // { [roomId]: Array(12) }
const roomListeners = {}; // { [roomId]: Set(socketId) }

io.on('connection', (socket) => {
  const user = socket.handshake.auth;

  // 1. Join Room
  socket.on('room:join', ({ room_id }) => {
    socket.join(`room_${room_id}`);
    if (!roomListeners[room_id]) roomListeners[room_id] = new Set();
    roomListeners[room_id].add(socket.id);

    io.to(`room_${room_id}`).emit('room:user_joined', {
      user,
      listener_count: roomListeners[room_id].size
    });

    // Send current seats
    if (!roomSeats[room_id]) {
      roomSeats[room_id] = Array.from({ length: 12 }, (_, i) => ({
        seat_index: i + 1,
        user_id: null,
        name: null,
        avatar: null,
        is_muted: false
      }));
    }
    socket.emit('seat:updated', { room_id, seats: roomSeats[room_id] });
  });

  // 2. Chat Message
  socket.on('chat:send', ({ room_id, message }) => {
    io.to(`room_${room_id}`).emit('chat:message', {
      id: 'msg_' + Date.now(),
      room_id,
      user,
      message,
      timestamp: Date.now()
    });
  });

  // 3. Take Seat
  socket.on('seat:take', ({ room_id, seat_index }) => {
    const seats = roomSeats[room_id];
    if (seats && seat_index >= 1 && seat_index <= 12) {
      seats[seat_index - 1] = {
        seat_index,
        user_id: user.user_id,
        name: user.user_name,
        avatar: user.avatar,
        is_muted: false
      };
      io.to(`room_${room_id}`).emit('seat:updated', { room_id, seats });
    }
  });

  // 4. Vacate Seat
  socket.on('seat:vacate', ({ room_id, seat_index }) => {
    const seats = roomSeats[room_id];
    if (seats && seat_index >= 1 && seat_index <= 12) {
      seats[seat_index - 1] = {
        seat_index,
        user_id: null,
        name: null,
        avatar: null,
        is_muted: false
      };
      io.to(`room_${room_id}`).emit('seat:updated', { room_id, seats });
    }
  });

  // 5. Floating Reaction
  socket.on('reaction:send', ({ room_id, emoji }) => {
    io.to(`room_${room_id}`).emit('reaction:broadcast', {
      room_id,
      user_id: user.user_id,
      emoji,
      x_offset: Math.random() * 0.8 + 0.1
    });
  });

  socket.on('disconnect', () => {
    // Auto cleanup room listeners
  });
});

// Internal webhook for PHP Gift Broadcast
app.post('/internal/broadcast-gift', (req, res) => {
  const { room_id, broadcast_data } = req.body;
  if (room_id && broadcast_data) {
    io.to(`room_${room_id}`).emit('gift:broadcast', broadcast_data);
  }
  res.json({ success: true });
});

server.listen(4000, () => {
  console.log('SmartLive Realtime Socket running on port 4000');
});
```
