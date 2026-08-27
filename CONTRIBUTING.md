# Contributing

## What this package may do

Change the schema of an Evolution CMS installation that someone else owns. Every statement it
emits is one a DBA would have to defend, so the package states the reason, says whether the
operation blocks writes, and never runs anything the operator has not seen first.

Two rules follow from that, and they bound every change made here:

1. **Read before write.** Nothing is applied without inspecting the live schema. A rule that
   assumes what Evolution shipped is a rule that breaks on the first site whose schema was
   touched by an import or an extra.
2. **Reversible.** Every change carries the statement that undoes it, built at plan time from
   the live schema — which is the only moment the definition of an index about to be dropped can
   still be read. `db:tune` records that statement before it applies anything, and `db:untune`
   replays it, but only while the schema is still where it was left. Each undo carries a guard:
   the index it expects to be there, or to still be gone. A record whose guard no longer holds
   is dropped rather than retried — it can never become replayable.

A rule whose undo cannot be built from the schema does not belong in the ruleset. If you find
yourself wanting to hard-code what an index *probably* looked like, that is the signal.

## Running the checks

```bash
composer install
composer check
```

`check` is php-cs-fixer, phpstan at level 6 and phpunit. All three run in CI on PHP 8.2 through
8.4, and on the lowest Illuminate the package claims to support.

## The two layers

Everything that decides *what* to change is pure: it takes an index inventory and returns
findings, and it is tested without a database. Everything that needs a live connection sits
behind `SchemaReader`. Keep the seam there — a rule that can only be tested by connecting to
MySQL is a rule nobody will test.

`tests/Unit/Fake/SchemaFake.php` is how the planner is exercised. Give it the indexes a table
has, and assert on the plan.

## The end-to-end check

`tests/Schema/plan.php` loads a real schema, applies the whole plan at the aggressive tier, plans
again, then undoes everything from the journal and compares the index inventory to the one it
started with.

Four things have to hold. The second pass comes back empty, which proves the statements are valid
SQL on a real server and that `db:doctor` converges instead of proposing the same change forever.
The inventory after `db:untune` is identical to the baseline, down to prefix lengths and fulltext
indexes. The plan is the same size again. And the prune tables still resolve through the
connection prefix — a double-prefixed table name is the mistake this catches.

Run it against any MySQL you can throw away:

```bash
mysql -h 127.0.0.1 -uroot -proot -e 'CREATE DATABASE evolution'
mysql -h 127.0.0.1 -uroot -proot evolution < tests/Schema/baseline.sql
php tests/Schema/plan.php
```

`TUNE_TEST_HOST`, `TUNE_TEST_PORT`, `TUNE_TEST_DATABASE`, `TUNE_TEST_USERNAME`,
`TUNE_TEST_PASSWORD` and `TUNE_TEST_PREFIX` override the defaults.

`tests/Schema/baseline.sql` is the structure of one Evolution CE 3.1 installation, not a
canonical artifact of the project. Regenerate it with:

```bash
mysqldump -uroot -proot --no-data --skip-add-drop-table --skip-comments --compact <database> \
  | sed 's/ AUTO_INCREMENT=[0-9]*//' > tests/Schema/baseline.sql
```

## Adding a rule

A rule earns its place by naming the query it serves. Put that in `reason` — it is printed to
whoever is about to change their production schema, and it is the only thing they have to judge
it by. Rules whose reason reads *this looks like it should be indexed* do not go in.

Then pick a tier honestly:

- `core` — pays off on any site, applies online, no data risk.
- `extended` — real but situational.
- `aggressive` — rebuilds a table.

A `core` rule that blocks writes is a bug, and `RulesetTest` fails on it.

Redundant indexes do not need rules. The analyzer derives those from any schema, and a curated
drop rule is only for an index that is *not* redundant and is still not worth its writes.

## Commits

Imperative and lower-case after the type: `feat:`, `fix:`, `docs:`, `ci:`, `chore:`. The body
carries the reasoning when the subject cannot.
