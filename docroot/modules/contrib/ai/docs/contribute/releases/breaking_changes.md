# Breaking Changes

In general, the AI Module supports upgrading between releases with [Update Hooks](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Extension%21module.api.php/function/hook_update_N/11.x). Though, there can be breaking changes that are not supported by update hooks that must be communicated as such. This outlines how the AI module highlights these through the release cycle and through [change records](https://www.drupal.org/about/core/policies/core-change-policies/change-records).

## Process

When there is a change that is not supported by an upgrade hook...

1. Tag the related issue with the [`Breaking Change (BC)`](https://www.drupal.org/project/issues/search?projects=AI+(Artificial+Intelligence)&issue_tags=Breaking+Change+(BC)) tag.
2. Append `(Breaking Change)` to the issue title so that it's easily caught within the release notes. For example:
    > "Add AI Banana module (Breaking Change)"
3. In the issue description, ensure there are steps documented for users to handle the breaking change. For example:

    ```
    <h3>Breaking Change</h3>
    <p>There is a new dependency on the AI Banana module.</p>
    <h3>Upgrade Path</h3>
    <p>Make sure you re-run <code>composer update drupal/ai -W</code> to ensure the dependency tree resolves correctly.</p>
    ```

4. In order to adhere to [Semantic Versioning](https://semver.org/), it will require a new major version, as to somewhat align with [how Drupal core handles its breaking changes](https://www.drupal.org/about/core/policies/core-change-policies/allowed-changes#s-alphas-for-major-versions).
5. When generating the [release notes](release_notes.md), if there are any issues marked as `(Breaking Change)` copy the Upgrade Path instructions directly into the release notes:

    > ### Upgrade Path
    > [#123456 Add AI Banana module](https://drupal.org/node/123456)
    >
    > - Breaking Change: There is a new dependency on the AI Pirate module
    > - Upgrade Path: Make sure you re-run <code>composer update drupal/ai -W</code> to ensure the dependency tree resolves correctly.

6. Proceed with the [major release process](publishing_a_minor_major_release.md) as usual
7. Create a [new Change Record](https://www.drupal.org/node/add/changenotice?field_project=3346420) documenting the breaking change...
    - Title: The issue name
    - Published: Enabled
    - Introduced in branch: The active branch that the tag is built from
    - Introduced in version: The new version that is being published
    - Issue links: Link to the relevant issue
    - Description: Include both the Upgrade Path instructions you had built prior

## See Also

- [`Breaking Change (BC)` Tag](https://www.drupal.org/project/issues/search?projects=AI+(Artificial+Intelligence)&issue_tags=Breaking+Change+(BC))
- [Release Notes](release_notes.md)
- [Change Records for the AI Module](https://www.drupal.org/list-changes/ai)
- [Change Record Documentation](https://www.drupal.org/about/core/policies/core-change-policies/change-records)