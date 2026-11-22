import { Desk } from '@/components/desk';

export default function RenterHome() {
  return (
    <Desk
      title="Bail B1 · H2"
      lines={[
        { href: '/locataire/login', label: 'Ouvrir la session', detail: 'r1@org-a.test' },
        { href: '/locataire/loyers', label: 'Loyer', detail: '1 000 000 MGA + 100 000 charges' },
        { href: '/locataire/signalement', label: 'Signaler', detail: 'Sans ouvrir le dossier complet' },
      ]}
    />
  );
}
