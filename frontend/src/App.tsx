import { useEffect, useState } from 'react';

type Health = {
  app: string;
  database: string;
  redis: string;
};

export default function App() {
  const [health, setHealth] = useState<Health | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    fetch('http://localhost:4000/health')
      .then((res) => res.json())
      .then(setHealth)
      .catch((err) => setError(String(err)));
  }, []);

  return (
    <div style={{ fontFamily: 'sans-serif', padding: '40px', maxWidth: '520px' }}>
      <h1>eBid Hub</h1>
      <p style={{ color: '#666' }}>Phase 0 — foundation skeleton. No sale format is functional yet.</p>

      <h3>Backend connectivity check</h3>
      {error && <p style={{ color: 'crimson' }}>Could not reach backend: {error}</p>}
      {!error && !health && <p>Checking...</p>}
      {health && (
        <ul>
          <li>App: {health.app}</li>
          <li>Database: {health.database}</li>
          <li>Redis: {health.redis}</li>
        </ul>
      )}

      <p style={{ color: '#999', fontSize: '13px' }}>
        Landing page / auction page mockups (Modern Marketplace Minimal direction)
        will be wired in as real routes once Phase 0 business logic exists.
      </p>
    </div>
  );
}
