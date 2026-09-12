'use strict';

const { Given, Then, When } = require('@cucumber/cucumber');

const {
  friendly,
  gotoUrl,
  waitForPageLoad,
} = require('@vardot/varbase-e2e/tests/step-definitions/varbase-e2e');

/**
 * Run a step body and rethrow any failure as a tester-friendly error.
 */
async function attempt(body, message) {
  try {
    await body();
  } catch (err) {
    throw friendly(message, err);
  }
}

/**
 * Provision every non-admin user from worldParameters.users via
 * /admin/people/create. Idempotent across the scenario list.
 *
 * Example: Given I add testing users
 */
Given(/^(?:I |we )?add( the)? testing users$/, async function (theCase) {
  const users = this.parameters.users || {};
  await attempt(async () => {
    for (const [key, info] of Object.entries(users)) {
      if (info.isAdmin) continue;
      await gotoUrl(this.page, `${this.parameters.launchUrl}/admin/people/create`);
      await this.page.evaluate((info) => {
        const set = (sel, val) => { const el = document.querySelector(sel); if (el) el.value = val; };
        set('#edit-name', info.username);
        set('#edit-mail', info.email || `${info.username}@example.test`);
        set('#edit-pass-pass1', info.password);
        set('#edit-pass-pass2', info.password);
        for (const role of info.roles || []) {
          const cb = document.querySelector(`input[name="roles[${role}]"]`);
          if (cb) cb.checked = true;
        }
      }, info);
      await this.page.evaluate(() => document.querySelector('#edit-submit').click());
      await waitForPageLoad(this.page);
    }
  }, 'Could not provision the testing users');
});

/**
 * Resolve a varbase-e2e named selector from the world registry, suggesting the
 * closest registered name on a typo instead of dumping the whole catalogue.
 */
function resolveName(world, name) {
  const css = world.__selectorsCss || {};
  const key = name.trim();
  if (Object.prototype.hasOwnProperty.call(css, key)) {
    return css[key];
  }
  const keys = Object.keys(css);
  let best = null;
  let bestDistance = Infinity;
  for (const candidate of keys) {
    const distance = (function lev(a, b) {
      const m = a.length;
      const n = b.length;
      if (!m) return n;
      if (!n) return m;
      const row = new Array(n + 1);
      for (let j = 0; j <= n; j += 1) row[j] = j;
      for (let i = 1; i <= m; i += 1) {
        let prev = i;
        for (let j = 1; j <= n; j += 1) {
          const cost = a.charCodeAt(i - 1) === b.charCodeAt(j - 1) ? 0 : 1;
          const cur = Math.min(row[j] + 1, prev + 1, row[j - 1] + cost);
          row[j - 1] = prev;
          prev = cur;
        }
        row[n] = prev;
      }
      return row[n];
    }(key, candidate));
    if (distance < bestDistance) {
      bestDistance = distance;
      best = candidate;
    }
  }
  const hint = best && bestDistance <= Math.max(4, Math.floor(key.length / 3))
    ? ` Did you mean "${best}"?`
    : '';
  throw new Error(`Unknown named selector "${key}".${hint} Run "Then print css selectors" to see all ${keys.length} registered names.`);
}

/**
 * Assert a named selector is visible / hidden / attached / focused / enabled /
 * disabled / editable.
 *
 * Example: Then the "ace editor container" element should be visible
 */
Then(/^the "([^"]*)" element should be (visible|hidden|attached|focused|enabled|disabled|editable)(?: within (\d+) seconds?)?$/, async function (name, state, sec) {
  const sel = resolveName(this, name);
  const loc = this.page.locator(sel);
  const timeout = sec ? Number(sec) * 1000 : 10000;
  await attempt(async () => {
    if (state === 'visible' || state === 'attached') {
      await loc.first().waitFor({ state, timeout });
    }
    else if (state === 'hidden') {
      await loc.first().waitFor({ state: 'hidden', timeout });
    }
    else if (state === 'focused') {
      await this.page.waitForFunction(s => document.activeElement && document.activeElement.matches(s), sel, { timeout });
    }
    else {
      const fn = { enabled: 'isEnabled', disabled: 'isDisabled', editable: 'isEditable' }[state];
      const ok = await loc.first()[fn]();
      if (!ok) throw new Error(`"${name}" not ${state}`);
    }
  }, `Expected "${name}" (${sel}) to be ${state}`);
});

/**
 * Assert the exact count of elements matching a named selector.
 *
 * Example: Then the "ace editor container" element should have a count of 1
 */
