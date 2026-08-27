# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project follows
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.3.0] - 2026-08-27

### Changed

- `db:tune` and `db:untune` read the statistics again on every table they changed, in one
  `ANALYZE TABLE`, instead of advising it. An index the optimizer has no statistics for is one
  it may decline to use, so a change without them is applied but not necessarily in effect.
  `--no-analyze` leaves them alone.

## [1.2.0] - 2026-08-27

### Added

- Rules for the tables the first ruleset walked past. `active_users`, whose who-is-online list
  is swept by `lasthit` and whose primary key starts at the session id. `site_plugin_events`,
  `user_role_vars`, `site_module_access` and `site_module_depobj`, each carrying nothing but a
  primary key that leads with the column nobody asks by. And the name lookups on the six
  element tables, which the site cache answers at runtime and nothing answers while it is
  being rebuilt.
- `db:doctor` reports columns that cannot point at what they point at: `site_content.parent` is
  a signed `int` against an `int unsigned` `id`, and it is not alone. It reports and stops
  there — a column type is changed by rewriting the table, which is not what this package does,
  and the mismatch is why the schema can carry no foreign keys.

### Fixed

- The record table is created with the collation the rest of the schema uses rather than the
  database default, and one created before this is converted in place. A table this package
  leaves behind should read like the schema around it.

## [1.1.0] - 2026-08-27

### Changed

- `db:untune` reads a record the schema has moved past as stale rather than failing on it.
  Every undo statement now carries the index it expects to find, or to still be missing, and a
  record whose index has since been renamed or dropped by something else is reported and
  dropped instead of failing this run and every run after it.

## [1.0.0] - 2026-08-27

### Added

- `db:doctor`, which reports what the live schema is missing and what it carries for nothing.
- `db:tune`, which applies those changes, with `--dry-run`, `--only`, `--tier` and a refusal to
  run anything that blocks writes without `--allow-rebuild`.
- `db:untune`, which puts back what `db:tune` changed — an added index is dropped, a removed one
  is recreated exactly as the schema had it, from a definition read before the drop.
- `db:prune`, which trims the audit logs Evolution never removes from.
- A curated ruleset for the Evolution CE 3 core tables, and a redundancy analysis that derives
  its own drop rules from any schema.
