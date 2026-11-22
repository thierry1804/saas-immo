'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';

type Item = { actionId: string; at: string; label: string };

export default function QueuePage() {
  const [items, setItems] = useState<Item[]>([]);

  useEffect(() => {
    const raw = window.localStorage.getItem('veille.queue');
    setItems(raw ? (JSON.parse(raw) as Item[]) : []);
  }, []);

  return (
    <main className="desk">
      <Link className="mark" href="/agent">Veille</Link>
      <h1>File du téléphone</h1>
      {items.length === 0 ? (
        <p className="note">Rien en attente. Un envoi refusé reste ici, il n’est pas effacé.</p>
      ) : (
        <ul className="lines">
          {items.map((item) => (
            <li key={item.actionId}>
              <span style={{ display: 'grid', padding: '14px 0', borderBottom: '1px solid rgba(243,239,230,0.2)' }}>
                <span>{item.label}</span>
                <small className="quiet">{item.at} · enregistré sur ce téléphone</small>
              </span>
            </li>
          ))}
        </ul>
      )}
    </main>
  );
}
