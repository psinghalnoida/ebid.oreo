# eBid Hub realtime sidecar — the WebSocket server (D-42) that
# broadcasts live bidding/settlement/dispute/rating/cascade/admin
# events to connected browsers. Deployed as its own Cloud Run service,
# separate from the main PHP app, per the same process-separation the
# app already assumes (RealtimeBroadcastService talks to it over HTTP,
# never in-process).
#
# Base image pulled via mirror.gcr.io — see Dockerfile's own comment
# for why.

FROM mirror.gcr.io/library/node:20-alpine
WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci --omit=dev

COPY server.js ./

# Cloud Run injects PORT (default 8080); server.js reads
# EBIDHUB_WS_PORT, not PORT directly, so the deploy step sets
# EBIDHUB_WS_PORT=8080 explicitly to match --port 8080 — no code
# change needed to make this Cloud Run-compatible.
EXPOSE 8080
CMD ["node", "server.js"]
