# Release Notes
We have two ways to generate release notes for the AI module - the AI Release Notes project is the preferred method because it tags all credit holders for the release, not just code contributors, but you can also use [Drupal Module Release Notes](https://drupal-mrn.dev/) if the other option turns out to be too difficult.

## Using the AI Release Notes project
1. Visit https://github.com/ivanboring/ai-release-notes and clone the repository.
2. Run `composer install` to install the dependencies.
3. Create a complete list of all issue numbers included in the release. See the project's readme for more details on how to do this. You can use GitLab or `git log` to get the commit messages between two tags and extract the issue numbers into a text file.
4. Run the script to get release notes from Drupal.org - `php get_release_notes.php`. This might take some time, because it retrieves data from Drupal.org via a browser.
5. Write the release notes in plain text format using `php write_release_notes.php ai <previous_version>`, replacing `<previous_version>` with the most recent release version. If you want HTML format instead, add `true` as a third argument - so it would be `php write_release_notes.php ai <previous_version> true`.
6. The release notes will be printed to the console. Copy them and use them when creating the tag.
7. If there are any issues tagged Breaking Changes, or `(BC)`, ensure you add a section highlighting them as [Breaking Changes](breaking_changes.md)
8. Ensure the release notes are represented in [CHANGELOG.md](../../changelog.md)

## Using Drupal Module Release Notes (simple)
1. Go to https://drupal-mrn.dev/ and enter in the project name (ai), previous version, and new version.
2. Click on "Generate Release Notes".
3. Copy the generated release notes in plain text format and use them when creating the tag or copy the HTML version by clicking "Source" instead of "Preview".
4. If there are any issues tagged Breaking Changes, or `(BC)`, ensure you add a section highlighting them as [Breaking Changes](breaking_changes.md)
5. Ensure the release notes are represented in [CHANGELOG.md](../../changelog.md)

## Example

The following is an example of Release Notes built for the AI Module...

> Issues resolved since [1.2.0-rc2](https://www.drupal.org/project/ai/releases/1.2.0-rc2): 6
>
> ### Contributors
>
> [marcus\_johansson](https://www.drupal.org/u/marcus_johansson) (6), [a.dmitriiev](https://www.drupal.org/u/a.dmitriiev) (2), [abhisekmazumdar](https://www.drupal.org/u/abhisekmazumdar) (2), [maxilein](https://www.drupal.org/u/maxilein) (1), [jurgenhaas](https://www.drupal.org/u/jurgenhaas) (1), [yautja\_cetanu](https://www.drupal.org/u/yautja_cetanu) (1), [littlepixiez](https://www.drupal.org/u/littlepixiez) (1), [valthebald](https://www.drupal.org/u/valthebald) (2), [svendecabooter](https://www.drupal.org/u/svendecabooter) (2), [mrdalesmith](https://www.drupal.org/u/mrdalesmith) (1), [wouters\_f](https://www.drupal.org/u/wouters_f) (1), [rhristov](https://www.drupal.org/u/rhristov) (1), [annmarysruthy](https://www.drupal.org/u/annmarysruthy) (1), [apmsooner](https://www.drupal.org/u/apmsooner) (1)
>
> ### New Features
>
> - [#3549153](https://www.drupal.org/project/ai/issues/3549153) Translate: use prompt entities instead of custom configurations
>
> ### Bugs
> - [#3550934](https://www.drupal.org/project/ai/issues/3550934) API Explorer should set structured json schema on input not provider.
> -  [#3550929](https://www.drupal.org/project/ai/issues/3550929) AI Logging should output the raw output on streaming
> - [#3551753](https://www.drupal.org/project/ai/issues/3551753) The tool explorer doesn't update when required fields are not set
> - [#3550366](https://www.drupal.org/project/ai/issues/3550366) When upgrading to this module from AI Core - ECA models with a chat action are deleted without warning.
> - [#3503980](https://www.drupal.org/project/ai/issues/3503980) The translation submodule does not respect the content translation module permissions
>
> ### Upgrade Path
>
> - [#123456](https://www.drupal.org/project/ai/issues/123456) AI Banana module is a new dependency. Run `composer update drupal/ai -W` to resolve dependencies across the AI module
>
> ### Organizations
>
> FreelyGive (6), 1xINTERNET (2), Dropsolid (4), LakeDrops (1), Zoocha (1), Sven Decabooter (2), EntityOne (2), Make It Fly (2), Calibrate (1), Bulcode (1), QED42 (1), Drupal India Association (1)
>
> ### Stats
>
> **Amount of contributors:** 14
>
> **Amount of organizations:** 12
>
> **Amount of issues:** 6