Then(/^the "([^"]*)" element should have a count of (\d+)(?: within (\d+) seconds?)?$/, async function (name, expected, sec) {
  const sel = resolveName(this, name);
  const target = Number(expected);
  const timeout = sec ? Number(sec) * 1000 : 10000;
  const loc = this.page.locator(sel);
  const deadline = Date.now() + timeout;
  let last = -1;
  await attempt(async () => {
    while (Date.now() < deadline) {
      last = await loc.count();
      if (last === target) return;
      await this.page.waitForTimeout(100);
    }
    throw new Error(`count was ${last}`);
  }, `Expected "${name}" (${sel}) count to be ${target}`);
});

/**
 * Assert at least N elements match a named selector.
 *
 * Example: Then the "ace editor gutter cell" element should have at least a count of 1
 */
Then(/^the "([^"]*)" element should have at least a count of (\d+)(?: within (\d+) seconds?)?$/, async function (name, expected, sec) {
  const sel = resolveName(this, name);
  const target = Number(expected);
  const timeout = sec ? Number(sec) * 1000 : 10000;
  const loc = this.page.locator(sel);
  const deadline = Date.now() + timeout;
  let last = -1;
  await attempt(async () => {
    while (Date.now() < deadline) {
      last = await loc.count();
      if (last >= target) return;
      await this.page.waitForTimeout(100);
    }
    throw new Error(`count was ${last}`);
  }, `Expected "${name}" (${sel}) count to be at least ${target}`);
});

/**
 * Assert the first element matching a named selector contains the given text.
 *
 * Example: Then the "ace editor container" element should contain text "function"
 */
Then(/^the "([^"]*)" element should contain text "([^"]*)"(?: within (\d+) seconds?)?$/, async function (name, text, sec) {
  const sel = resolveName(this, name);
  const timeout = sec ? Number(sec) * 1000 : 10000;
  await attempt(async () => {
    await this.page.waitForFunction(
      ([s, t]) => { const el = document.querySelector(s); return el && el.textContent.includes(t); },
      [sel, text],
      { timeout, polling: 100 },
    );
  }, `Expected "${name}" (${sel}) to contain text "${text}"`);
});

/**
 * Assert an element's computed CSS value for a property.
 *
 * Example: Then the "ace editor print margin" element should have the computed style "display" of "block"
 */
Then(/^the "([^"]*)" element should have the computed style "([^"]*)" of "([^"]*)"$/, async function (name, property, value) {
  const sel = resolveName(this, name);
  await attempt(async () => {
    await this.page.waitForFunction(
      ([s, p, v]) => {
        const el = document.querySelector(s);
        return !!el && window.getComputedStyle(el).getPropertyValue(p).trim() === v;
      },
      [sel, property, value],
      { timeout: 10000, polling: 100 },
    );
  }, `Expected "${name}" (${sel}) computed ${property} to be "${value}"`);
});

/**
 * Assert the global Ace library object is available on the page.
 *
 * Example: Then the global Ace library should be available
 */
Then(/^the global Ace library should be available(?: within (\d+) seconds?)?$/, async function (sec) {
  const timeout = sec ? Number(sec) * 1000 : 10000;
  await attempt(async () => {
    await this.page.waitForFunction(() => typeof window.ace !== 'undefined', null, { timeout, polling: 100 });
  }, 'Expected the global "ace" object to be defined');
});

/**
 * Assert that the Drupal Ace editor plugin is registered.
 *
 * Example: Then the Drupal editor "ace_editor" should be registered
 */
Then(/^the Drupal editor "([^"]*)" should be registered$/, async function (id) {
  await attempt(async () => {
    await this.page.waitForFunction(
      (id) => !!(window.Drupal && Drupal.editors && Drupal.editors[id]),
      id,
      { timeout: 10000, polling: 100 },
    );
  }, `Expected Drupal.editors.${id} to be registered`);
});

/**
 * Assert that a Drupal behavior is registered (used to prove the formatter and
 * filter behaviours have distinct names and therefore coexist).
 *
 * Example: Then the Drupal behavior "ace_filter" should be registered
 */
Then(/^the Drupal behavior "([^"]*)" should be registered$/, async function (name) {
  await attempt(async () => {
    await this.page.waitForFunction(
      (name) => !!(window.Drupal && Drupal.behaviors && Drupal.behaviors[name]),
      name,
      { timeout: 10000, polling: 100 },
    );
  }, `Expected Drupal.behaviors.${name} to be registered`);
});

/**
 * Assert the Ace editor has attached to a textarea: the original textarea is
 * hidden and a rendered .ace_editor sits inside the same form-item wrapper.
 *
 * Example: Then the Ace editor should be attached to the "body textarea" field
 */
