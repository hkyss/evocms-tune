# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project follows
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- `db:doctor`, which reports what the live schema is missing and what it carries for nothing.
- `db:tune`, which applies those changes, with `--dry-run`, `--only`, `--tier` and a refusal to
  run anything that blocks writes without `--allow-rebuild`.
- `db:prune`, which trims the audit logs Evolution never removes from.
- A curated ruleset for the Evolution CE 3 core tables, and a redundancy analysis that derives
  its own drop rules from any schema.
