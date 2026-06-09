# Publishing a Minor/Major Release
This document outlines the steps the Publishing Manager needs to take to publish a new minor or major release of the AI module. When publishing a minor or major release for the AI module, follow these steps to ensure a smooth and efficient process.

## Create the Tag
1. [Tag the minor/major release](tagging_a_release.md) and push it up to the remote repository
2. Visit https://git.drupalcode.org/project/ai/-/tags and verify that the tag corresponds to the correct version number and includes all intended changes.
3. Check the diff between the last release and the new tag to ensure it matches the intended changes.
4. Verify in the tag branch that the following UIs have been built and committed:
   - `ui/mdxeditor/dist/assets`
   - `ui/default-tools-editor/dist/assets`
   - `ui/json-schema-editor/dist`
   - `modules/ai_ckeditor/js/build`

5. No code confirmation should be needed for minor/major releases, since these should have been fully tested by QA.

## Publish a Release
1. The time for the release should have been agreed upon with the marketing team in advance to ensure proper communication.
2. Go to the AI module project page on Drupal.org: https://www.drupal.org/project/ai
3. Click on the "Add new release" link on the bottom of the page.
4. Select the newly created tag from the "Version" dropdown menu and click next.
5. Copy the [CHANGELOG.md](../../changelog.md) release notes into the "Release notes" field.
6. We usually do not fill out the "Short description" field for minor/major releases.
7. Check the "Bug fixes" or "New features" boxes as appropriate.
8. Click Preview to verify the HTML formatting looks good.
9. Click Save to publish the release.

## Communicate the Release
1. Ensure the release is officially published on Drupal.org
2. [Communicate the release on Slack](communicating_a_release.md)
