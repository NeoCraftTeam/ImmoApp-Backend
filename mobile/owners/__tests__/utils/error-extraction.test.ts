import { extractApiErrorMessage } from '@/api/extract-error';
import axios, { AxiosError, type AxiosResponse } from 'axios';

function makeAxiosError(response?: Partial<AxiosResponse>, code?: string): AxiosError {
  const err = new AxiosError('boom', code, undefined, undefined, response as AxiosResponse);
  return err;
}

describe('extractApiErrorMessage', () => {
  it('renvoie le 1er field error si présent', () => {
    const err = makeAxiosError({
      status: 422,
      data: { errors: { email: ["L'email est invalide."] } },
    } as Partial<AxiosResponse>);
    expect(extractApiErrorMessage(err)).toBe("L'email est invalide.");
  });

  it('fallback message string si pas de field errors', () => {
    const err = makeAxiosError({
      status: 422,
      data: { message: 'Données invalides.' },
    } as Partial<AxiosResponse>);
    expect(extractApiErrorMessage(err)).toBe('Données invalides.');
  });

  it('message 401 par défaut', () => {
    const err = makeAxiosError({ status: 401, data: {} } as Partial<AxiosResponse>);
    expect(extractApiErrorMessage(err)).toMatch(/incorrects/i);
  });

  it('message 403 par défaut', () => {
    const err = makeAxiosError({ status: 403, data: {} } as Partial<AxiosResponse>);
    expect(extractApiErrorMessage(err)).toMatch(/autorisée/i);
  });

  it('détecte ECONNABORTED', () => {
    const err = makeAxiosError(undefined, 'ECONNABORTED');
    expect(extractApiErrorMessage(err)).toMatch(/délai|attente/i);
  });

  it('réseau absent', () => {
    const err = makeAxiosError(undefined, 'ERR_NETWORK');
    expect(extractApiErrorMessage(err)).toMatch(/connexion|réseau/i);
  });

  it('Error non-Axios', () => {
    expect(extractApiErrorMessage(new Error('oops'))).toBe('oops');
  });

  it('valeur inconnue → fallback générique', () => {
    expect(extractApiErrorMessage({})).toMatch(/erreur|réessayez/i);
    expect(extractApiErrorMessage(null)).toMatch(/erreur|réessayez/i);
  });

  // sanity: isAxiosError détecte bien notre fixture
  it('axios.isAxiosError détecte la fixture', () => {
    const err = makeAxiosError({ status: 500, data: { message: 'oops' } } as Partial<AxiosResponse>);
    expect(axios.isAxiosError(err)).toBe(true);
  });
});
