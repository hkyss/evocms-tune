# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project follows
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
