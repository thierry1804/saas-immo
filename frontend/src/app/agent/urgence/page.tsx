'use client';

import { useState } from 'react';
import Link from 'next/link';

export default function UrgencePage() {
  const [sent, setSent] = useState(false);
  const [text, setText] = useState('');

  return (
    <main className="desk">
      <Link className="mark" href="/agent">Veille</Link>
      <h1>Urgence</h1>
      <p className={sent ? 'hot' : 'alert'}>
        {sent ? 'Alerte mise en file.' : 'Alerte non transmise. Appelle le contact avant d’attendre le réseau.'}
      </p>
      <p><a href="tel:+261320000099">Appeler le contact d’urgence</a></p>
      <form
        className="stack"
        onSubmit={(event) => {
          event.preventDefault();
          if (text.trim().length < 8) return;
          setSent(true);
        }}
      >
        <label>
          Danger
          <textarea value={text} onChange={(event) => setText(event.target.value)} rows={4} required />
        </label>
        <button className="action" type="submit">Déposer l’alerte</button>
      </form>
    </main>
  );
}
