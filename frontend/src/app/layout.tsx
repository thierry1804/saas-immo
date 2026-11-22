import type { Metadata, Viewport } from 'next';
import { Barlow_Condensed, Lobster_Two } from 'next/font/google';
import './globals.css';
import { ServiceWorkerRegister } from './sw-register';

const body = Barlow_Condensed({
  weight: ['400', '500', '600'],
  subsets: ['latin'],
  variable: '--font-body',
});

const mark = Lobster_Two({
  weight: '400',
  subsets: ['latin'],
  variable: '--font-mark',
});

export const metadata: Metadata = {
  title: 'Veille',
  description: 'Conciergerie : visites, constats, loyers et reversements.',
  manifest: '/manifest.webmanifest',
  applicationName: 'Veille',
};

export const viewport: Viewport = {
  themeColor: '#1b1b19',
  width: 'device-width',
  initialScale: 1,
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="fr" className={`${body.variable} ${mark.variable}`}>
      <body className={body.className}>
        <ServiceWorkerRegister />
        {children}
      </body>
    </html>
  );
}
