const API_BASE =
  process.env.NEXT_PUBLIC_API_BASE_URL ?? 'http://127.0.0.1:8000/api/v1';

export type ApiError = {
  code: string;
  message: string;
  retryable: boolean;
  request_id: string;
};

export async function apiFetch<T>(
  path: string,
  init: RequestInit = {},
): Promise<T> {
  const headers = new Headers(init.headers);
  headers.set('Accept', 'application/json');
  if (init.body && !headers.has('Content-Type')) {
    headers.set('Content-Type', 'application/json');
  }

  const response = await fetch(`${API_BASE}${path}`, {
    ...init,
    headers,
  });

  if (!response.ok) {
    const payload = (await response.json().catch(() => null)) as
      | { error?: ApiError }
      | null;
    throw Object.assign(new Error(payload?.error?.message ?? response.statusText), {
      status: response.status,
      error: payload?.error,
    });
  }

  return response.json() as Promise<T>;
}

export { API_BASE };
