# Testing expai

expai has one PHPUnit suite (`extension/expai/phpunit.xml.dist`). It runs the unit tests of `tests/phpunit/unit`
and, through a wrapper, every script test of `tests/` the way its header says. It runs with the PHPUnit of the
Exponential installation that expai is installed in. Nothing is installed into the extension.

## Running it

From the installation root, as the web server's user (the caches the tests write must stay readable to it):

```
php vendor/bin/phpunit -c extension/expai/phpunit.xml.dist                        # everything except the provider group
php vendor/bin/phpunit -c extension/expai/phpunit.xml.dist --testsuite unit       # the unit tests only
php vendor/bin/phpunit -c extension/expai/phpunit.xml.dist --testsuite scripts    # the script tests only
php vendor/bin/phpunit -c extension/expai/phpunit.xml.dist --exclude-group e2e    # without the mock end-to-end runs
php vendor/bin/phpunit -c extension/expai/phpunit.xml.dist extension/expai/tests/phpunit/unit/core   # one directory
```

The bootstrap (`tests/phpunit/bootstrap.php`) starts the installation the same way `bin/php/ezexec.php` does, on
the siteaccess `EXPAI_TEST_SITEACCESS` (default `admin`). `EXPONENTIAL_ROOT` names another installation. Mail of
the whole run goes to the file transport, whatever the site's settings say. Without a database or without the expai
tables, the tests that need them are skipped and give the reason. The rest still runs. A test that reaches the
database without asking for it first is skipped the same way in a DB-less run (PHPUnit 13 and later).

The run does not depend on the site it runs on: messages and numbers are compared in English (the run sets
`RegionalSettings Locale=eng-GB` and `TextTranslation=disabled` in memory), the site's `[DataPolicy] DefaultRegions`
is emptied (every test of the data policy sets its own rule), and the script runner uses `timeout` or `gtimeout`
when one is installed. A German siteaccess with `DefaultRegions[]=eu;local` gives the same result as an English one.

The structure detector of the edit form (`design/admin/javascript/expai_structure.js`) has a test of its own that
runs with node (20 or later), without a browser; `expAiStructureScriptTest` runs it from the unit suite when node is
installed and is skipped otherwise:

```
node --test extension/expai/tests/js/expai_structure.test.mjs
```

The script tests can still be run on their own, exactly as before:

```
php bin/php/ezexec.php extension/expai/tests/expaicost_test.php
php bin/php/ezexec.php extension/expai/tests/expaigate_test.php -s admin      # EXPAI_TEST_LOGIN=admin
```

## Suites and groups

| Suite / group | What | Needs |
| --- | --- | --- |
| suite `unit` | `tests/phpunit/unit/<area>/expAi*Test.php`: the classes of `classes/`, one test class per class or topic | some need the database |
| suite `scripts`, group `scripts` | the 20 unit-style script tests of `tests/`, each in a process of its own | the database for 10 of them |
| group `e2e` | the end-to-end scripts against the mock servers of `tests/mock` (providers, fallback, chains, lanes and batches, the expmcp bridge), started on free loopback ports | the database |
| group `provider` | `expaitranslationsync_e2e_test.php`, which calls a real model and costs money | excluded by default; `--group provider` with `EXPAI_TEST_ALLOW_PAID=1` |

A wrapped script passes when it exits with 0, ends with PASS and prints no FAIL line. It must also report at least
as many `ok` checks as it had when it was brought into the suite, so a check that disappears fails the run. Where the
closing line counts the checks, each one must have printed `ok`.

## What the tests may touch

- **Content**: only below a hidden folder below node 2 that the test makes (`expAiTestCase::folder()`,
  `expAiScriptFixture::folder()`). After the test, everything below it is removed, not moved to the trash. That
  covers what a script moved to the trash, the drafts it wrote, their log rows, conversations and threads.
- **Rows**: written inside a transaction that is rolled back (`expAiTestCase::inTransaction()`), or removed again in
  `cleanup()`. Rows of the mock providers that exist only in a script's memory (usage, queue, batch, chain and probe
  rows) are removed after the script.
