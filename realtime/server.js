// eBid Hub — Real-time WebSocket sidecar (D-42, extended D-52 for the
// Live Ticker / BR-48)
//
// This is a genuinely separate, long-running process from the PHP
// application — CodeIgniter/PHP has no native way to hold a connection
// open and push updates, so live bidding updates need this dedicated
// process running alongside Nginx/PHP-FPM.
//
// Architecture:
//   Browser <--WebSocket--> this server <--internal HTTP--> PHP app
//
// Two genuinely distinct room namespaces:
//   - "sale_event:<uuid>" — a listing page watching ONE specific
//     auction (D-42's original model, unchanged).
//   - "buyer:<uuid>" — a buyer's Live Ticker, watching potentially MANY
//     auctions at once (their own active bids, plus CLV matches).
//     A single persistent connection per buyer, not one connection per
//     auction they happen to care about — the proper long-term fix
//     rather than the browser opening N separate sockets.
//
// PHP calls /broadcast (existing, sale-event-scoped) whenever a
// bid-relevant event happens on a specific auction, and separately
// calls /broadcast-to-buyer (new) whenever that same event is relevant
// to a specific buyer's ticker — PHP decides who cares and why; this
// server is purely a relay, same discipline as D-42.

const http = require('http');
const { WebSocketServer } = require('ws');

const WS_PORT = process.env.EBIDHUB_WS_PORT || 8081;
const BROADCAST_SECRET = process.env.EBIDHUB_BROADCAST_SECRET || 'dev-only-change-in-production';

const saleEventRooms = new Map();
const buyerRooms = new Map();

function joinRoom(roomMap, roomKey, ws, wsPropertyName) {
    if (!roomMap.has(roomKey)) {
        roomMap.set(roomKey, new Set());
    }
    roomMap.get(roomKey).add(ws);
    ws[wsPropertyName] = roomKey;
}

function leaveRoom(roomMap, roomKey, ws) {
    if (!roomKey || !roomMap.has(roomKey)) return;
    roomMap.get(roomKey).delete(ws);
    if (roomMap.get(roomKey).size === 0) {
        roomMap.delete(roomKey);
    }
}

function broadcastToRoom(roomMap, roomKey, payload) {
    const room = roomMap.get(roomKey);
    if (!room) return 0;
    const message = JSON.stringify(payload);
    let sent = 0;
    for (const ws of room) {
        if (ws.readyState === ws.OPEN) {
            ws.send(message);
            sent++;
        }
    }
    return sent;
}

function handleBroadcastRequest(body, roomMap, keyField) {
    const parsed = JSON.parse(body);
    if (parsed.secret !== BROADCAST_SECRET) {
        return { status: 403, body: { error: 'invalid secret' } };
    }
    if (!parsed[keyField] || !parsed.event) {
        return { status: 400, body: { error: `${keyField} and event are required` } };
    }

    const sentCount = broadcastToRoom(roomMap, parsed[keyField], {
        event: parsed.event,
        data: parsed.data || {},
        timestamp: new Date().toISOString(),
    });

    return { status: 200, body: { ok: true, delivered: sentCount } };
}

const httpServer = http.createServer((req, res) => {
    if (req.method !== 'POST' || (req.url !== '/broadcast' && req.url !== '/broadcast-to-buyer')) {
        res.writeHead(404);
        res.end();
        return;
    }

    let body = '';
    req.on('data', (chunk) => { body += chunk; });
    req.on('end', () => {
        try {
            const result = req.url === '/broadcast'
                ? handleBroadcastRequest(body, saleEventRooms, 'saleEventId')
                : handleBroadcastRequest(body, buyerRooms, 'buyerId');
            res.writeHead(result.status, { 'Content-Type': 'application/json' });
            res.end(JSON.stringify(result.body));
        } catch (err) {
            res.writeHead(400);
            res.end(JSON.stringify({ error: 'invalid JSON body' }));
        }
    });
});

const wss = new WebSocketServer({ server: httpServer, path: '/ws' });

wss.on('connection', (ws, req) => {
    const url = new URL(req.url, `http://${req.headers.host}`);
    const saleEventId = url.searchParams.get('saleEventId');
    const buyerId = url.searchParams.get('buyerId');

    if (!saleEventId && !buyerId) {
        ws.close(1008, 'saleEventId or buyerId query parameter is required');
        return;
    }

    if (saleEventId) {
        joinRoom(saleEventRooms, saleEventId, ws, '_saleEventId');
    }
    if (buyerId) {
        joinRoom(buyerRooms, buyerId, ws, '_buyerId');
    }
    ws.send(JSON.stringify({ event: 'connected', data: { saleEventId, buyerId } }));

    ws.on('close', () => {
        leaveRoom(saleEventRooms, ws._saleEventId, ws);
        leaveRoom(buyerRooms, ws._buyerId, ws);
    });
    ws.on('error', () => {
        leaveRoom(saleEventRooms, ws._saleEventId, ws);
        leaveRoom(buyerRooms, ws._buyerId, ws);
    });
});

httpServer.listen(WS_PORT, () => {
    console.log(`eBid Hub realtime sidecar listening on port ${WS_PORT}`);
});
