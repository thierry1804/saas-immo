'use client';

import { useState } from 'react';
import Link from 'next/link';
import { apiFetch } from '@/lib/api';
import { BENEFICIARY_P1 } from '@/lib/demo';

export default function LotsPage() {
  const [reason, setReason] = useState('Reversement loyer H2, jeu synthétique');
  const [batchId, setBatchId] = useState('');
  const [message, setMessage] = useState('');

  async function prepare(event: React.FormEvent) {
    event.preventDefault();
    setMessage('');
    try {
      const data = await apiFetch<{ id: string; status: string }>('/payout-batches', {
        method: 'POST',
        body: JSON.stringify({
          beneficiary_id: BENEFICIARY_P1,
          currency: 'MGA',
          amount_minor: 100000,
          reason,
        }),
      });
      setBatchId(data.id);
      setMessage(`Lot ${data.status}. G1 ne peut pas l’approuver.`);
    } catch (error) {
      setMessage(error instanceof Error ? error.message : 'Préparation refusée.');
    }
  }

  async function approve() {
    setMessage('');
    try {
      const data = await apiFetch<{ status: string }>(`/payout-batches/${batchId}/approve`, {
        method: 'POST',
        body: JSON.stringify({ reason: 'Approuvé par le second compte' }),
      });
      setMessage(`Lot ${data.status}.`);
    } catch (error) {
      setMessage(error instanceof Error ? error.message : 'Approbation refusée.');
    }
  }

  return (
    <main className="desk">
      <Link className="mark" href="/gestionnaire">Veille</Link>
      <h1>Lot sortant</h1>
      <p className="note">Prépare avec G1, puis reconnecte G2 pour approuver.</p>
      <form className="stack" onSubmit={prepare}>
        <label>
          Motif
          <input value={reason} onChange={(event) => setReason(event.target.value)} />
        </label>
        <button className="action" type="submit">Préparer 100 000 MGA</button>
      </form>
      {batchId ? (
        <button className="action" type="button" onClick={approve}>Approuver le lot</button>
      ) : null}
      {message ? <p>{message}</p> : null}
    </main>
  );
}
