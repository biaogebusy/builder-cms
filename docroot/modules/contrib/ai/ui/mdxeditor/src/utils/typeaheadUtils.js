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
