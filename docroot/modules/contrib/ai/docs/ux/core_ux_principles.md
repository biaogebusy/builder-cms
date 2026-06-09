# Core UX Principles

# AI User Experience

The Drupal AI initiative prioritizes creating intuitive tools with a consistent, predictable experience across the ecosystem. 

UX issues are often not contained to a single module or issue queue. Improving the experience across interconnected tools requires coordination, shared patterns, and dedicated processes.

## Primary Audience: Site Builders

While Drupal has historically been developer-first, the AI initiative designs for site builders, content editors, and business owners as the primary audience. Interfaces and terminology should be clear to someone who understands building websites but may not be a developer or AI expert.

## Secondary Audience: Developers

Developers are a critical audience whose needs are met through descriptions, help text, and documentation rather than primary UI labels.

## Naming Principles

When naming features or writing labels, follow this priority order:

1. **Accuracy** - Names must be technically correct and not misleading.

2. **Site-builder friendliness** - Labels should be short, clear, and use industry-standard AI terminology where it exists. Avoid Drupalisms like "nodes," "entities," or "hooks" in primary labels.

3. **Developer context in descriptions** - Technical details, Drupal-specific terms, and implementation notes belong in descriptions or help text, not labels.

4. **Benefit-oriented language** - Explain what the user gets, not just what the feature is.

**Before:** Vector DBs Settings

**After:** Vector Database Configuration   
*Connect vector databases like Pinecone or Milvus to enable semantic search that finds content by meaning, not keywords.*

## Managing UI Complexity

**First impressions matter** - Users judge a system in seconds. Look for opportunities to modernize interfaces and create moments of delight.

**Lead with benefits** - What AI offers should be immediately visible, not buried at the end of a confusing path through overloaded screens.

**Consistency** - Reuse effective UI patterns across the ecosystem. If a pattern works well in one module, adopt it in others. Our long-term goal is to have a pattern library for the admin UI.

**Progressive disclosure** - Hide complexity until needed using accordions, tooltips, and modals to keep interfaces clean.

**Hierarchy over flat lists** - Group related items into logical categories (Infrastructure → Tools & Automation → Features) rather than presenting long flat lists.

