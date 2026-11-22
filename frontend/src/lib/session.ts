export type Session = {
  accessToken: string;
  refreshToken: string | null;
  organizationId: string;
  label: string;
};

const KEY = 'veille.session';

export function readSession(): Session | null {
  if (typeof window === 'undefined') return null;
  const raw = window.sessionStorage.getItem(KEY);
  if (!raw) return null;
  try {
    return JSON.parse(raw) as Session;
  } catch {
    return null;
  }
}

export function writeSession(session: Session) {
  window.sessionStorage.setItem(KEY, JSON.stringify(session));
}

export function clearSession() {
  window.sessionStorage.removeItem(KEY);
}
