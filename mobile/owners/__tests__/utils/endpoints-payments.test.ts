import { ENDPOINTS } from '@/api/endpoints';

describe('ENDPOINTS payment routes', () => {
  it('a tous les endpoints paiement requis', () => {
    expect(ENDPOINTS.payments.initiate).toBe('/payments/initiate_payment');
    expect(ENDPOINTS.payments.verify).toBe('/payments/verify_payment');
    expect(ENDPOINTS.payments.cancel).toBe('/payments/cancel_payment');
    expect(ENDPOINTS.payments.methods).toBe('/payments/methods');
    expect(ENDPOINTS.payments.history).toBe('/payments/history');
    expect(ENDPOINTS.payments.export).toBe('/payments/export');
  });

  it('builders payment avec encodage URI', () => {
    expect(ENDPOINTS.payments.publicStatus('KH ABC/123')).toBe(
      '/payments/KH%20ABC%2F123/public-status',
    );
    expect(ENDPOINTS.payments.receipt('p 1')).toBe('/payments/p%201/receipt');
    expect(ENDPOINTS.payments.refundRequest('p1')).toBe('/payments/p1/refund-request');
  });

  it('endpoints Stripe pour cartes sauvegardées', () => {
    expect(ENDPOINTS.payments.stripeMethods).toBe('/payments/stripe/payment-methods');
    expect(ENDPOINTS.payments.stripeMethod('pm_123')).toBe(
      '/payments/stripe/payment-methods/pm_123',
    );
    expect(ENDPOINTS.payments.stripeSetDefault('pm_123')).toBe(
      '/payments/stripe/payment-methods/pm_123/set-default',
    );
    expect(ENDPOINTS.payments.stripeSetupIntent).toBe('/payments/stripe/setup-intent');
  });

  it('endpoints credits achat + verify', () => {
    expect(ENDPOINTS.credits.balance).toBe('/credits/balance');
    expect(ENDPOINTS.credits.packages).toBe('/credits/packages');
    expect(ENDPOINTS.credits.purchase('pkg_1')).toBe('/credits/purchase/pkg_1');
    expect(ENDPOINTS.credits.verifyPurchase).toBe('/credits/verify-purchase');
  });

  it('subscriptions inclut upgrade/downgrade/renew', () => {
    expect(ENDPOINTS.subscriptions.subscribe).toBe('/subscriptions/subscribe');
    expect(ENDPOINTS.subscriptions.renew).toBe('/subscriptions/renew');
    expect(ENDPOINTS.subscriptions.upgrade).toBe('/subscriptions/upgrade');
    expect(ENDPOINTS.subscriptions.downgrade).toBe('/subscriptions/downgrade');
    expect(ENDPOINTS.subscriptions.cancel).toBe('/subscriptions/cancel');
  });
});
