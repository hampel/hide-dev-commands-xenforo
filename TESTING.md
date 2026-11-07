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
php cmd.php xf-dev:inf                                          # expect: not defined
php cmd.php xf-dev:info; echo $?                                # expect: Development mode is not enabled, 1
php cmd.php xf-dev:metadata-fix /tmp; echo $?                   # expect: Development mode is not enabled, 1
php cmd.php xf-dev:import; echo $?                              # expect: its own guard's message, 1
php cmd.php hide-dev-commands:validate --strict; echo $?        # expect: every row ok, 0
```

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
source and still being wrong.
