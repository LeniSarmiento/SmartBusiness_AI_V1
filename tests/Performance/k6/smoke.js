import http from 'k6/http';
import { check, fail, group, sleep } from 'k6';
import { Trend } from 'k6/metrics';

const baseUrl = (__ENV.BASE_URL || 'http://localhost:8080').replace(/\/$/, '');
const appOrigin = (__ENV.APP_ORIGIN || baseUrl).replace(/\/$/, '');
const email = __ENV.LOAD_TEST_EMAIL;
const password = __ENV.LOAD_TEST_PASSWORD;

const dashboardDuration = new Trend('smartbusiness_dashboard_duration', true);
const productsDuration = new Trend('smartbusiness_products_duration', true);
const inventoryDuration = new Trend('smartbusiness_inventory_duration', true);
const salesDuration = new Trend('smartbusiness_sales_duration', true);

export const options = {
  vus: Number(__ENV.K6_VUS || 3),
  duration: __ENV.K6_DURATION || '30s',
  thresholds: {
    checks: ['rate>0.99'],
    http_req_failed: ['rate<0.01'],
    http_req_duration: ['p(95)<1500'],
    smartbusiness_dashboard_duration: ['p(95)<2000'],
    smartbusiness_products_duration: ['p(95)<1000'],
    smartbusiness_inventory_duration: ['p(95)<1000'],
    smartbusiness_sales_duration: ['p(95)<1000'],
  },
};

function baseHeaders() {
  return {
    Accept: 'application/json',
    Origin: appOrigin,
    Referer: `${appOrigin}/`,
  };
}

export function setup() {
  if (!email || !password) {
    fail('Define LOAD_TEST_EMAIL y LOAD_TEST_PASSWORD. No se incluyen credenciales en el repositorio.');
  }

  const jar = http.cookieJar();
  const csrf = http.get(`${baseUrl}/sanctum/csrf-cookie`, {
    headers: baseHeaders(),
    tags: { endpoint: 'csrf' },
  });
  check(csrf, { 'CSRF cookie disponible': (response) => response.status === 204 });

  const csrfCookies = jar.cookiesForURL(baseUrl);
  const xsrfToken = csrfCookies['XSRF-TOKEN']?.[0];
  if (!xsrfToken) {
    fail('Laravel no devolvió la cookie XSRF-TOKEN.');
  }

  const login = http.post(
    `${baseUrl}/api/v1/auth/login`,
    JSON.stringify({ email, password }),
    {
      headers: {
        ...baseHeaders(),
        'Content-Type': 'application/json',
        'X-XSRF-TOKEN': decodeURIComponent(xsrfToken),
      },
      tags: { endpoint: 'login' },
    },
  );
  check(login, { 'login correcto': (response) => response.status === 200 });
  if (login.status !== 200) {
    fail(`Login falló con HTTP ${login.status}.`);
  }

  const body = login.json();
  const memberships = body?.data?.memberships || body?.memberships || [];
  const organizationId = memberships[0]?.organization?.id;
  if (!organizationId) {
    fail('La cuenta de carga no tiene una organización activa.');
  }

  const cookies = jar.cookiesForURL(baseUrl);
  const cookieHeader = Object.entries(cookies)
    .map(([name, values]) => `${name}=${values[0]}`)
    .join('; ');

  return { organizationId, cookieHeader };
}

function readEndpoint(path, endpoint, data, metric) {
  const response = http.get(`${baseUrl}${path}`, {
    headers: {
      ...baseHeaders(),
      Cookie: data.cookieHeader,
      'X-Request-ID': `k6-${__VU}-${__ITER}-${endpoint}`,
    },
    tags: { endpoint },
  });

  metric.add(response.timings.duration);
  check(response, {
    [`${endpoint} responde 200`]: (result) => result.status === 200,
    [`${endpoint} devuelve JSON`]: (result) => (result.headers['Content-Type'] || '').includes('application/json'),
  });
}

export default function (data) {
  const prefix = `/api/v1/organizations/${data.organizationId}`;

  group('lecturas empresariales', () => {
    readEndpoint(`${prefix}/dashboard`, 'dashboard', data, dashboardDuration);
    readEndpoint(`${prefix}/products?per_page=20`, 'products', data, productsDuration);
    readEndpoint(`${prefix}/inventory-movements?per_page=20`, 'inventory', data, inventoryDuration);
    readEndpoint(`${prefix}/sales?per_page=20`, 'sales', data, salesDuration);
  });

  sleep(1);
}
