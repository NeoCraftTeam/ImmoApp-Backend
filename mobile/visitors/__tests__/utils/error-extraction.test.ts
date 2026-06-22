import { AxiosError, AxiosHeaders } from 'axios';

import { extractApiErrorMessage } from '@/api/client';

function makeAxiosError(opts: {
  status?: number;
  data?: unknown;
  code?: string;
}): AxiosError {
  const err = new AxiosError('boom');
  (err as AxiosError & { response?: unknown }).response = opts.status
    ? {
        status: opts.status,
        statusText: '',
        data: opts.data,
        headers: new AxiosHeaders(),
        config: { headers: new AxiosHeaders() } as never,
      }
    : undefined;
  if (opts.code) {
    err.code = opts.code;
  }
  return err;
}

describe('extractApiErrorMessage', () => {
  it('returns backend message when present', () => {
    const err = makeAxiosError({ status: 422, data: { message: 'Email invalide' } });
    expect(extractApiErrorMessage(err)).toBe('Email invalide');
  });

  it('returns "Identifiants incorrects." on 401 with no message', () => {
    const err = makeAxiosError({ status: 401, data: {} });
    expect(extractApiErrorMessage(err)).toBe('Identifiants incorrects.');
  });

  it('returns "Données invalides." on 422 with no message', () => {
    const err = makeAxiosError({ status: 422, data: {} });
    expect(extractApiErrorMessage(err)).toBe('Données invalides.');
  });

  it('flags timeout from ECONNABORTED', () => {
    const err = makeAxiosError({ code: 'ECONNABORTED' });
    expect(extractApiErrorMessage(err)).toMatch(/Délai d/);
  });

  it('returns a network error when no response', () => {
    const err = makeAxiosError({ code: 'ERR_NETWORK' });
    expect(extractApiErrorMessage(err)).toMatch(/Connexion au serveur impossible/);
  });

  it('falls back to plain Error.message', () => {
    expect(extractApiErrorMessage(new Error('Whoops'))).toBe('Whoops');
  });

  it('returns generic fallback for unknown shapes', () => {
    expect(extractApiErrorMessage({ weird: true })).toBe(
      'Une erreur est survenue. Réessayez plus tard.',
    );
  });
});
