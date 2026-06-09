# HostnameFilter Service

The `HostnameFilter` service filters AI-generated output to remove or rewrite links and images with disallowed hostnames.

## Configuration

By default no hostnames are allowed, meaning all links and images will be stripped from AI-generated content. You can configure allowed hostnames and rewrite behavior in the Drupal admin UI at **Configuration » AI » Default Settings** (/admin/config/ai/settings).

### Allowed Hostnames
You can specify a list of allowed hostnames (one per line). Wildcards are supported using `*` to match any subdomain. For example:

```
example.com
*.cdn.example.com
trustedsite.org
```

### Rewrite Links
You can choose whether to rewrite links to allowed hostnames or remove them entirely. If enabled, links to allowed hostnames will have their inner HTML rewritten to match the actual URL, ensuring transparency about where the link points.

### Full Trust Mode
If you really want to allow all hostnames to be trusted (not recommended), you can add the following to your `settings.php` file:

```php
// AI output filtering configuration.
$settings['ai_output'] = [
  // Full trust mode - bypass all filtering.
  // Default: FALSE
  'full_trust_mode' => FALSE,
];
```

*Note that these can be overwritten per AI request on the ChatInput object, if needed.*

## Examples

### Default Behavior (rewrite_links = FALSE)

**HTML Input:**
```html
<a href="https://evil.com/phishing">Click here for free gift</a>
```

**Output:**
```html
<a>Click here for free gift</a>
```

**Markdown Input:**
```markdown
[Click here for free gift](https://evil.com/phishing)
```

**Output:**
```markdown
Click here for free gift
```

### Rewrite Mode (rewrite_links = TRUE)


**HTML Input:**
```html
<a href="https://evil.com/phishing?data=exfiltration">Click here for free gift</a>
```

**Output:**
```html
<a href="https://evil.com/phishing?data=exfiltration">https://evil.com/phishing?data=exfiltration</a>
```

**Markdown Input:**
```markdown
[Click here for free gift](https://evil.com/phishing?data=exfiltration)
```

**Output:**
```markdown
[https://evil.com/phishing?data=exfiltration](https://evil.com/phishing?data=exfiltration)
```

## Why Use Rewrite Mode?

Rewrite mode is useful for transparency or when you trust your end-users to read the actual link text - it ensures users can see exactly where a link points before clicking, preventing deceptive link text from hiding malicious URLs.

## Allowed Content

The following are always allowed regardless of domain settings:
- Relative URLs: `/path/to/page`
- Absolute paths: `/absolute/path`
- Anchor links: `#section`
- Mailto links: `mailto:user@example.com`
- Protocol-relative URLs: `//example.com/page`

## Images

Images with disallowed domains are always completely removed (the entire `<img>` tag or `![alt](url)` markdown). Rewrite mode only affects links, not images.

The reason for this is that images can be used to exfiltrate data or track users without their knowledge, just by being loaded in the browser. Therefore, it's safer to remove them entirely if they come from untrusted sources.

## Usage in Code

```php
// Inject the service.
$hostnameFilter = \Drupal::service('ai.hostname_filter_service');

// Filter AI-generated content.
$filteredOutput = $hostnameFilter->filterText($aiGeneratedContent);
```

## Programmatic Configuration (for testing)

```php
// Set allowed domains programmatically.
$hostnameFilter->setAllowedDomains(['example.com', '*.cdn.example.com']);

// Enable/disable rewrite mode.
$hostnameFilter->setRewriteLinks(TRUE);

// Filter content.
$result = $hostnameFilter->filterText($content);
```
