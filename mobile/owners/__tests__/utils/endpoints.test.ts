import { ENDPOINTS } from '@/api/endpoints';

describe('ENDPOINTS owner', () => {
  it('routes auth correctes', () => {
    expect(ENDPOINTS.auth.login).toBe('/auth/login');
    expect(ENDPOINTS.auth.register).toBe('/auth/registerAgent');
    expect(ENDPOINTS.auth.me).toBe('/auth/me');
  });

  it('routes ads owner-scoped', () => {
    expect(ENDPOINTS.my.ads).toBe('/my/ads');
    expect(ENDPOINTS.my.stats).toBe('/my/stats');
    expect(ENDPOINTS.my.adsAnalytics).toBe('/my/ads/analytics');
  });

  it('URI-encode les params dynamiques', () => {
    expect(ENDPOINTS.ads.detail('a b/c')).toBe('/ads/a%20b%2Fc');
    expect(ENDPOINTS.my.expenses('id with space')).toBe('/my/ads/id%20with%20space/expenses');
  });

  it('chat endpoints', () => {
    expect(ENDPOINTS.chat.conversations).toBe('/conversations');
    expect(ENDPOINTS.chat.messages('abc')).toBe('/conversations/abc/messages');
    expect(ENDPOINTS.chat.reaction('m1')).toBe('/messages/m1/reactions');
  });

  it('availability endpoints', () => {
    expect(ENDPOINTS.availability.list('ad1')).toBe('/ads/ad1/availability');
    expect(ENDPOINTS.availability.delete('ad1', 's1')).toBe('/ads/ad1/availability/s1');
  });

  it('refunds + market + trust + team', () => {
    expect(ENDPOINTS.refunds.list).toBe('/payments/refunds');
    expect(ENDPOINTS.refunds.request('p1')).toBe('/payments/p1/refund-request');
    expect(ENDPOINTS.market.estimate).toBe('/rent-estimate');
    expect(ENDPOINTS.trust.score).toBe('/my/trust-score');
    expect(ENDPOINTS.team.list).toBe('/my/team');
  });

  it('toutes les paths HTTP commencent par /', () => {
    // Le groupe `echo.*` est un naming de channel websocket (`private-conversation.x`)
    // pas une path HTTP — on l'exclut volontairement de l'assertion.
    const walk = (obj: Record<string, unknown>, parentKey = '') => {
      for (const [k, v] of Object.entries(obj)) {
        if (parentKey === 'echo' && k === 'conversationChannel') continue;
        if (typeof v === 'string') {
          expect(v.startsWith('/')).toBe(true);
        } else if (typeof v === 'function') {
          const result = (v as (a: string, b?: string) => string)('x', 'y');
          expect(result.startsWith('/')).toBe(true);
        } else if (typeof v === 'object' && v !== null) {
          walk(v as Record<string, unknown>, k);
        }
      }
    };
    walk(ENDPOINTS as unknown as Record<string, unknown>);
  });
});
