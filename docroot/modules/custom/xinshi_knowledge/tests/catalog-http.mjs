import assert from 'node:assert/strict';
import { createRequire } from 'node:module';
import { join } from 'node:path';
const require = createRequire(join(process.env.KNOWLEDGE_BROWSER_MODULES, 'package.json'));
const { request } = require('playwright');
const baseURL = process.env.KNOWLEDGE_UI_URL;
const fixture = JSON.parse(process.env.KNOWLEDGE_UI_FIXTURE);
const context = await request.newContext({ baseURL });
let checks = 0;
const check = (value, message) => { assert.ok(value, message); checks++; };
const privateResponse = response => check(response.headers()['cache-control'].includes('no-store'), 'Response may be cached');
try {
  const anonymous = await context.get('/api/v3/knowledge');
  check(anonymous.status() === 403, 'Anonymous catalog access');
  privateResponse(anonymous);
  let login = await context.post('/user/login?_format=json', {
    data: { name: 'catalog-editor', pass: 'catalog-test-password' },
  });
  check(login.ok(), 'Editor login failed: ' + await login.text());
  const token = await (await context.get('/session/token')).text();
  const headers = { Accept: 'application/vnd.api+json', 'X-CSRF-Token': token };
  const list = await context.get('/api/v3/knowledge');
  check(list.ok(), 'Authenticated catalog failed: ' + await list.text());
  privateResponse(list);
  check((await list.json()).items.length === 20, 'HTTP pagination');
  const bad = await context.get('/api/v3/knowledge?before[]=1');
  check(bad.status() === 400, 'Malformed cursor accepted');
  privateResponse(bad);
  const detail = await context.get('/api/v3/knowledge/' + fixture.id);
  check((await detail.json()).owner === 'catalog-editor', 'HTTP owner missing');
  const missing = await context.get('/api/v3/knowledge/00000000-0000-4000-8000-000000000000');
  check(missing.status() === 404, 'Missing resource should be 404');
  privateResponse(missing);
  const filePath = '/api/v1/media/xinshi_knowledge_document/field_knowledge_file';
  const fileHeaders = { ...headers, 'Content-Type': 'application/octet-stream',
    'Content-Disposition': `file; filename="${encodeURIComponent('产品资料.txt')}"` };
  const noCsrf = await context.post(filePath, { data: Buffer.from('test'),
    headers: { ...fileHeaders, 'X-CSRF-Token': '' } });
  check(noCsrf.status() === 403, 'Cookie upload without CSRF returned ' + noCsrf.status() + ': ' + await noCsrf.text());
  const uploaded = await context.post(filePath, { data: Buffer.from('HTTP upload acceptance fixture'), headers: fileHeaders });
  check(uploaded.status() === 201, 'File upload failed: ' + await uploaded.text());
  const file = (await uploaded.json()).data;
  const mediaResponse = await context.post('/api/v1/media/xinshi_knowledge_document', {
    headers: { ...headers, 'Content-Type': 'application/vnd.api+json' },
    data: { data: { type: 'media--xinshi_knowledge_document', attributes: { name: '产品资料.txt' },
      relationships: { field_knowledge_file: { data: { type: 'file--file', id: file.id } } } } },
  });
  check(mediaResponse.status() === 201, 'Media create failed: ' + await mediaResponse.text());
  const media = (await mediaResponse.json()).data;
  const nodeResponse = await context.post('/api/v1/node/xinshi_knowledge', {
    headers: { ...headers, 'Content-Type': 'application/vnd.api+json' },
    data: { data: { type: 'node--xinshi_knowledge', attributes: { title: 'Uploaded from chat' },
      relationships: { field_knowledge_documents: { data: [{ type: media.type, id: media.id }] } } } },
  });
  check(nodeResponse.status() === 201, 'Knowledge create failed: ' + await nodeResponse.text());
  const node = (await nodeResponse.json()).data;
  const status = await context.get('/api/v3/knowledge/' + node.id);
  const row = await status.json();
  check(row.owner === 'catalog-editor' && row.attachments[0].status === 'pending' && row.attachments[0].name === '产品资料.txt', 'Upload owner or queued state incorrect');
  const other = await request.newContext({ baseURL });
  try {
    login = await other.post('/user/login?_format=json', { data: { name: 'catalog-outsider', pass: 'catalog-test-password' } });
    check(login.ok(), 'Outsider login failed');
    const denied = await other.get('/api/v3/knowledge/' + node.id);
    check(denied.status() === 403, 'Outsider read allowed');
    privateResponse(denied);
    const otherToken = await (await other.get('/session/token')).text();
    const deniedUpload = await other.post(filePath, { data: Buffer.from('denied'),
      headers: { ...fileHeaders, 'X-CSRF-Token': otherToken } });
    check(deniedUpload.status() === 403, 'Outsider upload allowed');
  } finally { await other.dispose(); }
  process.stdout.write(`Catalog HTTP: ${checks} checks passed.\n`);
} finally { await context.dispose(); }