- **Settings**: `expAiTestCase::config()` builds on the settings expai ships, with values set in memory. The site's
  override and its keys are not read by the unit tests, and no test prints a key.
- **Audit trail**: the kernel's audit writes into a throwaway directory with keys of its own for the run
  (`expAiTestAudit`), in the PHPUnit process and in every script test (`EXPAI_TEST_AUDIT_DIR`, set by
  `tests/phpunit/support/script_prepend.php`). It never writes into the site's hash-chained channel files.
  `expaiaudit_test.php` uses its own throwaway directory below `var/tmp/audit-tests/`.
- **Network**: only 127.0.0.1 (the mock servers and a local file server). No test of the default run calls a provider.
- **Mail**: the file transport only.

## Measuring coverage

PHPUnit measures with Xdebug or PCOV. Neither has to be loaded otherwise: load Xdebug for the coverage run only.
The script tests run in processes of their own. While the suite measures coverage they run with Xdebug too
(`tests/phpunit/support/coverage_prepend.php`, loaded by `script_prepend.php`), and what they ran in `classes/` is added to the coverage of the test
that ran them. Give the Xdebug module in `EXPAI_XDEBUG_SO` so the scripts load the same one:

```
X=/path/to/xdebug.so
XDEBUG_MODE=coverage EXPAI_XDEBUG_SO=$X php -d zend_extension=$X -d memory_limit=4G vendor/bin/phpunit \
    -c extension/expai/phpunit.xml.dist --path-coverage \
    --coverage-html var/tmp/expai-coverage --coverage-text=var/tmp/expai-coverage.txt
```

Leave out `--path-coverage` for line coverage only, which is several times faster. Branch figures count only the
files that ran at least once, because PHPUnit's report knows the branches of a file only from a run of it.

## Current figures

Measured on 2026-10-12 on a development installation (SQLite, PHP 8.5, Xdebug 3.5), whole default run: 432 tests
(407 unit tests in 57 test classes, 25 script tests with 1,835 checks between them), 9 minutes with line coverage,
about 1 minute without.

| | Lines of `classes/` |
| --- | --- |
| Before the suite (the script tests only) | 69.0% (11,339 of 16,425) |
| Now (the whole default run) | **96.7%** (16,562 of 17,128) |

Before the suite, 24 files had no line covered at all (the server functions of Vector, the translation sync and
the draft revert, the queue kick, the thread migration, the dashboard, the publisher, the links ...). Now 78 of
177 files are covered completely, and these are the only files below 90%:

| File | Lines | What is not reached, and why |
| --- | --- | --- |
| `queue/expaiqueuekick.php` | 74% | the `popen`/`exec` fallbacks for a PHP without `proc_open`, a start record that cannot be written, and the count of the site's idle workers: reaching them means breaking the host or starting real workers |
| `web/expaiwebguard.php` | 89% | the answers of `dns_get_record()`: they come from the system resolver; the tests give the guard a resolver of their own |
| `expaipackagedraftlistener.php` | 88% | the branch for a draft log row that cannot be stored (a failing database) |
| `expaiexchangerates.php`, `queue/expaiexchangerateshandler.php` | 77%, 0% | added on the same day by other work; their tests come with them |

Branch coverage was not measured for the whole run: with `--path-coverage`, the largest functions of the
assistant, the chain engine and Vector's server functions have so many paths that one unit test class took more
than ten minutes, so a run of the suite does not end in a usable time. Measure branches for one directory or class
at a time (`--path-coverage --filter ...`).

## Before a release

- The default run passes, and `--group provider` with `EXPAI_TEST_ALLOW_PAID=1` once against the release's default
  model.
- Coverage of `classes/` is at least 90% of lines. Update the figures above.
- A new class gets its test in `tests/phpunit/unit/<area>/`. A new script test gets its row in
  `tests/phpunit/scripts/expAiScriptTestsTest.php`, with its current number of checks.