Then(/^the Ace editor should be attached to the "([^"]*)" field$/, async function (name) {
  const sel = resolveName(this, name);
  await attempt(async () => {
    await this.page.waitForFunction(
      (s) => {
        const ta = document.querySelector(s);
        if (!ta) return false;
        const wrapper = ta.closest('.js-form-type-textarea') || ta.parentElement;
        const editor = wrapper && wrapper.querySelector('.ace_editor');
        const hidden = window.getComputedStyle(ta).visibility === 'hidden' || ta.offsetParent === null;
        return !!editor && hidden;
      },
      sel,
      { timeout: 15000, polling: 150 },
    );
  }, `Expected the Ace editor to be attached to "${name}" (${sel})`);
});

/**
 * Assert the Ace print margin is actually shown (display not "none") for a
 * named element. Proves the print_margins setting is honoured.
 *
 * Example: Then the "ace editor print margin" element should show the print margin
 */
Then(/^the "([^"]*)" element should show the print margin$/, async function (name) {
  const sel = resolveName(this, name);
  await attempt(async () => {
    await this.page.waitForFunction(
      (s) => { const el = document.querySelector(s); return !!el && window.getComputedStyle(el).display !== 'none'; },
      sel,
      { timeout: 10000, polling: 100 },
    );
  }, `Expected "${name}" (${sel}) print margin to be shown`);
});

/**
 * Type text into the first Ace editor on the page (an editor-mode instance),
 * then wait for the debounced sync back to the underlying textarea.
 *
 * Example: When I type "<h2>Synced</h2>" into the Ace editor
 */
When(/^(?:I |we )?type "([^"]*)" into the Ace editor$/, async function (text) {
  await attempt(async () => {
    await this.page.waitForFunction(() => {
      const pre = document.querySelector('pre[id$="-ace-editor"]');
      return pre && window.ace && window.ace.edit;
    }, null, { timeout: 15000, polling: 150 });
    await this.page.evaluate((value) => {
      const pre = document.querySelector('pre[id$="-ace-editor"]');
      const editor = window.ace.edit(pre.id);
      editor.session.setValue(value);
    }, text);
    // js/editor.js debounces the textarea sync by 400ms.
    await this.page.waitForTimeout(700);
  }, `Could not type into the Ace editor`);
});

/**
 * Assert a named form field (textarea/input) currently holds the given value.
 *
 * Uses a distinct phrasing ("field value should contain") and an evaluate-based
 * read so it works against the Ace-hidden textarea, where varbase-e2e's built-in
 * visible-field assertion would not apply.
 *
 * Example: Then the "body textarea" field value should contain "<h2>Synced</h2>"
 */
Then(/^the "([^"]*)" field value should contain "([^"]*)"(?: within (\d+) seconds?)?$/, async function (name, text, sec) {
  const sel = resolveName(this, name);
  const timeout = sec ? Number(sec) * 1000 : 10000;
  await attempt(async () => {
    await this.page.waitForFunction(
      ([s, t]) => { const el = document.querySelector(s); return el && (el.value || '').includes(t); },
      [sel, text],
      { timeout, polling: 100 },
    );
  }, `Expected the "${name}" (${sel}) field to contain "${text}"`);
});

/**
 * Click the first element matching a named selector, falling back to a JS
 * .click() if Playwright actionability is blocked.
 *
 * Example: When I click the "save configuration button" element
 */
When(/^(?:I |we )?click(?: on)?(?: the)? "([^"]*)" element$/, async function (name) {
  const sel = resolveName(this, name);
  await attempt(async () => {
    const loc = this.page.locator(sel).first();
    await loc.waitFor({ state: 'visible', timeout: 10000 });
    try {
      await loc.click({ timeout: 4000 });
    }
    catch (e) {
      await this.page.evaluate((s) => { const el = document.querySelector(s); if (el) el.click(); }, sel);
    }
    await waitForPageLoad(this.page, this.minWaitTime && this.minWaitTime.page);
  }, `Could not click the "${name}" element`);
});

/**
 * Focus a named element and press a key or chord. Generic - any key, any
 * element; used here to exercise the editor's keyboard shortcuts.
 *
 * Example: When I press the "Control+f" key in the "ace editor container" element
 */
