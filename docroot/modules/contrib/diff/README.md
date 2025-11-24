# Diff for Drupal

Allows the user to compare revisions with different layouts.

## Requirements

To use Diff you need to install and activate the following libraries dependencies:
 - php-htmldiff-advanced (Used for displaying the comparison as a rendered entity)

## Installation

To use all the layout plugins provided by Diff please follow the next steps:

 - Use composer to download the required dependencies. Run:`composer require drupal/diff`.
 - Follow the link below for more information about composer and Drupal:
   <https://www.drupal.org/node/2404989>

## Contribution guidelines

The Diff module is used by a very large portion of the community, but is only maintained by a very small team (just one
person as of writing this).

In order to make the triage and management of the issue queue reasonable, the following guidelines need to be followed:

In order to set an issue to Needs review, the issue MUST:
1. Contain an up-to-date Issue summary describing the issue. If it is a bug, it must contain clear steps to reproduce.
2. Have an up-to-date Merge Request against the 2.x branch with all pipelines green in Gitlab CI (phpcs, phpstan, phpunit, etc).
3. If the issue is a bug fix, it must contain test coverage for the bug.
4. If the issue contains UI changes, it must contain before and after screenshots demonstrating the change.

In order to set an issue to Reviewed and Tested by the Community, the issue MUST:
1. Follow all steps above to get to Needs review first.
2. Be reviewed by another member of the community (i.e not the same person who set it to Needs Review and preferably a user from a different Organisation).
3. When setting an issue to RTBC, the user must demonstrate in the comment the steps they have taken to adequately review the issue. (i.e more descriptive than "works well")
