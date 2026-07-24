// eBid Hub — Real-time WebSocket sidecar (D-42)
//
// This is a genuinely separate, long-running process from the PHP
// application — CodeIgniter/PHP has no native way to hold a connection
// open and push updates, so live bidding updates need this dedicated
// process running alongside Nginx/PHP-FPM.
//
// Architecture:
//   Browser <--WebSocket--> this server <--internal HTTP--> PHP app
//
// PHP calls the internal /broadcast endpoint (protected by a shared
// secret, never exposed publicly) whenever a bid-relevant event happens
// — a bid placed, Dynamic Time extending the clock, the increment
// halving, a cascade completing. This server then pushes that event to
// every browser currently watching that specific sale_event.
//
// Deliberately does NOT touch the database directly — it has no
// knowledge of BR rules, EMD, or anything else. It's purely a message
// relay: PHP decides what happened and is correct, this server just
// gets that message to browsers instantly.

const http = require('http');
const { WebSocketServer } = require('ws');

const WS_PORT = process.env.EBIDHUB_WS_PORT || 8081;
const BROADCAST_SECRET = process.env.EBIDHUB_BROADCAST_SECRET || 'dev-only-change-in-production';

const rooms = new Map();

function joinRoom(saleEventId, ws) {
    if (!rooms.has(saleEventId)) {
        rooms.set(saleEventId, new Set());
    }
    rooms.get(saleEventId).add(ws);
    ws._saleEventId = saleEventId;
}

function leaveRoom(ws) {
    const saleEventId = ws._saleEventId;
    if (!saleEventId || !rooms.has(saleEventId)) return;
    rooms.get(saleEventId).delete(ws);
    if (rooms.get(saleEventId).size === 0) {
        rooms.delete(saleEventId);
    }
}

function broadcastToRoom(saleEventId, payload) {
    const room = rooms.get(saleEventId);
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

const httpServer = http.createServer((req, res) => {
    if (req.method !== 'POST' || req.url !== '/broadcast') {
        res.writeHead(404);
        res.end();
        return;
    }

    let body = '';
    req.on('data', (chunk) => { body += chunk; });
    req.on('end', () => {
        try {
            const parsed = JSON.parse(body);
            if (parsed.secret !== BROADCAST_SECRET) {
                res.writeHead(403);
                res.end(JSON.stringify({ error: 'invalid secret' }));
                return;
            }
            if (!parsed.saleEventId || !parsed.event) {
                res.writeHead(400);
                res.end(JSON.stringify({ error: 'saleEventId and event are required' }));
                return;
            }

            const sentCount = broadcastToRoom(parsed.saleEventId, {
                event: parsed.event,
                data: parsed.data || {},
                timestamp: new Date().toISOString(),
            });

            res.writeHead(200, { 'Content-Type': 'application/json' });
            res.end(JSON.stringify({ ok: true, delivered: sentCount }));
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

    if (!saleEventId) {
        ws.close(1008, 'saleEventId query parameter is required');
        return;
    }

    joinRoom(saleEventId, ws);
    ws.send(JSON.stringify({ event: 'connected', data: { saleEventId } }));

    ws.on('close', () => leaveRoom(ws));
    ws.on('error', () => leaveRoom(ws));
});

httpServer.listen(WS_PORT, () => {
    console.log(`eBid Hub realtime sidecar listening on port ${WS_PORT}`);
});
