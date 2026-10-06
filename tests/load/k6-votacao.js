// Teste de carga HTTP (k6): simula o cenário "1.000 pessoas votando em períodos próximos".
// Uso:
//   k6 run -e BASE_URL=https://staging.exemplo.com -e PUBLIC_ID=ABC12345 -e USERS=1000 tests/load/k6-votacao.js
//
// Pré-requisito (APENAS em ambiente de teste): o Login Google real não é automatizável.
// Gere N usuários + sessões de teste no banco de STAGING (ex.: via tinker/factory + session driver "database")
// e grave um cookie de sessão por linha em SESSIONS_FILE. Cada usuário virtual usa sua própria sessão,
// logo cada um vota uma única vez. Veja docs/TESTES.md.
import http from 'k6/http';
import { check, sleep } from 'k6';
import { SharedArray } from 'k6/data';

const BASE = __ENV.BASE_URL || 'http://localhost:8000';
const ID = __ENV.PUBLIC_ID;
const sessions = new SharedArray('sessions', () => open(__ENV.SESSIONS_FILE || './sessions.txt').split('\n').filter(Boolean));

export const options = {
  scenarios: {
    pico: { executor: 'ramping-vus', startVUs: 0, stages: [{ duration: '30s', target: Number(__ENV.USERS || 1000) }, { duration: '60s', target: Number(__ENV.USERS || 1000) }, { duration: '15s', target: 0 }] },
  },
  thresholds: { http_req_failed: ['rate<0.01'], http_req_duration: ['p(95)<1500'] },
};

export default function () {
  const cookie = sessions[(__VU - 1) % sessions.length];
  const jar = http.cookieJar();
  jar.set(BASE, 'votaflow_session', cookie);

  const pagina = http.get(`${BASE}/v/${ID}/votar`);
  check(pagina, { 'pagina 200/302': (r) => [200, 302].includes(r.status) });
  const token = (pagina.html && pagina.html().find('input[name=_token]').first().attr('value')) || '';
  sleep(Math.random() * 20); // tempo "lendo" as perguntas

  const body = JSON.parse(__ENV.RESPOSTAS || '{}'); // {"respostas[ID][]": ALT_ID, ...}
  body._token = token;
  const r = http.post(`${BASE}/v/${ID}/votar`, body, { redirects: 0 });
  check(r, { 'voto aceito (302)': (x) => x.status === 302 });
}
