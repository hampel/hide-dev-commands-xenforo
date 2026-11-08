# Testing Hide Development Commands

What this add-on touches, where it breaks quietly, and which checks a session can run on its own
against the ones that need a forum with development and designer mode off. `CLAUDE.md` explains
the mechanism; this file is the checklist.

## Surfaces

| surface | what it is for |
|---|---|
| `app_cli_start_end` → `Listener::appCliStartEnd()` | hides and blocks the families whose mode is off, and hides the commands `config.php` lists, once every command is registered and none has run |
| `hampel_admin_api_redaction` → `Listener::adminApiRedaction()` | claims the `hideDevCommands` key with no secrets in it, so the Admin API shows rather than withholds it |
| `$config['hideDevCommands']['hide']` | names and `*` patterns of commands to hide, never block |
| `RequiresDevModeTrait`, `RequiresDesignerModeTrait` | core's guard traits: another add-on's command that uses one is hidden with its mode |
| `Command::setApplication()`, overridden by `Cli\CapturesConsole` | Symfony's own call when a command is registered: the only point at which the console application can be reached |
| `hide-dev-commands:config` | prints the modes and what each family's state is as a result |
| `hide-dev-commands:validate` | proves the capture, the listener, the families, the block and the hide list |

No class extensions, template modifications, options, phrases or database tables.

## Fragile points

- **Every link in the capture chain is a XenForo or Symfony internal, not an API**: `cmd.php`
  building the runner with `new`, registration before the app starts, and `add()` calling
  `setApplication()`. A break fails safe — the commands are shown again — and `validate` is what
  notices. `CLAUDE.md`, *No XenForo hook reaches the command list*.
- **The `blocked` check reads Symfony's private `Command::$code` by reflection.** A console that
  renames it makes that row fail rather than the block itself.
- **The runner resolves the typed name twice.** Hiding the command being run turns an
  abbreviation into a logged server error. `CLAUDE.md`, *The command being run is blocked but not
  hidden*.
- **A guarded command is found by its trait, not its name.** An add-on that checks the mode by
  hand rather than through a trait is not detected; the `config.php` list is the way to hide it.
- **The families differ by XenForo version.** 2.2 and 2.3.0 Patch 2 have no `xf-make:*`, and 2.2
  has no `xf-dev:info`. `CLAUDE.md`, *Not every family exists on every supported XenForo*.

## Automated

From the add-on root, on a development install:

```bash
vendor/bin/phpunit                                        # 28 tests on 2.3.13
vendor/bin/phpstan analyse                                # level max, no baseline
php ../../../../cmd.php hide-dev-commands:validate --strict; echo $?   # expect 0
```

- **The suite registers all of this XenForo's real commands** through `XF\Cli\Runner`'s own
  `registerCommands()`, turns the modes off with `setConfig()`, and checks:
  - what is hidden for each combination of modes, and that nothing outside the three families is;
  - that `list` and `list xf-dev` show none of them;
  - that an exact name and an abbreviation both refuse;
  - the `validate` exit codes;
  - the secrets declaration.
- **`validate` on a development install proves the capture and the listener**, and that each
  family is still found. With both modes on, it skips the block rows: there is nothing blocked to
  check.

**What none of it reaches is `cmd.php` itself**, where the order of registration, app start and
the listener is what the add-on depends on. The suite runs everything in one process. The
development install has both modes on, because other work needs them.

## Needs a human

A disposable forum with both modes off, on each supported XenForo line, before a release.

### On a sandbox with the modes off

**Use a disposable instance, not a development install**, because a development install needs both
modes on for everything else it is used for. `xenforo-addon-sandbox` builds one. Install the add-on,
then set both modes off in that instance's `config.php`.

