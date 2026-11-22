import Link from 'next/link';

const roles = [
  { href: '/agent', label: 'Agent', detail: 'Mission du jour, hors ligne' },
  { href: '/gestionnaire/login', label: 'Gestionnaire', detail: 'Patrimoine, revue, caisse' },
  { href: '/proprietaire', label: 'Propriétaire', detail: 'Visites publiées et relevés' },
  { href: '/locataire', label: 'Locataire', detail: 'Bail, loyers, signalement' },
  { href: '/apporteur', label: 'Apporteur', detail: 'Annonces et commissions' },
];

export default function HomePage() {
  return (
    <main className="desk">
      <p className="mark">Veille</p>
      <h1>ORG-A · synthétique</h1>
      <ul className="lines">
        {roles.map((role) => (
          <li key={role.href}>
            <Link href={role.href}>
              <span>{role.label}</span>
              <small>{role.detail}</small>
            </Link>
          </li>
        ))}
      </ul>
    </main>
  );
}
