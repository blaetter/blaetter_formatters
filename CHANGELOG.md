# Changelog Blätter Formatters

All notable changes to this module, grouped by topic. New entries go into the "Unreleased" section at the top; on deployment it gets the date.

Sections per version: Features, Security, Bugs, Removed, Maintenance (omit empty sections).

## Unreleased

### Features
- Cookie consent records of EU Cookie Compliance (with IP address) are deleted after six months: a cron job deletes the older records in batches (at most 50,000 per run, `ConsentRetention`); the module itself never deleted them

### Security
- Grid blocks only show views the current user may access
- The copy of a SEPA mandate for the customer only shows the masked IBAN; the complete IBAN is only sent to the shop

### Bugs
- Embedded videos in texts with the format Full HTML can be loaded again: "Convert line breaks" runs after "Embed media" and may wrap the consent button in a paragraph, so the script now finds the placeholder with `closest()` instead of the parent element (`JSON.parse` error, content not loaded after the consent in the cookie banner)
- Embedded videos and other external content were not loaded automatically after the user agreed to external content in the cookie banner: the consent was checked before the cookie banner had read it; it is checked after all behaviors are attached, and the content is loaded right away when the user agrees in the banner (without reload)
- Contact forms without subject and name field (SEPA mandate) failed on Drupal 11 with a TypeError in `Html::escape()` and sent no mails: the subject and the sender name are taken from the first and last name (otherwise the mail address and the label of the form), so the mails have the subject "[SEPA-Lastschriftmandat] First Last"
- New embed and social media blocks showed PHP warnings in their settings form (missing default configuration)
- The social media block stored unchecked providers as 0
- The notification about changed addresses had no recipient without the NodeShop settings; the site mail address is used as fallback
- A grid block referencing a deleted or renamed view broke the whole page; missing views, displays and blocks are now skipped and logged
- Embedded videos and other external content could not be loaded when the cookie banner script was missing, and content loaded later (e.g. via AJAX) got no consent button
- The provider options of media were cached for the first media type and then offered for all others

### Removed
- Honeypot with time restriction on the search form of the search page (from the time of the paid Bing search): it asked users who searched again within 5 seconds to wait and made the search pages uncacheable

### Maintenance
- Config schema for the issue formatter and the grid, social media and embed blocks
- Dependencies in `info.yml`, license and PHP version in `composer.json`, help page describes all features
- Unit tests for the grid block, the block settings, the config schema and the module hooks
- Compatibility with PHP 8.4 (explicitly nullable parameters) and Drupal 12 (original entity via `getOriginal()`)
- Consent script uses Drupal behaviors and declares its dependencies
