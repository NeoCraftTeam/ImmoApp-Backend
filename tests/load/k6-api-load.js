/**
 * k6 Load Testing Script – KeyHome API
 *
 * Usage:
 *   brew install k6            # install k6 (macOS)
 *   k6 run tests/load/k6-api-load.js                          # default (10 VUs, 30s)
 *   k6 run --vus 50 --duration 60s tests/load/k6-api-load.js  # custom
 *
 * Environment variables:
 *   BASE_URL  – API base URL (default: http://localhost:8000)
 *   API_TOKEN – Bearer token for authenticated endpoints
 */

import http from 'k6/http';
import { check, group, sleep } from 'k6';
import { Rate, Trend } from 'k6/metrics';

// ─── Configuration ───────────────────────────────────────────────────────────
const BASE_URL = __ENV.BASE_URL || 'http://localhost:8000';
const TOKEN    = __ENV.API_TOKEN || '';

const authHeaders = TOKEN
  ? { headers: { Authorization: `Bearer ${TOKEN}`, Accept: 'application/json' } }
  : { headers: { Accept: 'application/json' } };

// ─── Custom metrics ──────────────────────────────────────────────────────────
const errorRate    = new Rate('errors');
const adListTrend  = new Trend('ad_list_duration');
const adShowTrend  = new Trend('ad_show_duration');
const searchTrend  = new Trend('search_duration');
const loginTrend   = new Trend('login_duration');

// ─── Scenarios ───────────────────────────────────────────────────────────────
export const options = {
  scenarios: {
    // Smoke test – low load to verify everything works
    smoke: {
      executor: 'constant-vus',
      vus: 5,
      duration: '30s',
      tags: { scenario: 'smoke' },
    },
    // Stress test – ramp up to find breaking point
    stress: {
      executor: 'ramping-vus',
      startVUs: 0,
      stages: [
        { duration: '20s', target: 20 },   // ramp up
        { duration: '30s', target: 50 },   // peak
        { duration: '10s', target: 0 },    // cool down
      ],
      startTime: '35s', // start after smoke
      tags: { scenario: 'stress' },
    },
  },
  thresholds: {
    http_req_duration: ['p(95)<2000'],    // 95% of requests < 2s
    errors:           ['rate<0.1'],       // error rate < 10%
    ad_list_duration: ['p(95)<1500'],     // ad listing < 1.5s
  },
};

// ─── Main test function ─────────────────────────────────────────────────────
export default function () {
  group('Public – Ad listing', () => {
    const res = http.get(`${BASE_URL}/api/v1/ads`, authHeaders);
    adListTrend.add(res.timings.duration);
    check(res, {
      'ad list status 200': (r) => r.status === 200,
      'ad list has data':   (r) => {
        try { return JSON.parse(r.body).data.length > 0; }
        catch { return false; }
      },
    }) || errorRate.add(1);
  });

  group('Public – Single ad', () => {
    const res = http.get(`${BASE_URL}/api/v1/ads/1`, authHeaders);
    adShowTrend.add(res.timings.duration);
    check(res, {
      'ad show status 2xx': (r) => r.status >= 200 && r.status < 300,
    }) || errorRate.add(1);
  });

  group('Public – Search', () => {
    const res = http.get(`${BASE_URL}/api/v1/ads?search=appartement&city=1`, authHeaders);
    searchTrend.add(res.timings.duration);
    check(res, {
      'search status 200': (r) => r.status === 200,
    }) || errorRate.add(1);
  });

  group('Auth – Login attempt', () => {
    const payload = JSON.stringify({
      email: 'loadtest@example.com',
      password: 'password',
    });
    const params = { headers: { 'Content-Type': 'application/json', Accept: 'application/json' } };
    const res = http.post(`${BASE_URL}/api/v1/login`, payload, params);
    loginTrend.add(res.timings.duration);
    check(res, {
      'login responds': (r) => r.status === 200 || r.status === 401 || r.status === 422 || r.status === 429,
    }) || errorRate.add(1);
  });

  sleep(1);
}

// ─── Summary ─────────────────────────────────────────────────────────────────
export function handleSummary(data) {
  const p95 = data.metrics.http_req_duration?.values?.['p(95)']?.toFixed(0) || '?';
  const errRate = ((data.metrics.errors?.values?.rate || 0) * 100).toFixed(1);
  const totalReqs = data.metrics.http_reqs?.values?.count || 0;

  console.log(`
╔═══════════════════════════════════════════════════╗
║  k6 Load Test Summary – KeyHome API               ║
╠═══════════════════════════════════════════════════╣
║  Total requests:     ${String(totalReqs).padStart(8)}                    ║
║  p95 response time:  ${String(p95 + 'ms').padStart(8)}                    ║
║  Error rate:         ${String(errRate + '%').padStart(8)}                    ║
╚═══════════════════════════════════════════════════╝
  `);

  return {};
}
