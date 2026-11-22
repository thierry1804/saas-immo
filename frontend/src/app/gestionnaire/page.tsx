import { Desk } from '@/components/desk';

export default function ManagerHome() {
  return (
    <Desk
      title="Journée ORG-A"
      lines={[
        { href: '/gestionnaire/patrimoine', label: 'Logements', detail: 'H1 EUR · H2 MGA' },
        { href: '/gestionnaire/caisse', label: 'Caisse', detail: 'Facture et paiement simulé', tone: 'hot' },
        { href: '/gestionnaire/lots', label: 'Lots sortants', detail: 'G1 prépare, G2 approuve' },
        { href: '/agent/mission', label: 'Reçu H2 à relire', detail: 'Pas encore reçu', tone: 'alert' },
        { href: '/gestionnaire/login', label: 'Changer de compte', detail: 'G1 ou G2' },
      ]}
    />
  );
}
