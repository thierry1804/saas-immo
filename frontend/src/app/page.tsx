import { API_BASE } from '@/lib/api';
import styles from './page.module.css';

export default function Home() {
  return (
    <div className={styles.page}>
      <main className={styles.main}>
        <h1>Veille Agent</h1>
        <p>
          Squelette PWA L00. Les parcours hors ligne arrivent en L03. API cible :{' '}
          <code>{API_BASE}</code>
        </p>
        <ul>
          <li>Manifest + service worker placeholder</li>
          <li>Client API stub dans <code>src/lib/api.ts</code></li>
          <li>
            Maquettes HTML : <code>docs/l00/ui/</code>
          </li>
        </ul>
      </main>
    </div>
  );
}
