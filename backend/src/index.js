import 'dotenv/config';
import express from 'express';
import cors from 'cors';
import pg from 'pg';
import Redis from 'ioredis';

const app = express();
app.use(cors());
app.use(express.json());

const port = process.env.BACKEND_PORT || 4000;

// ── DB connection (used by /health to prove connectivity, not yet by any route) ──
const pool = new pg.Pool({
  host: process.env.POSTGRES_HOST,
  port: process.env.POSTGRES_PORT || 5432,
  database: process.env.POSTGRES_DB,
  user: process.env.POSTGRES_USER,
  password: process.env.POSTGRES_PASSWORD,
});

// ── Redis connection ──
const redis = new Redis({
  host: process.env.REDIS_HOST,
  port: process.env.REDIS_PORT || 6379,
});

app.get('/', (req, res) => {
  res.json({ service: 'eBid Hub API', status: 'Phase 0 — foundation', version: '0.1.0' });
});

// Walking-skeleton health check: proves app, DB, and Redis are all reachable
// together — the same shape of check used to validate the deployment pipeline
// itself before real business logic is layered on top.
app.get('/health', async (req, res) => {
  const health = { app: 'ok', database: 'unknown', redis: 'unknown' };

  try {
    await pool.query('SELECT 1');
    health.database = 'ok';
  } catch (err) {
    health.database = `error: ${err.message}`;
  }

  try {
    await redis.ping();
    health.redis = 'ok';
  } catch (err) {
    health.redis = `error: ${err.message}`;
  }

  const allOk = health.database === 'ok' && health.redis === 'ok';
  res.status(allOk ? 200 : 503).json(health);
});

app.listen(port, () => {
  console.log(`eBid Hub API listening on port ${port}`);
});
