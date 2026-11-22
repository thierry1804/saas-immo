'use client';

import { useState } from 'react';
import Link from 'next/link';
import { apiFetch } from '@/lib/api';
import { DEMO, ORG_A } from '@/lib/demo';
import { writeSession } from '@/lib/session';

export function PasswordLogin({
  title,
  identifier,
  next,
}: {
  title: string;
  identifier: string;
  next: string;
}) {
  const [email, setEmail] = useState(identifier);
  const [password, setPassword] = useState(DEMO.password);
  const [code, setCode] = useState(DEMO.otp);
  const [mfaToken, setMfaToken] = useState<string | null>(null);
  const [error, setError] = useState('');

  async function submitPassword(event: React.FormEvent) {
    event.preventDefault();
    setError('');
    try {
      const data = await apiFetch<{ mfa_required?: boolean; mfa_token?: string; access_token?: string; refresh_token?: string | null; memberships?: { organization_id: string }[] }>(
        '/auth/password',
        { method: 'POST', body: JSON.stringify({ identifier: email, password }) },
      );
      if (data.mfa_token) {
        setMfaToken(data.mfa_token);
        return;
      }
      if (data.access_token) {
        writeSession({
          accessToken: data.access_token,
          refreshToken: data.refresh_token ?? null,
          organizationId: data.memberships?.[0]?.organization_id ?? ORG_A,
          label: email,
        });
        window.location.href = next;
      }
    } catch (caught) {
      setError(caught instanceof Error ? caught.message : 'Connexion refusée.');
    }
  }

  async function submitMfa(event: React.FormEvent) {
    event.preventDefault();
    setError('');
    try {
      const data = await apiFetch<{ access_token: string; refresh_token?: string | null; memberships?: { organization_id: string }[] }>(
        '/auth/mfa',
        { method: 'POST', body: JSON.stringify({ mfa_token: mfaToken, code }) },
      );
      writeSession({
        accessToken: data.access_token,
        refreshToken: data.refresh_token ?? null,
        organizationId: data.memberships?.[0]?.organization_id ?? ORG_A,
        label: email,
      });
      window.location.href = next;
    } catch (caught) {
      setError(caught instanceof Error ? caught.message : 'Code refusé.');
    }
  }

  return (
    <main className="desk">
      <Link className="mark" href="/">Veille</Link>
      <h1>{title}</h1>
      <p className="note">Jeu synthétique. Mot de passe {DEMO.password}, code {DEMO.otp}.</p>
      {mfaToken ? (
        <form className="stack" onSubmit={submitMfa}>
          <label>
            Code
            <input value={code} onChange={(event) => setCode(event.target.value)} inputMode="numeric" autoComplete="one-time-code" />
          </label>
          <button className="action" type="submit">Valider le code</button>
        </form>
      ) : (
        <form className="stack" onSubmit={submitPassword}>
          <label>
            E-mail
            <input value={email} onChange={(event) => setEmail(event.target.value)} autoComplete="username" />
          </label>
          <label>
            Mot de passe
            <input type="password" value={password} onChange={(event) => setPassword(event.target.value)} autoComplete="current-password" />
          </label>
          <button className="action" type="submit">Continuer</button>
        </form>
      )}
      {error ? <p className="error">{error}</p> : null}
    </main>
  );
}

export function OtpLogin({
  title,
  destination,
  next,
}: {
  title: string;
  destination: string;
  next: string;
}) {
  const [phone, setPhone] = useState(destination);
  const [challenge, setChallenge] = useState<string | null>(null);
  const [code, setCode] = useState(DEMO.otp);
  const [error, setError] = useState('');

  async function request(event: React.FormEvent) {
    event.preventDefault();
    setError('');
    try {
      const data = await apiFetch<{ challenge_id: string }>('/auth/otp/request', {
        method: 'POST',
        body: JSON.stringify({ channel: 'sms', destination: phone, intent: 'login' }),
      });
      setChallenge(data.challenge_id);
    } catch (caught) {
      setError(caught instanceof Error ? caught.message : 'OTP refusé.');
    }
  }

  async function verify(event: React.FormEvent) {
    event.preventDefault();
    setError('');
    try {
      const data = await apiFetch<{ access_token: string; refresh_token?: string | null; memberships?: { organization_id: string }[] }>(
        '/auth/otp/verify',
        { method: 'POST', body: JSON.stringify({ challenge_id: challenge, code }) },
      );
      writeSession({
        accessToken: data.access_token,
        refreshToken: data.refresh_token ?? null,
        organizationId: data.memberships?.[0]?.organization_id ?? ORG_A,
        label: phone,
      });
      window.location.href = next;
    } catch (caught) {
      setError(caught instanceof Error ? caught.message : 'Code refusé.');
    }
  }

  return (
    <main className="desk">
      <Link className="mark" href="/">Veille</Link>
      <h1>{title}</h1>
      <p className="note">Code de développement {DEMO.otp}.</p>
      {challenge ? (
        <form className="stack" onSubmit={verify}>
          <label>
            Code
            <input value={code} onChange={(event) => setCode(event.target.value)} inputMode="numeric" />
          </label>
          <button className="action" type="submit">Ouvrir la session</button>
        </form>
      ) : (
        <form className="stack" onSubmit={request}>
          <label>
            Téléphone
            <input value={phone} onChange={(event) => setPhone(event.target.value)} autoComplete="tel" />
          </label>
          <button className="action" type="submit">Recevoir le code</button>
        </form>
      )}
      {error ? <p className="error">{error}</p> : null}
    </main>
  );
}
