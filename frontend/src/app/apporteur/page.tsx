import { Desk } from '@/components/desk';

export default function ReferrerHome() {
  return (
    <Desk
      title="Apporteur F1"
      lines={[
        { href: '/apporteur/login', label: 'Connexion', detail: '+261330000001' },
        { href: '/apporteur/commissions', label: 'Commissions', detail: 'Montant figé à l’acquisition' },
      ]}
    />
  );
}
