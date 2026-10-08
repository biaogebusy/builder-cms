import assert from 'node:assert/strict';
import { createRequire } from 'node:module';
import { resolve } from 'node:path';

const modules = process.env.KNOWLEDGE_BROWSER_MODULES;
const baseURL = process.env.KNOWLEDGE_UI_URL;
assert(modules && /^http:\/\/127\.0\.0\.1:\d+$/.test(baseURL), 'Use the isolated runner and its loopback URL.');
const require = createRequire(import.meta.url);
const { chromium } = require(resolve(modules, 'playwright'));
const { expect } = require(resolve(modules, 'playwright/test'));
const { default: AxeBuilder } = require(resolve(modules, '@axe-core/playwright'));
const fixture = JSON.parse(process.env.KNOWLEDGE_UI_FIXTURE);
const overview = '/admin/config/content/xinshi-knowledge-sync';
const browser = await chromium.launch({ headless: true, channel: process.env.KNOWLEDGE_BROWSER_CHANNEL });
const contexts = [];
let checks = 0;

function pass(message) {
  checks++;
  console.log(`PASS: ${message}`);
}

function field(page, name) {
  return page.locator(`[name="settings[${name}]"]`);
}

async function session(name) {
  const context = await browser.newContext({ baseURL, viewport: { width: 1366, height: 1000 } });
  contexts.push(context);
  const page = await context.newPage();
  if (name) {
    await page.goto('/user/login');
    await page.locator('[name="name"]').fill(name);
    await page.locator('[name="pass"]').fill('synthetic-form-test-only');
    await page.getByRole('button', { name: 'Log in', exact: true }).click();
    await expect(page).not.toHaveURL(/\/user\/login/);
  }
  return { context, page };
}

async function clickAjax(page, label) {
  const response = page.waitForResponse((item) => item.url().includes('_wrapper_format=drupal_ajax'));
  await page.getByRole('button', { name: label, exact: true }).click();
  assert((await response).ok(), 'AJAX request failed.');
}

async function policyId(page, index, value) {
  const input = field(page, `policies][${index}][id`);
  await input.fill(value);
  const response = page.waitForResponse((item) => item.url().includes('_wrapper_format=drupal_ajax'));
  await input.press('Tab');
  assert((await response).ok(), 'Policy option refresh failed.');
  await expect(field(page, 'default_policy').locator(`option[value="${value}"]`)).toHaveCount(1);
}

async function save(page) {
  await page.getByRole('button', { name: '保存来源', exact: true }).click();
  await expect(page).toHaveURL(baseURL + overview);
}

async function accessible(page, name) {
  const report = await new AxeBuilder({ page }).analyze();
  assert.deepEqual(report.violations.map(({ id, nodes }) => ({ id, targets: nodes.map(({ target }) => target) })), [], name);
  pass(`AXE: ${name}`);
}

