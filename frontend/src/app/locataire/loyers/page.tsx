import Link from 'next/link';

export default function RentsPage() {
  return (
    <main className="desk">
      <Link className="mark" href="/locataire">Veille</Link>
      <h1>Compte locatif</h1>
      <ul className="lines">
        <li><span style={{ display: 'grid', gridTemplateColumns: '1fr auto', padding: '14px 0', borderBottom: '1px solid rgba(243,239,230,0.2)' }}><span>Loyer</span><small>1 000 000 MGA</small></span></li>
        <li><span style={{ display: 'grid', gridTemplateColumns: '1fr auto', padding: '14px 0', borderBottom: '1px solid rgba(243,239,230,0.2)' }}><span>Charges</span><small>100 000 MGA</small></span></li>
        <li><span style={{ display: 'grid', gridTemplateColumns: '1fr auto', padding: '14px 0', borderBottom: '1px solid rgba(243,239,230,0.2)' }}><span>Dépôt</span><small className="quiet">500 000 MGA · non reversable</small></span></li>
      </ul>
    </main>
  );
}
