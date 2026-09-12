/* 
 * Typeahead plugin does not contain closing character for the trigger, 
 * so we need to map the trigger to the closing character.
 */

export function getTypeToTrigger(configs) {
  return Object.fromEntries(configs.map((c) => [c.type, c.trigger]))
}

/**
 * Converts typeahead directive syntax to plain content in markdown,
 * without trigger and escaped brackets.
 */
export function markdownDirectivesToPlain(md) {
  if (typeof md !== 'string') return md

  return md.replace(/:(\w+)\[((?:[^\]\\]|\\.)*)\]/g, (_, type, content) => {
    return content.replace(/\\\]/g, ']').replace(/\\\[/g, '[')
  })
}

/*
 * Drupal token syntax (e.g. [node:title], [current-user:name]) looks like a
 * bracketed span containing a colon. mdast-util-directive's toMarkdown
 * extension (loaded by @mdxeditor/typeahead-plugin and directivesPlugin)
 * defensively backslash-escapes such colons/brackets whenever it serializes
 * markdown, so they cannot be misread as directive syntax on a future
 * re-parse. unescapeTokens reverses that so the value saved back to Drupal is
 * always the exact raw token, never an escaped variant.
 */
const ESCAPED_TOKEN_PATTERN =
  /\\?\[((?:\\?[A-Za-z0-9_.-])+(?:\\?:(?:\\?[A-Za-z0-9_.-])+)+)\\?\]/g

/**
 * Strips backslashes from colons and brackets the markdown serializer
 * defensively escaped around Drupal-token-shaped spans. Idempotent/no-op on
 * text that has no escaping left (e.g. already resolved by
 * markdownDirectivesToPlain).
 */
export function unescapeTokens(md) {
  if (typeof md !== 'string') return md

  return md.replace(ESCAPED_TOKEN_PATTERN, (_, inner) => `[${inner.replace(/\\(.)/g, '$1')}]`)
}