try {
  await expect.poll(async () => {
    try { return (await fetch(baseURL)).status; } catch { return 0; }
  }, { timeout: 15000 }).toBeGreaterThan(0);

  for (const name of [null, 'reader', 'sync_parser_admin']) {
    const { context } = await session(name);
    for (const path of [overview, `${overview}/add`, `${overview}/form-fixture/edit`]) {
      assert.equal((await context.request.get(path)).status(), 403, `${name ?? 'anonymous'} read ${path}`);
      assert.equal((await context.request.post(path, { form: { form_id: 'xinshi_knowledge_sync_source' } })).status(), 403);
    }
    pass(`GET and POST denied for ${name ?? 'anonymous'} on all three admin routes`);
  }

  const { context, page } = await session('sync_config_admin');
  await page.goto(overview);
  await expect(page.getByRole('heading', { name: '知识库文档同步', exact: true })).toBeVisible();
  await accessible(page, 'source overview');
  await page.getByRole('link', { name: '添加文档来源', exact: true }).click();
  await field(page, 'id').fill('browser-source');
  await field(page, 'enabled').check();
  await policyId(page, 0, 'public');
  await field(page, 'default_policy').selectOption('public');
  await clickAjax(page, '添加阅读策略');
  await policyId(page, 1, 'staff');
  await field(page, 'policies][1][roles][operations').check();
  await field(page, 'policies][1][users').fill('rea');
  await page.locator('.ui-autocomplete .ui-menu-item').filter({ hasText: /^reader/ }).click();
  await expect(field(page, 'policies][1][users')).toHaveValue(`reader (${fixture.reader})`);
  await clickAjax(page, '添加目录规则');
  await field(page, 'rules][0][prefix').fill('internal/');
  await field(page, 'rules][0][policy').selectOption('staff');
  await clickAjax(page, '添加阅读策略');
  await clickAjax(page, '移除策略 3');
  await clickAjax(page, '添加目录规则');
  await clickAjax(page, '移除规则 2');
  await save(page);
  pass('Create source with renamed default policy, AJAX rows, role and autocomplete account');

  await page.goto(`${overview}/browser-source/edit`);
  await expect(field(page, 'id')).toBeDisabled();
  await expect(field(page, 'policies][0][id')).toBeDisabled();
  await expect(field(page, 'policies][1][id')).toHaveValue('staff');
  await expect(field(page, 'policies][1][roles][operations')).toBeChecked();
  await expect(field(page, 'policies][1][users')).toHaveValue(`reader (${fixture.reader})`);
  await expect(field(page, 'default_policy')).toHaveValue('public');
  await expect(field(page, 'rules][0][policy')).toHaveValue('staff');
  await accessible(page, 'source editor');
  await page.screenshot({ path: '/tmp/xinshi-knowledge-sync-admin-ui.png', fullPage: true });
  pass('Saved fields survive a fresh request and existing identities are immutable');

  const forged = await page.locator('form.xinshi-knowledge-sync-source').evaluate((form) => Object.fromEntries(new FormData(form)));
  forged.form_token = 'invalid-test-token';
  forged.op = '保存来源';
  delete forged['settings[enabled]'];
  const response = await context.request.post(`${overview}/browser-source/edit`, { form: forged });
  assert.match(await response.text(), /outdated|invalid|过期/i, 'Missing CSRF error.');
  await page.reload();
  await expect(field(page, 'enabled')).toBeChecked();
  pass('Invalid CSRF token cannot change source configuration');

  const second = await context.newPage();
  await second.goto(`${overview}/browser-source/edit`);
  await field(page, 'enabled').uncheck();
  await save(page);
  await second.getByRole('button', { name: '保存来源', exact: true }).click();
  await expect(second.locator('.messages--error')).toContainText('来源已被其他管理员修改');
  await second.goto(`${overview}/browser-source/edit`);
  await expect(field(second, 'enabled')).not.toBeChecked();
  await second.close();
  pass('A second open form cannot overwrite a concurrent source edit');

  await page.goto(`${overview}/add`);
  await field(page, 'id').fill('browser-source');
  await page.getByRole('button', { name: '保存来源', exact: true }).click();
  await expect(page.locator('.messages--error')).toContainText('该来源标识已存在');
  pass('Duplicate source submission reports a visible error');

  await page.goto(`${overview}/form-fixture/edit`);
  await clickAjax(page, '移除规则 1');
  await clickAjax(page, '移除策略 2');
  await page.getByRole('button', { name: '保存来源', exact: true }).click();
  await expect(page.locator('.messages--error')).toContainText('有文档仍关联已移除的策略');
  await page.goto(`${overview}/form-fixture/edit`);
  await expect(field(page, 'policies][1][id')).toHaveValue('operations');
  await clickAjax(page, '移除策略 3');
  await save(page);
  pass('Referenced policy removal fails and unused policy removal succeeds');

  const reader = await session('reader');
  assert.equal((await reader.context.request.get(`/node/${fixture.privateNode}`)).status(), 200);
  await page.goto(`${overview}/form-fixture/edit`);
  await field(page, 'policies][1][users').fill('');
  await save(page);
  assert.equal((await reader.context.request.get(`/node/${fixture.privateNode}`)).status(), 403);
  await page.goto(`${overview}/form-fixture/edit`);
  await field(page, 'policies][1][users').fill(`reader (${fixture.reader})`);
  await save(page);
  assert.equal((await reader.context.request.get(`/node/${fixture.privateNode}`)).status(), 200);
  await page.goto(`${overview}/form-fixture/edit`);
  await field(page, 'enabled').uncheck();
  await save(page);
  assert.equal((await reader.context.request.get(`/node/${fixture.privateNode}`)).status(), 403);
  pass('Form membership changes and disabling a source enforce current HTTP read access');
  console.log(`Knowledge sync browser: ${checks} checks passed.`);
} finally {
  await Promise.all(contexts.map((context) => context.close()));
  await browser.close();
}
