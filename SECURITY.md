# Security

## Reporting

Report privately through [GitHub Security Advisories](https://github.com/hkyss/evocms-tune/security/advisories/new)
or by email to hkyss.work@protonmail.com. Please don't use a public issue.

Include the server version, the table involved, and the output of `php artisan db:doctor --json`.
First reply within a week.

Fixes go to the latest `1.x` release.

## What running db:tune does

It runs `ALTER TABLE` against your database with the credentials Evolution is configured with.
Nothing is sandboxed and nothing is transactional — MySQL commits DDL implicitly, so a batch that
fails halfway leaves the earlier statements applied.

Additive changes are safe to interrupt. Drops are not reversible without rebuilding the index,
which is why `db:tune` prints every statement first and asks before it runs them, and why
`--dry-run` exists.

Statements are emitted with `ALGORITHM=INPLACE, LOCK=NONE` and fail rather than silently falling
back to a blocking rebuild. The one exception is dropping the last fulltext index on a table,
which cannot be done any other way; those are held back until `--allow-rebuild`.

## Identifiers

Table and index names come from the ruleset and from `information_schema`, never from user
input, and every one is backtick-quoted with embedded backticks doubled. The table prefix comes
from the connection configuration.

`--only` matches rule ids and table names against the ruleset; a value that matches nothing
selects nothing. It is not interpolated into SQL.

## Before production

Take a backup. Not because this package writes data — it does not touch a row outside
`db:prune` — but because an index change on a large table can take long enough to matter, and
the decision to run it is yours.

Run `db:doctor` on staging first and read the reasons. A rule that does not fit your site is one
`--only` away from being skipped.

## db:prune

`db:prune` deletes rows, permanently, from `event_log` and `manager_log`. It is the only command
here that removes data. `--dry-run` counts without deleting, and retention is configured, not
guessed.

Those tables are an audit trail. If yours is subject to a retention policy, set the days to match
it before running the command, not after.
