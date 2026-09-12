
***
## <a name="updating"> </a>Standard Operating Procedure (SOP) for Updates

> **Documentation Scope:** This documentation is comprehensive to serve as a
definitive resource and reduce repetitive support inquiries. If you are an
experienced site-builder, feel free to bypass the foundational steps. However,
for those seeking a guaranteed stable deployment, these procedures are
mandatory.

### Quick Start: Update Commands

| Scenario | Primary Command / Action |
| --- | --- |
| **Major Upgrade (2.x to 3.x)** | `composer require drupal/blazy:^3.0 -W -n` <br /> See Blazy project home [**Upgrade Path**](https://www.drupal.org/project/blazy#blazy-upgrade) |
| **Standard Update (Drush)** | `drush cr && drush updb && drush cr` |
| **Asset Issues (CSS/JS UI)** | Toggle Aggregation in [**Performance Page**](/admin/config/development/performance) with **Clearing all caches** button |
| **WSOD Emergency Recovery** | Delete `composer.lock` & `/vendor`, then `composer install -W -n` |

---

### Full Update SOP

> [!IMPORTANT]
> **The Golden Rule of Updates:**
>
> Strict adherence to the order of operations below is required. If your site
becomes unstable, **do not uninstall the module** (which destroys
configuration). Instead, downgrade to your previous version, clear all caches,
and restart this SOP from step one.

#### 1. Update via Composer
For major version upgrades (e.g., 2.x to 3.x), you must perform a
**parallel upgrade** to ensure all dependencies resolve simultaneously.

* **Main Module only:**
```bash
   composer require drupal/blazy:^3.0 -W -n
```

* **With Sub-modules (if installed):**
```bash
   composer require drupal/blazy:^3.0 drupal/slick:^3.0 drupal/slick_views:^3.0 -W -n
```

**Note:** The `-W` (with-dependencies) and `-n` (no-interaction) flags ensure a
smooth, automated update of the entire tree.

#### 2. Update via Drush (The Preferred Method)
Once composer is done, ​execute this specific sequence to ensure the container
and database are synchronized:

```bash
drush cr && drush updb && drush cr
```

The first `drush cr` ensures the new code is (re-)mapped correctly in
`../files/php`. Failing to do this is the major error reason.

#### 3. Update via UI (Manual / No-Drush)
If you do not have access to Drush, follow these steps in strict order.
**Preparation (backup) is vital**.

1. **Staging First:**

   Never update Production directly. Test on a Dev/Staging environment and
   ensure you have a fresh backup (e.g., via
   [backup_migrate](https://drupal.org/project/backup_migrate)). If you override
   asset or template files, be sure to cross-check against the latest releases
   for any potential changes (see the relevant **Change Records** links from the
   ecosystem project homes), and re-adjust them accordingly. Major releases may
   have potentially breaking changes to leverage either Core upgrade
   requirements or any internal major betterment like seen from Blazy 2.17 to
   3.x, see more details if any provided at
   [Admin status](/admin/reports/status).

2. **Maintenance Mode:**

   Place the site in [Maintenance Mode](/admin/config/development/maintenance).

3. **The "Safety Tab":**

   Open the [**Performance Page**](/admin/config/development/performance) in a
   separate browser tab. Do not close or reload this tab. This is
   your emergency access to clear caches if the rest of the UI breaks.

4. **Download Files:**

   Replace the module files via the
   [Update UI](/admin/modules/update), FTP or Composer.

5. **Pre-Update Cache Clear:**

   Before running any database updates, hit "**Clear all caches**". This ensures
   the new code is mapped correctly in `../files/php`. Failing to do this is the
   major error reason.

6. **Run Updates:**

   Navigate to `/update.php` in your browser and execute pending tasks.

7. **Post-Update Cache Clear:**

   Clear all caches a second time.

8. **Rebuild Assets:**

   Only if you see CSS/JS issues and regular cache clearing fails, toggle
   aggregation on the
   [**Performance Page**](/admin/config/development/performance) to force a
   regeneration assets.

9. **Verification:**

   Verify the latest status at [Admin status](/admin/reports/status) and view
   your site.

---

## <a name="wsod"> </a>Emergency Recovery (WSOD)
This might or might not be related to Blazy updates. If you encounter a
**White Screen of Death** (WSOD) that a standard cache clear cannot fix, perform
a total environment rebuild:

1. Rename or delete the `composer.lock` file and the `/vendor` directory.
2. Run `composer update -W -n` to reinstall a clean dependency tree.
3. **Flush File System:** If assets are corrupted, manually delete:
    * `web/sites/default/files/css`
    * `web/sites/default/files/js`
4. Run the Drush "Power Cycle": `drush cr && drush updb && drush cr`.
5. If WSOD persists, capture the error message/log; search or post it to any
   identified module mentioned in it.

**Note on Stability:**
Alpha, Beta, and DEV releases are for development environments. Always align
your versions (Dev-to-Dev, Stable-to-Stable) as outlined in the
[Version compatibility](#first).

---

## <a name="d11-compat"> </a>Drupal 11 Compatibility

Blazy 3.x continues to operate reliably on Drupal 11 in many environments and
remains suitable for existing projects that are already stable.

Blazy 4.x, however, is the branch officially aligned with Drupal 11. It
formalizes compatibility by:

- Updating hook implementations to follow current Drupal 11 standards
- Removing deprecated APIs
- Streamlining and modernizing internal architecture

While immediate migration is not required for sites where Blazy 3.x is
functioning well, projects planning long-term Drupal 11 development are
encouraged to evaluate Blazy 4.x.

Adopting 4.x ensures alignment with the current Drupal API direction and
positions projects for future enhancements and ongoing maintenance improvements.

---

##  <a name="4x-upgrade"> </a>Upgrade Path: 3.x → 4.x

Blazy 4.x is a major release that formalizes Drupal 11 alignment and removes
previously deprecated APIs. While most runtime behavior remains consistent, some
internal classes and deprecated methods introduced in 3.x have been cleaned up
as part of this release.

For many sites using default configurations, upgrading from 3.x to 4.x should be
straightforward. Projects with custom integrations or extensions are encouraged
to review the documented deprecations and API adjustments before upgrading.

A detailed list of changes, deprecated components, and their replacements is
available in the Change Record:

→ See the full <a href="https://www.drupal.org/node/3575429">Change Record</a>
  for 4.x.


---
<a href="#top">Back to Top &uarr;</a>
---