```bash
php cmd.php list | grep -c 'xf-dev:\|xf-make:\|xf-designer:'   # expect 0
php cmd.php list xf-dev                                         # expect: no commands in that namespace
php cmd.php xf-dev:inf; echo $?                                 # 2.3 expect: Development mode is not enabled, 1
php cmd.php xf-dev:info; echo $?                                # 2.3 expect: Development mode is not enabled, 1
php cmd.php xf-dev:metadata-fix /tmp; echo $?                   # expect: Development mode is not enabled, 1
php cmd.php xf-dev:import; echo $?                              # expect: its own guard's message, 1
php cmd.php hide-dev-commands:validate --strict; echo $?        # expect: no warn or fail rows, 0
```

**Bracket `xf_error_log` with `MAX(error_id)` before the block and read only rows above it
afterwards.** The only row expected is the one for `list xf-dev`, which a forum without the add-on
logs for any unknown namespace. A row for `xf-dev:inf` is the double-lookup regression in
`CLAUDE.md`.

Then:

- **turn development mode on**, and check `list` shows the `xf-dev:*` and `xf-make:*` commands
  again while `xf-designer:*` stays hidden;
- **turn designer mode on as well**, and check all 129 are listed (on 2.3.13; count `list` before
  installing to have the number for another version);
- **with both modes on, list `xf-rebuild:*` and one exact name** under
  `$config['hideDevCommands']['hide']`, and check `list` leaves them out, that one still runs by
  its exact name, and that `validate --strict` exits 0; then add an entry that matches nothing and
  check it exits 2;
- **where another add-on's command uses a guard trait**, check it is hidden with its mode and
  refuses by name;
- **disable the add-on** with both modes off, and check the full list comes back and
  `hide-dev-commands:validate` refuses as a command of an inactive add-on.

### On XenForo 2.2

**Repeat the first block on a 2.2 sandbox before a release.** 2.2 ships its own fork of Symfony
Console, and the `gray` colour trap in `xenforo-addon-validate` is the precedent for reading 2.2's
source and still being wrong. On PHP 7.4, leave `redis` out of `XF_CONTEXTS`; the sandbox skill
says why.

Three answers differ there, and none is a failure:

- `xf-dev:inf` and `xf-dev:info` print "Command is not defined" with a "Did you mean" list, because
  2.2 has no `xf-dev:info`;
- `validate` shows `xf-make:*` and `xf-dev:info` as blank skips, since 2.2 has neither;
- `xf-dev:metadata-fix /tmp` refuses with "Development mode is not enabled" from this add-on's
  block, since that command has no guard of its own on 2.2.

## The record

**2026-11-08, `1.0.0a1`**: both blocks passed on XenForo 2.3.13 with PHP 8.3.36 and on 2.2.19
Patch 2 with PHP 7.4.33, under `xf-cli` 0.5.1, with the working copy mounted read-only, development
and designer off in `config.override.php`, and the add-on installed by id from `_data/`. That run
found the abbreviation regression and the 2.2 `xf-make:*` warning, both fixed before the
re-run. `validate --strict` exited 0 on both, and the add-on's PHP files linted clean on 7.4.

**2026-11-08, `1.0.0a1` release zip**: installed on production forums with both modes off, the
first install from a built zip rather than the working copy. Every *Needs a human* check passed:
`list` showed none of the three families, `validate --strict` exited 0, `xf-dev:inf` and
`xf-dev:info` refused without logging an error, `config` reported every family hidden and
blocked, and the forums' other CLI commands ran as before.

**2026-11-08, `1.0.0a2`**: the whole *Needs a human* section passed again on XenForo 2.3.13 with PHP
8.3.36 and on 2.2.19 Patch 2 with PHP 7.4.33, set up as for a1, with a scratch add-on whose one
command uses `RequiresDevModeTrait`. That command was hidden with development mode off, refused by
name, and came back with the mode. The hide list hid `xf-rebuild:*` (13 on 2.3, 6 on 2.2) and an
exact name, which still ran by that name; an abbreviation of it logged nothing; an entry matching
nothing gave exit 0, and 2 under `--strict`. The run found `config` reporting itself as not hidden
when listed, fixed before release. The add-on's PHP files linted clean on 7.4.
