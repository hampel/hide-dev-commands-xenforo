# Testing Hide Development Commands

The checks the PHPUnit suite cannot make: it turns the modes off with `setConfig()` inside one
process, but it never goes through `cmd.php`, where the order of registration, app start and the
listener is what the add-on depends on.

## On a sandbox with the modes off

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
- **disable the add-on** with both modes off, and check the full list comes back and
  `hide-dev-commands:validate` refuses as a command of an inactive add-on.

## On XenForo 2.2

**Repeat the first block on a 2.2 sandbox before a release.** 2.2 ships its own fork of Symfony
Console, and the `gray` colour trap in `xenforo-addon-validate` is the precedent for reading 2.2's
source and still being wrong. On PHP 7.4, leave `redis` out of `XF_CONTEXTS`; the sandbox skill
says why.

Three answers differ there, and none is a failure:

- `xf-dev:inf` and `xf-dev:info` print "Command is not defined" with a "Did you mean" list, because
  2.2 has no `xf-dev:info`;
- `validate` shows `xf-make:*` and `xf-dev:info` as blank skips, since 2.2 has neither;
- `xf-dev:metadata-fix /tmp` refuses with "Development mode is not enabled" from this add-on's block,
  since that command has no guard of its own on 2.2.

## The record

**2026-11-08, `1.0.0a1`**: both blocks passed on XenForo 2.3.13 with PHP 8.3.36 and on 2.2.19
Patch 2 with PHP 7.4.33, under `xf-cli` 0.5.1, with the working copy mounted read-only, development
and designer off in `config.override.php`, and the add-on installed by id from `_data/`. That run
found the abbreviation regression and the 2.2 `xf-make:*` warning, both fixed before the
re-run. `validate --strict` exited 0 on both, and the add-on's PHP files linted clean on 7.4.
