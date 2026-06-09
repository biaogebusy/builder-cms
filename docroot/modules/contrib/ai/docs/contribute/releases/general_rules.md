# General Rules

This document outlines the rules, schedules, and responsibilities for releasing updates to the AI module, including release types, timelines, and the current release management team.

## Release Frequency
Releases of patches and security updates for the AI module always happen on Wednesdays, unless a bug is so critical that it needs to be released immediately.

Minor and major releases can be scheduled as needed, but should ideally not conflict with patch release days.

The current patch release cycle is every two weeks on Wednesdays.

So in table form:

| Release Type          | Frequency          | Day       |
|-----------------------|--------------------|-----------|
| Patch Releases        | Bi-weekly          | Wednesday |
| Minor/Major Releases  | As needed          | Any day   |
| Security Releases     | As needed          | Wednesday |

### Issues merge cutoff
For scheduled patch releases, to ensure stability and quality, all issues and merge requests intended for a patch release must be merged into the main branch by the end of the day (UTC) on the Tuesday prior to the release.

For critical patch releases, the cutoff date for merging issues and merge requests will be determined based on the urgency of the fix and communicated internally to the AI Module Maintainers team.

For minor and major releases, the cutoff date for merging issues and merge requests will be determined based on the release schedule and communicated to the development team in advance. However, since we want to give QA ample time to test, a good rule of thumb is to have all issues and merge requests merged at least one week before the planned release date.

For security releases, the cutoff date for merging issues depends on the [Drupal Security Team](https://www.drupal.org/drupal-security-team) and when they see a release as fit.

## Release Roles
The current person/team responsible for managing releases of the AI module is the AI Module Maintainers team. We have split the responsibilities into a three-step process:

1. **Preparation**: This includes making sure that all issues and merge requests are properly reviewed, tested, and merged into the main branch before the cutoff date. After that, they will create release notes and publish the tag. They also have the possibility to revert a release before it is published. Due to timing, security releases are published by this team as well.
2. **QA**: This team is usually only needed for minor and major releases, and is responsible for testing the release candidate to ensure that it meets quality standards and does not introduce any new issues.
3. **Publishing**: This team is responsible for verifying the release and publishing it to the Drupal.org project page. In the case of patch releases, they communicate the release. For minor/major releases, the marketing team takes over the communication part.

## Current Release Managers
The people currently responsible for each part of the release process, in the order of availability they will take the responsibility for the release.

### Preparation

| Name | Drupal.org Profile |
|------|--------------------|
| Marcus Johansson | [https://www.drupal.org/u/marcus_johansson](https://www.drupal.org/u/marcus_johansson) |
| Abhisek Mazumdar | [https://www.drupal.org/u/abhisekmazumdar](https://www.drupal.org/u/abhisekmazumdar) |

### QA

| Name | Drupal.org Profile |
|------|--------------------|
| Will Huggins | [https://www.drupal.org/u/zoocha-will](https://www.drupal.org/u/zoocha-will) |

### Publishing

| Name | Drupal.org Profile |
|------|--------------------|
| Artem Dmitriiev | [https://www.drupal.org/u/admitriiev](https://www.drupal.org/u/admitriiev) |
| Rob Loach | [https://www.drupal.org/u/robloach](https://www.drupal.org/u/robloach) |

### Backup

These are individuals that can step in if none of the people above are available.

| Name | Drupal.org Profile |
|------|--------------------|
| Kevin Quillen | [https://www.drupal.org/u/kevinquillen](https://www.drupal.org/u/kevinquillen) |
| Marcus Johansson | [https://www.drupal.org/u/marcus_johansson](https://www.drupal.org/u/marcus_johansson) |
| Scott Euser | [https://www.drupal.org/u/scott_euser](https://www.drupal.org/u/scott_euser) |
| Jamie Abrahams | [https://www.drupal.org/u/yautja_cetanu](https://www.drupal.org/u/yautja_cetanu) |
