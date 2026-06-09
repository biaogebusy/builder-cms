# Publishing a Security Release
This document outlines the steps the Preparation Manager needs to take to pre-publish a new security release of the AI module. When publishing a security release for the AI module, follow these steps to ensure a smooth and efficient process.

## Create the Tag
1. [Tag the security release](tagging_a_release.md) and push it up to the remote repository
2. Visit https://git.drupalcode.org/project/ai/-/tags and verify that the tag corresponds to the correct version number and includes all intended changes.
3. Check the diff between the last release and the new tag to ensure it matches the intended changes.
4. Verify in the tag branch that the following UIs have been built and committed:
   - `ui/mdxeditor/dist/assets`
   - `ui/default-tools-editor/dist/assets`
   - `ui/json-schema-editor/dist`
   - `modules/ai_ckeditor/js/build`

## QA Test
1. Review the list of issues and merge requests that were intended to be included in the security release.
2. Try out and verify that the issues have been resolved in the codebase.
3. A full test of the module is not required for security releases, but if you have time, it is recommended to do a quick smoke test of the main functionality.

## Pre-Publish a Release
1. The release of the security release should be coordinated with the [Drupal Security Team](https://www.drupal.org/drupal-security-team) to ensure it is not made public until they have reviewed and approved the release. Normally this happens on Wednesdays before 16:00 UTC.
2. Go to the AI module project page on Drupal.org: https://www.drupal.org/project/ai
3. Click the "Add new release" link on the bottom of the page.
4. Select the newly created tag from the "Version" dropdown menu and click next.
6. Copy the [CHANGELOG.md](../../changelog.md) release notes into the "Release notes" field.
7. We usually do not fill out the "Short description" field for security releases.
8. **IMPORTANT** Check the "Security update" box.
9. Another verification checkbox will appear to confirm that the release is coordinated with the Drupal Security Team - check that as well.
10. Click Preview to verify the HTML formatting looks good.
11. Click Save to publish the release.

## Communicate the Release to Security Team
1. Let the security team know that the release has been pre-published and is ready for their review on the security issue on Drupal.org.

## Communicate the Release
1. Ensure the release is officially published on Drupal.org
2. [Communicate the release on Slack](communicating_a_release.md)
