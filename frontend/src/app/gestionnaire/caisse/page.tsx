'use client';

import { useState } from 'react';
import Link from 'next/link';
import { apiFetch } from '@/lib/api';
import { H2 } from '@/lib/demo';

export default function CaissePage() {
  const [log, setLog] = useState<string[]>([]);
  const [busy, setBusy] = useState(false);

  function push(line: string) {
    setLog((current) => [line, ...current]);
  }

  async function run() {
    setBusy(true);
    try {
      const draft = await apiFetch<{ id: string; currency: string; total_minor: number }>('/invoices/drafts', {
        method: 'POST',
        body: JSON.stringify({ property_id: H2, currency: 'MGA', amount_minor: 50000 }),
      });
      push(`Brouillon ${draft.id} · ${draft.total_minor} ${draft.currency}`);
      const issued = await apiFetch<{ id: string; number?: string }>(`/invoices/${draft.id}/issue`, { method: 'POST' });
      push(`Facture émise ${issued.number ?? issued.id}`);
      const attempt = await apiFetch<{ attempt_id: string; status: string }>(`/invoices/${draft.id}/payment-attempts`, {
        method: 'POST',
        body: JSON.stringify({
          provider: 'mvola',
          method: 'mobile_money',
          currency: draft.currency,
          amount_minor: draft.total_minor,
        }),
      });
      push(`Tentative ${attempt.attempt_id} · ${attempt.status}. Rien n’est soldé.`);
      const back = await apiFetch<{ attempt_status?: string; message?: string }>(
        `/payment-attempts/${attempt.attempt_id}/browser-return`,
        { method: 'POST' },
      );
      push(back.message ?? `Retour navigateur : ${back.attempt_status ?? 'pending'}. La facture n’est pas soldée.`);
    } catch (error) {
      push(error instanceof Error ? error.message : 'Caisse interrompue.');
    } finally {
      setBusy(false);
    }
  }

  return (
    <main className="desk">
      <Link className="mark" href="/gestionnaire">Veille</Link>
      <h1>Caisse H2</h1>
      <p className="note">Tarif figé 50 000 MGA. Le retour du navigateur ne confirme pas le paiement.</p>
      <button className="action" type="button" onClick={run} disabled={busy}>
        {busy ? 'En cours' : 'Émettre et tenter MVola'}
      </button>
      <ul className="lines">
        {log.map((line) => (
          <li key={line}>
            <span style={{ display: 'block', padding: '12px 0', borderBottom: '1px solid rgba(243,239,230,0.2)' }}>{line}</span>
          </li>
        ))}
      </ul>
    </main>
  );
}
