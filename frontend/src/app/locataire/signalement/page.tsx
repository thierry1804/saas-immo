'use client';

import { useState } from 'react';
import Link from 'next/link';
import { apiFetch } from '@/lib/api';
import { H2 } from '@/lib/demo';

export default function ReportPage() {
  const [note, setNote] = useState('Fuite sous l’évier');
  const [result, setResult] = useState('');

  async function submit(event: React.FormEvent) {
    event.preventDefault();
    try {
      const data = await apiFetch<{ id: string }>('/anomalies', {
        method: 'POST',
        body: JSON.stringify({
          property_id: H2,
          source_type: 'renter',
          category: 'fuite',
          severity: 'normal',
          note,
        }),
      });
      setResult(`Signalement ${data.id} enregistré.`);
    } catch (error) {
      setResult(error instanceof Error ? error.message : 'Signalement refusé.');
    }
  }

  return (
    <main className="desk">
      <Link className="mark" href="/locataire">Veille</Link>
      <h1>Signalement</h1>
      <form className="stack" onSubmit={submit}>
        <label>
          Ce qui ne va pas
          <textarea value={note} onChange={(event) => setNote(event.target.value)} rows={4} />
        </label>
        <button className="action" type="submit">Envoyer le signalement</button>
      </form>
      {result ? <p>{result}</p> : null}
    </main>
  );
}