When(/^(?:I |we )?press the "([^"]*)" key in the "([^"]*)" element$/, async function (key, name) {
  const sel = resolveName(this, name);
  await attempt(async () => {
    const loc = this.page.locator(sel).first();
    await loc.waitFor({ state: 'visible', timeout: 10000 });
    await loc.click();
    await this.page.keyboard.press(key);
  }, `Could not press "${key}" in the "${name}" element`);
});

/**
 * Type into the Ace editor the way a person does: focus its hidden text input
 * and press keys. Unlike a programmatic session.setValue(), this is what a
 * read-only editor is supposed to refuse (issue #3046914).
 *
 * Example: When I type "abc" into the Ace editor with the keyboard
 */
When(/^(?:I |we )?type "([^"]*)" into the Ace editor with the keyboard$/, async function (text) {
  await attempt(async () => {
    const input = this.page.locator('.js-form-type-textarea .ace_editor textarea.ace_text-input').first();
    await input.waitFor({ state: 'attached', timeout: 15000 });
    await this.page.locator('.js-form-type-textarea .ace_editor').first().click();
    await this.page.keyboard.type(text);
    // js/editor.js debounces the textarea sync by 400ms.
    await this.page.waitForTimeout(700);
  }, `Could not type "${text}" into the Ace editor with the keyboard`);
});

/**
 * Assert the text currently held by the Ace editor session.
 *
 * Example: Then the Ace editor content should not contain "abc"
 */
Then(/^the Ace editor content should( not)? contain "([^"]*)"$/, async function (negated, text) {
  await attempt(async () => {
    const content = await this.page.evaluate(() => {
      const pre = document.querySelector('pre[id$="-ace-editor"]');
      return (pre && window.ace) ? window.ace.edit(pre.id).getValue() : null;
    });
    if (content === null) {
      throw new Error('No Ace editor was found on the page.');
    }
    const contains = content.includes(text);
    if (negated && contains) {
      throw new Error(`Expected the Ace editor content not to contain "${text}", but it did.`);
    }
    if (!negated && !contains) {
      throw new Error(`Expected the Ace editor content to contain "${text}", but it did not.`);
    }
  }, `Could not verify the Ace editor content`);
});

/**
 * Assert a named element's rendered height is greater than N pixels.
 *
 * Example: Then the "ace formatter container" element should be taller than 20 pixels
 */
Then(/^the "([^"]*)" element should be taller than (\d+) pixels(?: within (\d+) seconds?)?$/, async function (name, px, sec) {
  const selector = resolveName(this, name);
  const minimum = Number(px);
  const timeout = sec ? Number(sec) * 1000 : 10000;
  await attempt(async () => {
    await this.page.waitForFunction(
      ([s, m]) => {
        const element = document.querySelector(s);
        return !!element && element.getBoundingClientRect().height > m;
      },
      [selector, minimum],
      { timeout, polling: 100 },
    );
  }, `Expected "${name}" (${selector}) to be taller than ${minimum}px`);
});

/**
 * Assert the Ace mode of the widget attached to a named field.
 *
 * The widget prints its editor next to the field's textarea, so the field name
 * identifies the instance without depending on the delta or the page order.
 *
 * Example: Then the Ace widget "field_gherkin_script" should use the "gherkin" mode
 */
Then(/^the Ace widget "([^"]*)" should use the "([^"]*)" mode$/, async function (field, mode) {
  await attempt(async () => {
    const actual = await this.page.evaluate((field) => {
      const textarea = document.querySelector('textarea.ace-editor-widget[name^="' + field + '"]');
      if (!textarea || !window.ace) {
        return null;
      }
      const editor = window.ace.edit(textarea.id + '-ace-widget');
      return editor.getSession().getMode().$id;
    }, field);
    if (actual === null) {
      throw new Error(`No Ace widget was found for the "${field}" field.`);
    }
    if (actual !== 'ace/mode/' + mode) {
      throw new Error(`Expected the "${field}" widget to use "ace/mode/${mode}", found "${actual}".`);
    }
  }, `Could not verify the mode of the "${field}" Ace widget`);
});

/**
 * Assert the text held by the Ace widget of a named field.
 *
 * Example: Then the Ace widget "field_gherkin_script" content should contain "Given I am"
 */
Then(/^the Ace widget "([^"]*)" content should( not)? contain "([^"]*)"$/, async function (field, negated, text) {
  await attempt(async () => {
    const content = await this.page.evaluate((field) => {
      const textarea = document.querySelector('textarea.ace-editor-widget[name^="' + field + '"]');
      if (!textarea || !window.ace) {
        return null;
      }
      return window.ace.edit(textarea.id + '-ace-widget').getSession().getValue();
    }, field);
    if (content === null) {
      throw new Error(`No Ace widget was found for the "${field}" field.`);
    }
    const contains = content.includes(text);
    if (negated && contains) {
      throw new Error(`Expected the "${field}" widget not to contain "${text}", but it did.`);
    }
    if (!negated && !contains) {
      throw new Error(`Expected the "${field}" widget to contain "${text}", but it did not.`);
    }
  }, `Could not verify the content of the "${field}" Ace widget`);
});
