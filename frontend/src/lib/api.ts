import { readSession } from './session';

const API_BASE =
  process.env.NEXT_PUBLIC_API_BASE_URL ?? 'http://127.0.0.1:8000/api/v1';

export type ApiError = {
  code: string;
  message: string;
  retryable: boolean;
  request_id: string;
};

type Envelope<T> = { data: T; meta?: { request_id?: string } };

export async function apiFetch<T>(
  path: string,
  init: RequestInit = {},
  extraHeaders: Record<string, string> = {},
): Promise<T> {
  const headers = new Headers(init.headers);
  headers.set('Accept', 'application/json');
  if (init.body && !headers.has('Content-Type')) {
    headers.set('Content-Type', 'application/json');
  }
  const session = readSession();
  if (session && !headers.has('Authorization')) {
    headers.set('Authorization', `Bearer ${session.accessToken}`);
    headers.set('X-Organization-Id', session.organizationId);
  }
  const method = (init.method ?? 'GET').toUpperCase();
  if (method !== 'GET' && method !== 'HEAD' && !headers.has('Idempotency-Key')) {
    headers.set('Idempotency-Key', crypto.randomUUID());
  }
  for (const [key, value] of Object.entries(extraHeaders)) {
    headers.set(key, value);
  }

  const response = await fetch(`${API_BASE}${path}`, {
    ...init,
    headers,
  });

  const payload = (await response.json().catch(() => null)) as
    | Envelope<T>
    | { error?: ApiError }
    | null;

  if (!response.ok) {
    const error = payload && 'error' in payload ? payload.error : undefined;
    throw Object.assign(new Error(error?.message ?? response.statusText), {
      status: response.status,
      error,
    });
  }

  if (payload && 'data' in payload) return payload.data;
  return payload as T;
}

export { API_BASE };
