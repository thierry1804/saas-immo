import Link from 'next/link';
import { H1, H2 } from '@/lib/demo';

const homes = [
  { id: H2, name: 'H2', tariff: '50 000 MGA', owner: 'P1' },
  { id: H1, name: 'H1', tariff: '20 EUR', owner: 'P1' },
];

export default function PatrimoinePage() {
  return (
    <main className="desk">
      <Link className="mark" href="/gestionnaire">Veille</Link>
      <h1>Logements</h1>
      <p className="note">Les deux monnaies restent sur des lignes séparées.</p>
      <ul className="lines">
        {homes.map((home) => (
          <li key={home.id}>
            <span style={{ display: 'grid', gridTemplateColumns: '1fr auto', padding: '14px 0', borderBottom: '1px solid rgba(243,239,230,0.2)' }}>
              <span>{home.name} · {home.owner}</span>
              <small className="hot">{home.tariff}</small>
            </span>
          </li>
        ))}
      </ul>
    </main>
  );
}
