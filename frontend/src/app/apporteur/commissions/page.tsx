'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { apiFetch } from '@/lib/api';

type Commission = { id: string; amount_minor?: number; currency?: string; status?: string };

export default function CommissionsPage() {
  const [rows, setRows] = useState<Commission[]>([]);
  const [error, setError] = useState('');

  useEffect(() => {
    apiFetch<Commission[] | { items?: Commission[] }>('/commissions')
      .then((data) => setRows(Array.isArray(data) ? data : data.items ?? []))
      .catch((caught: unknown) => setError(caught instanceof Error ? caught.message : 'Lecture refusée.'));
  }, []);

  return (
    <main className="desk">
      <Link className="mark" href="/apporteur">Veille</Link>
      <h1>Commissions</h1>
      {error ? <p className="error">{error}</p> : null}
      <ul className="lines">
        {rows.map((row) => (
          <li key={row.id}>
            <span style={{ display: 'grid', gridTemplateColumns: '1fr auto', padding: '14px 0', borderBottom: '1px solid rgba(243,239,230,0.2)' }}>
              <span>{row.status ?? 'en cours'}</span>
              <small>{row.amount_minor ?? '—'} {row.currency ?? ''}</small>
            </span>
          </li>
        ))}
      </ul>
      {rows.length === 0 && !error ? <p className="note">Aucune commission acquise sur ce compte.</p> : null}
    </main>
  );
}
