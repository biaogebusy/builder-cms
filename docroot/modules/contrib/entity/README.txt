Entity API module
-----------------

Provides improvements and extensions to the Drupal 8 Entity system.
Acts as a staging ground for Drupal core, with each core minor release (8.5, 8.6, 8.7)
receiving a portion of this module's functionality.

Current functionality:
- Local action providers (core issue: #2976861)
- Local task providers
- Permission providers (core issue: #2809177)
- Query access API (Change record: https://www.drupal.org/node/3002038, core issue: #777578)
- Bundle plugin API (plugin-based entity bundles, currently not proposed for core inclusion)
- A generic UI for revisions (WIP, see #2625122)
- Duplicate entity UI
- EntityViewsData handler with many improvements over the one in core.
