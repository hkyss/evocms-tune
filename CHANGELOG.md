# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project follows
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
