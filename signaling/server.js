import { WebSocketServer } from 'ws';
import { randomUUID } from 'crypto';

const PORT = process.env.SIGNALING_PORT ? Number(process.env.SIGNALING_PORT) : 8089;
// Optional TURN relays can be advertised to clients via an env-provided JSON, or you can
// keep using only public STUN in the client code. We only signal; clients set ICE servers.
const TURN_RELAYS = process.env.TURN_RELAYS_JSON ? JSON.parse(process.env.TURN_RELAYS_JSON) : null;

/**
 * In-memory room and user registry. For production you might use Redis.
 * Structure: rooms[roomId] = Set of clientIds
 */
const rooms = new Map();
const clients = new Map(); // clientId -> ws

const wss = new WebSocketServer({ port: PORT });

function send(ws, type, payload) {
  try {
    ws.send(JSON.stringify({ type, ...payload }));
  } catch (_) {}
}

function broadcastToRoom(roomId, exceptClientId, messageObj) {
  const members = rooms.get(roomId);
  if (!members) return;
  const payload = JSON.stringify(messageObj);
  members.forEach((memberId) => {
    if (memberId === exceptClientId) return;
    const peer = clients.get(memberId);
    if (peer && peer.readyState === 1) {
      try { peer.send(payload); } catch (_) {}
    }
  });
}

wss.on('connection', (ws) => {
  const clientId = randomUUID();
  clients.set(clientId, ws);
  let roomId = null;

  send(ws, 'welcome', { clientId });

  ws.on('message', (data) => {
    let msg;
    try { msg = JSON.parse(String(data)); } catch (e) { return; }
    const { type } = msg || {};
    if (!type) return;

    if (type === 'join') {
      roomId = msg.roomId;
      if (!roomId) return;
      if (!rooms.has(roomId)) rooms.set(roomId, new Set());
      rooms.get(roomId).add(clientId);
      // Send existing peers
      const peers = Array.from(rooms.get(roomId)).filter((id) => id !== clientId);
      send(ws, 'peers', { peers });
      // Notify others
      broadcastToRoom(roomId, clientId, { type: 'peer-joined', clientId });
      return;
    }

    // WebRTC signaling relay
    if (type === 'signal') {
      const { targetId, data: signalData } = msg;
      const target = clients.get(targetId);
      if (target) {
        send(target, 'signal', { fromId: clientId, data: signalData });
      }
      return;
    }

    if (type === 'typing') {
      if (!roomId) return;
      const state = msg.state === 'start' ? 'start' : 'stop';
      broadcastToRoom(roomId, clientId, { type: 'typing', clientId, state });
      return;
    }

    if (type === 'leave') {
      if (roomId && rooms.has(roomId)) {
        rooms.get(roomId).delete(clientId);
        broadcastToRoom(roomId, clientId, { type: 'peer-left', clientId });
      }
      return;
    }
  });

  ws.on('close', () => {
    clients.delete(clientId);
    if (roomId && rooms.has(roomId)) {
      rooms.get(roomId).delete(clientId);
      broadcastToRoom(roomId, clientId, { type: 'peer-left', clientId });
    }
  });
});

console.log(`[signaling] WebSocket server listening on :${PORT}`);
if (TURN_RELAYS) {
  console.log(`[signaling] TURN relays advertised (client-side use): ${JSON.stringify(TURN_RELAYS)}`);
}

