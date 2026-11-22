'use client';

import { useState } from 'react';
import counterGrain from '../../assets/plates/counter-grain.png';
import slipGrain from '../../assets/plates/slip-grain.png';
import tear from '../../assets/plates/tear.png';
import { apiFetch } from '@/lib/api';
import { MISSION_H2 } from '@/lib/demo';
import { readSession } from '@/lib/session';

export function MissionReceipt() {
  const [sent, setSent] = useState(false);
  const [pending, setPending] = useState(false);
  const [note, setNote] = useState('Pas encore reçu par Veille');

  async function send() {
    setPending(true);
    const session = readSession();
    const actionId = crypto.randomUUID();
    try {
      if (!session) {
        throw new Error('Connecte l’agent avant d’envoyer le dossier.');
      }
      await apiFetch('/agent/sync/actions', {
        method: 'POST',
        body: JSON.stringify({
          device_id: '11111111-1111-4111-8111-ffffffff0002',
          actions: [
            {
              action_id: actionId,
              type: 'visit.finalize',
              mission_id: MISSION_H2,
              payload: { photos: 6, gps_flag: 'out_of_radius' },
            },
          ],
        }),
      });
      setSent(true);
      setNote('Reçu complet par Veille');
    } catch (error) {
      const queued = {
        actionId,
        at: new Date().toISOString(),
        label: 'Dossier H2',
      };
      const raw = window.localStorage.getItem('veille.queue');
      const queue = raw ? (JSON.parse(raw) as unknown[]) : [];
      queue.push(queued);
      window.localStorage.setItem('veille.queue', JSON.stringify(queue));
      setNote(
        error instanceof Error
          ? `${error.message} Le dossier reste sur ce téléphone.`
          : 'Le dossier reste sur ce téléphone.',
      );
    } finally {
      setPending(false);
    }
  }

  return (
    <main className="phone">
      <div className="plates" aria-hidden="true">
        <img className="r-counter-grain" src={counterGrain.src} alt="" />
        <img className="r-slip-grain" src={slipGrain.src} alt="" />
      </div>
      <div className="receipt">
        <p className="mark">Veille</p>
        <p className="meta">ORG-A · synthétique</p>
        <p className="meta">Mission H2 · contrôle standard</p>
        <p className="tariff">
          <span>Tarif figé 50 000 MGA</span>
          <span className="quiet">EUR</span>
        </p>
        <div className="slip-block">
          <p>
            Photos <span className="red">6/6</span>
          </p>
          <p>
            GPS hors rayon — <span className="red">drapeau</span>
          </p>
          <p>Enregistré sur ce téléphone</p>
          <p className={sent ? '' : 'red'}>{note}</p>
        </div>
        <img className="r-tear" src={tear.src} alt="" />
        <div className="band" aria-hidden="true">
          <span style={{ ['--fill' as string]: sent ? 1 : 0.68 }} />
        </div>
        <button className="action" type="button" onClick={send} disabled={pending || sent}>
          {sent ? 'Dossier reçu' : pending ? 'Envoi en cours' : 'Envoyer le dossier'}
        </button>
      </div>
    </main>
  );
}
