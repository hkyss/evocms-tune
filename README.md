# evocms-tune

[![Tests](https://github.com/hkyss/evocms-tune/actions/workflows/tests.yml/badge.svg)](https://github.com/hkyss/evocms-tune/actions/workflows/tests.yml)
[![Latest version](https://img.shields.io/packagist/v/hkyss/evocms-tune.svg)](https://packagist.org/packages/hkyss/evocms-tune)
[![PHP](https://img.shields.io/packagist/dependency-v/hkyss/evocms-tune/php.svg)](composer.json)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

Database tuning for Evolution CMS CE 3 ([evocms-community/evolution](https://github.com/evocms-community/evolution)).
Reads the schema an installation actually has, says what is missing and what it carries for
nothing, and applies the difference.

```bash
php artisan db:doctor
php artisan db:tune --dry-run
php artisan db:tune
php artisan db:untune
```

On a stock CE 3.1 install it finds thirty changes: nineteen that pay off on any site, nine that
depend on how the site is used, and two that only make sense once you know nothing searches.

## Why

Evolution's schema is not short of indexes. It is short of the right ones, and carries several
that only cost writes.

`site_content_closure` — the tree mechanism CE 3 introduced — ships with a primary key on
`closure_id` and nothing else, so every subtree query scans it. `site_tmplvar_templates` has a
primary key starting at `tmplvarid`, which the query that runs on every render (*which template
variables does this template have*) cannot use. `membergroup_access` and `site_tmplvar_access`
gate permissions on every request and carry no index but the primary key.

Meanwhile `site_tmplvar_contentvalues` — the busiest write table in the schema — maintains seven
index structures, two of which are leading prefixes of others, plus a fulltext index over a
`mediumtext` column. `site_content` keeps three overlapping indexes on the publish dates and a
fulltext index over a `longtext` column that InnoDB rebuilds on every save.

And `site_tmplvar_contentvalues` has no unique on `(tmplvarid, contentid)`, though `user_values`
enforces exactly that on the user side. That gap is where duplicate template variable rows
come from.

## Install

```bash
cd core
php artisan package:installrequire hkyss/evocms-tune "^1.0"
php artisan db:doctor
```

No migrations. The package changes nothing until you run `db:tune`, which creates one table of
its own — a record of what it changed, so `db:untune` can put it back.

## Tiers

Every rule is filed under one of three tiers, and `db:doctor`/`db:tune` default to the first.

| Tier | What it means |
|---|---|
| `core` | Pays off on any site, applies online, no data risk |
| `extended` | Real but situational — a second access path, a manager-only query |
| `aggressive` | Rebuilds a table and blocks writes while it runs |

```bash
php artisan db:doctor --tier=extended
php artisan db:tune --tier=aggressive --allow-rebuild
```

`--only` narrows a run to one rule or one table:

```bash
php artisan db:tune --only=site_content_closure
php artisan db:doctor --only=tmplvar_contentvalues.pair
```

## Undoing

`db:tune` writes down every change before it applies it, together with the statement that
reverses it, in a `tune_changes` table it creates on first use. `db:untune` replays those newest
first:

```bash
php artisan db:untune --dry-run
php artisan db:untune --only=site_content
php artisan db:untune
```

An index the package added is dropped again. An index it removed is recreated *exactly* as the
schema had it — same columns, same prefix lengths, same uniqueness, fulltext included — because
the definition was read out of `information_schema` before the drop rather than guessed
afterwards. Putting a fulltext index back rebuilds the table, so it needs `--allow-rebuild` the
same way removing it did.

A record only applies while the schema is still where `db:tune` left it. Each undo statement
carries the index it expects to find — or to still be missing — and `db:untune` checks that
before it runs anything. Where something else has moved on since, the record is read as stale
and dropped rather than failing this run and every run after it; a renamed index can never be
put back by a statement that names the old one.

When the last record is undone the table goes with it, and the package leaves nothing behind.

## What it will not do

**Apply anything you have not seen.** `db:doctor` reports, `db:tune --dry-run` prints every
statement, and `db:tune` asks before it runs them.

**Block writes without saying so.** Index changes are emitted with `ALGORITHM=INPLACE, LOCK=NONE`
and refuse to run any other way. Dropping the last fulltext index on a table cannot be done
online — those statements are marked, held back, and need `--allow-rebuild`.

**Assume what Evolution shipped.** Every rule is checked against the live schema first. An index
that already exists, or one a wider index already covers, is reported as satisfied and skipped.
A unique constraint is blocked, with a count, while the data still has duplicates.

**Change what it cannot put back.** Every pending change carries its own undo statement,
computed from the live schema at the moment it is planned. `db:doctor --json` prints those next
to the statements that would be applied.

**Change a column type.** `site_content.parent` is a signed `int` pointing at an `int unsigned`
`id`, and it is not the only one — which is why the schema carries no foreign keys at all.
`db:doctor` lists those under *Not ours to change* and stops there, because a column type is
changed by rewriting the table.

**Guess at redundancy.** Beyond the curated rules the package derives its own: any index whose
columns are the leading part of another index on the same table answers no query the wider one
cannot. That analysis runs against the schema *as it will be after* the additions, which is how
`aliasidx` and `parent` on `site_content` turn out to be redundant once the composite indexes
they should have been are in place. A unique is never treated as redundant against a wider
non-unique index.

## Pruning

Evolution writes to `event_log` and `manager_log` and never removes from them.

```bash
php artisan db:prune --dry-run
php artisan db:prune --days=30 --table=manager_log
```

Retention lives in the config, ninety days for the event log and a hundred and eighty for the
manager log.

## Config

```bash
php artisan vendor:publish --tag=tune-config
```

```php
return [
    'connection' => env('TUNE_CONNECTION'),
    'tier' => env('TUNE_TIER', 'core'),
    'prune' => [
        'event_log' => ['column' => 'createdon', 'days' => 90],
        'manager_log' => ['column' => 'timestamp', 'days' => 180],
    ],
    'prune_batch' => 5000,
];
```

## Before a large table

`db:tune` reports whether the server does index changes online, and every statement it prints
says which kind it is. On a `site_content` of any size, run the additions first, confirm with
`db:doctor`, and leave the drops for a quieter moment — a dropped index cannot be put back
without rebuilding it.

`ANALYZE TABLE` after a batch of changes, so the optimizer sees the new cardinalities.

## Requirements

PHP 8.2+, MySQL 5.6+ or MariaDB 10.0+ for online index changes. Older servers still work; the
package says so and every change becomes a blocking one.

## License

MIT.
