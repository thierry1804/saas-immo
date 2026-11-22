import type { Metadata, Viewport } from 'next';
import './globals.css';
import { ServiceWorkerRegister } from './sw-register';

export const metadata: Metadata = {
  title: 'Veille Agent',
  description: 'PWA agent terrain — synchronisation hors ligne',
  manifest: '/manifest.webmanifest',
  applicationName: 'Veille',
};

export const viewport: Viewport = {
  themeColor: '#1c1c1e',
  width: 'device-width',
  initialScale: 1,
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="fr">
      <body>
        <ServiceWorkerRegister />
        {children}
      </body>
    </html>
  );
}
