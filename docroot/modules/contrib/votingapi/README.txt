CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Requirements
 * Installation
 * Configuration
 * Maintainers


INTRODUCTION
------------

VotingAPI is a framework for content voting and rating systems in Drupal. It
does not directly provide any voting 'features' to users -- instead, it offers a
consistent API for other module developers to build their voting and rating
systems on top of. If you're an end user who just wants to rate nodes, check out
some of the modules that use VotingAPI's framework:


It supports:

 * Rating of any content (comments, nodes, users, fish, whatever)
 * Multi-criteria voting (rate a game based on video, audio, and replayability)
 * Automatic tabulation of results (with support for different voting styles,
   like 'percentage' and '+1/-1')
 * Efficient caching of results (sorting and filtering doesn't require any
   recalculation)
 * Hooks for additional vote calculations

 * For a full description of the module visit:
   https://www.drupal.org/project/votingapi

 * To submit bug reports and feature suggestions, or to track changes visit:
   https://www.drupal.org/project/issues/votingapi

For more information visit: https://www.drupal.org/node/68851

REQUIREMENTS
------------

This module requires no modules outside of Drupal core.

INSTALLATION
------------

 * Install the Voting API module as you would normally install a contributed
   Drupal module. Visit https://www.drupal.org/node/1897420 for further
   information.

CONFIGURATION
-------------

    1. Navigate to Administration > Extend and enable the module.
    2. Navigate to Administration > Configuration > Search and Metadata > Voting
       API Settings to configure Voting API settings.
    3. Configure the anonymous vote rollover and the registered user vote
       rollover. On high-traffic sites, administrators can use the Calculation
       schedule setting to postpone the calculation of vote results.
    4. Once the module is enabled, there is a Vote Types entity. Navigate
       to Administration > Structure > Vote Types to add a vote type. Save vote
       type.

MAINTAINERS
-----------

 * Oleksandr Dehteruk (pifagor) - https://www.drupal.org/u/pifagor (active)
 * Pedro Rocha (pedrorocha) - https://www.drupal.org/u/pedrorocha (inactive)
 * Roman Zimmermann (torotil) - https://www.drupal.org/u/torotil (inactive)
 * Jeff Eaton (eaton) - https://www.drupal.org/u/eaton (inactive)

SUPPORTING ORGANIZATION:
-----------

 * GOLEMS GABB
