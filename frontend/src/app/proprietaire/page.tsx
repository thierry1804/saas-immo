import { Desk } from '@/components/desk';

export default function OwnerHome() {
  return (
    <Desk
      title="Logements de P1"
      lines={[
        { href: '/agent/mission', label: 'H2 · visite du jour', detail: 'Rapport en préparation' },
        { href: '/proprietaire/login', label: 'Ouvrir la session', detail: 'p1@org-a.test' },
        { href: '/gestionnaire/patrimoine', label: 'H1 et H2', detail: 'EUR et MGA séparés' },
      ]}
    />
  );
}
