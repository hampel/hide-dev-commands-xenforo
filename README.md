# Hide development commands from XenForo's cmd.php

Hides XenForo's development, make and designer commands from `php cmd.php list` on a forum where
development or designer mode is off, which is to say on production. Each hidden command also refuses
to run when typed by its exact name.

By [Simon Hampel](https://xenforo.com/community/members/sim.4264/).

## Requirements

XenForo 2.2.0 or later, and PHP 7.4 or later.

## What it hides

**Each family follows the mode its own commands need**, which `config.php` sets:

| commands | hidden when | in XenForo 2.3.13 |
|---|---|---|
| `xf-dev:*` | `$config['development']['enabled']` is false | 80 |
| `xf-make:*` | `$config['development']['enabled']` is false | 35 |
| `xf-designer:*` | `$config['designer']['enabled']` is false | 14 |
| another add-on's command that uses `RequiresDevModeTrait` | `$config['development']['enabled']` is false | |
| another add-on's command that uses `RequiresDesignerModeTrait` | `$config['designer']['enabled']` is false | |

**An add-on's command is hidden when it guards itself the way core's do.** XenForo's two guard
traits make a command refuse to run without its mode, so a command that uses one is a development
command whichever add-on it comes from.

Turn the mode on and the commands come back; nothing is stored. Nothing else is hidden unless you
list it in `config.php`, below.

**This tidies the list; it does not close a hole.** All but two of these commands already refuse to
run without their mode. The two that do not are `xf-dev:info`, which prints the database name, user
and paths, and `xf-dev:metadata-fix`, which rewrites a metadata file. With this add-on they refuse
too, with the same message as the others.

A hidden command is not listed by `list`, and `list xf-dev` reports no such namespace. Typed by its
name or an abbreviation, it refuses. `help <command>` still describes it.

**A mistyped name still lists them.** `php cmd.php xf-dev:nope` prints XenForo's "Did you mean one
of these?" with the hidden commands in it, because XenForo looks the name up before any add-on has
loaded. Nothing an add-on can do reaches that point.

## Hiding other commands

**List any command you do not want in `cmd.php list` in `config.php`**, by name or with a `*`
pattern. This applies whatever the modes are.

**A good start on a production forum is the add-on developer's tools**, which build, version and
export an add-on's source. None is guarded by development mode, so they are listed on every forum,
and none has a use on one that only installs add-ons:

```php
$config['hideDevCommands']['hide'] = [
    'xf-addon:build-release',
    'xf-addon:bump-version',
    'xf-addon:create',
    'xf-addon:export',
    'xf-addon:sync-json',
    'xf-addon:validate-json',
];
```

**Leave `xf-addon:install-step`, `upgrade-step` and `uninstall-step` listed.** They re-run one
step of an add-on's setup, which is how a failed install or upgrade is recovered, so they are
for production. The `xf:addon-*` commands are too.

**These are hidden, not blocked.** Each still runs when typed by its exact name, so a cron job or a
script that calls one carries on working. A pattern matches a command's name, not its aliases:
`xf-addon:*` hides all nine `xf-addon:` commands, including the three step commands, and
leaves `xf:addon-install` listed although it also answers to `xf-addon:install`.
`hide-dev-commands:validate` warns about an entry that matches no command.

## Usage

Install it. There are no options; the one setting is the `config.php` list above.

```bash
php cmd.php hide-dev-commands:config      # which modes are on, and what is hidden
php cmd.php hide-dev-commands:config --commands   # also name each hidden command
php cmd.php hide-dev-commands:validate    # prove the hiding works on this XenForo
```

## Validation

**Run `hide-dev-commands:validate` after every XenForo upgrade.** XenForo offers no supported way to
change the command list, so the add-on relies on how its command runner and Symfony Console behave
today. If a release changes that, the commands are simply listed again, and `validate` is what tells
you.

| | all fine | warnings only | any failure |
|---|---|---|---|
| `hide-dev-commands:validate` | 0 | 0 | 1 |
| `hide-dev-commands:validate --strict` | 0 | 2 | 1 |

**This is not the monitoring-plugin numbering**, where 1 is a warning and 2 is critical: 1 always
means a check failed. `--unattended` is accepted for a monitor's command line and changes nothing,
because the command sends and writes nothing.

## Links

- [Source on GitHub](https://github.com/hampel/hide-dev-commands-xenforo)

## Licence

MIT. See `LICENSE.md`.
