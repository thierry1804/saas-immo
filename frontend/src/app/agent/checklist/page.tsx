import Link from 'next/link';

const points = [
  'Porte d’entrée',
  'Serrure',
  'Compteur eau',
  'Compteur électricité',
  'Cuisine',
  'Salle d’eau',
  'Fenêtres',
  'Sol séjour',
  'Dégât visible',
  'Clés rendues',
];

export default function ChecklistPage() {
  return (
    <main className="desk">
      <Link className="mark" href="/agent">Veille</Link>
      <h1>Checklist H2</h1>
      <p className="note">Version figée à la création de la mission. Six photos minimum.</p>
      <ul className="lines">
        {points.map((point, index) => (
          <li key={point}>
            <span style={{ display: 'grid', gridTemplateColumns: '1fr auto', padding: '14px 0', borderBottom: '1px solid rgba(243,239,230,0.2)' }}>
              <span>{index + 1}. {point}</span>
              <small className="quiet">{index === 8 ? 'Photo obligatoire' : 'Conforme'}</small>
            </span>
          </li>
        ))}
      </ul>
      <p><Link href="/agent/mission">Retour au reçu</Link></p>
    </main>
  );
}
