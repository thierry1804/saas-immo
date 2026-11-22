import { Desk } from '@/components/desk';

export default function AgentHome() {
  return (
    <Desk
      title="Missions de A1"
      lines={[
        { href: '/agent/mission', label: 'H2 · contrôle standard', detail: '50 000 MGA', tone: 'hot' },
        { href: '/agent/checklist', label: 'Checklist figée', detail: '10 points' },
        { href: '/agent/urgence', label: 'Urgence', detail: 'Avant les photos', tone: 'alert' },
        { href: '/agent/file', label: 'File du téléphone', detail: 'Pas encore reçu' },
        { href: '/agent/login', label: 'Connexion agent', detail: 'OTP' },
      ]}
    />
  );
}
