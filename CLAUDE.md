# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

Hide Development Commands is a XenForo 2 add-on (`Hampel/HideDevCommands`) that hides core's
development, make and designer commands from `cmd.php list` when the mode each needs is off, and
makes each refuse to run by name. `README.md` is the user-facing description.

## No XenForo hook reaches the command list, so the add-on borrows Symfony's

**Everything depends on one chain, and each link is an internal rather than an API.** Read
`CommandHider.php`'s docblock before changing any of it; the short form:

- **`cmd.php` builds `XF\Cli\Runner` with `new`**, not `XF::extendClass()`, so a class extension
  cannot reach `registerCommands()` or `canRunCommand()`.
- **Commands are registered before the app starts**, and so before any listener is loaded.
  `Runner::run()` goes `registerCommands()`, `find()`, `setupApp()`, `start()`, then
  `$console->run()`.
- **The CLI events are passed only the `XF\Cli\App`**, never the console application.
- **Symfony's `Application::add()` calls `setApplication()` on every command it registers.**
  `Cli\CapturesConsole` overrides it on both of this add-on's commands and hands the application to
  `CommandHider::capture()`. That is the only point at which add-on code can hold it.
- **`app_cli_start_end` then calls `CommandHider::apply()`**, when every command is registered and
  none has run, so `list` and a named command both see the change.

**It fails safe, and that is why the design was acceptable at all.** If the add-on is inactive,
its commands are still registered (XenForo walks every add-on directory on disk) but the listener
does not fire, so nothing is hidden. Nothing is persisted. A break anywhere puts the full list back.

**`hide-dev-commands:validate` is the guard on that chain**, and the reason the add-on has the
command at all: it proves capture, the listener, that each family is still found under its
namespace, and that `xf-dev:info` refuses by name. Run it on every new XenForo version.

### Decisions, made on 2026-11-08

All are Simon's except the designer-mode one, which the first session made and reported to him.

- **Hide and block every command in a family, not only the two without a guard.** `setCode()`
  replaces `execute()` with a refusal. A guarded command refuses in its own `run()` first, so in
  practice the block answers for `xf-dev:info` and `xf-dev:metadata-fix`. The refusal uses the guard
  traits' own words, so every hidden command refuses alike, and an unguarded command a later XenForo
  adds is covered without a change here.
- **The three core namespaces only, matched by class prefix**: `XF\Cli\Command\Development\`,
  `\Make\` and `\Designer\`. Not `xf-addon:*`, whose `*-step` commands are operator tools, and not
  third-party add-ons' commands. A command name is free text; the class namespace is where core
  keeps them.
- **Designer commands follow designer mode, not development mode**, because that is what
  `RequiresDesignerModeTrait` checks. Otherwise a forum with designer mode on and development off
  would have working commands hidden.
- **Both `:config` and `:validate`.** The capture needs a real command class; `config` reports state
  that no screen in the admin panel shows.
- **XenForo 2.2.0+, PHP 7.4+.** The same run order, event and Symfony methods are in 2.2.0 Patch 2,
  2.2.19 Patch 2 and 2.3.0 Patch 2, read from their zips, and 2.2.19 Patch 2 on PHP 7.4 passed the
  sandbox run in `TESTING.md` on 2026-11-08. PHP 7.4 is for the `RendersReport` trait.

### The command being run is blocked but not hidden, or an abbreviation is logged as an error

**`XF\Cli\Runner` resolves the typed name twice**: once before the app starts, when nothing is hidden,
and again inside `$console->run()`, after the listener. A hidden command reached by an abbreviation
passes the first lookup and fails the second with `CommandNotFoundException`, which the runner's
catch-all logs to `xf_error_log` and renders, exit 1. So the listener passes argv's first argument to
`apply()`, which leaves the command it resolves to visible. It is still blocked, and `list` is not
running, so nothing lists it.

A 2.3.13 sandbox found this on 2026-11-08: before the change `xf-dev:inf` logged a server error where
a forum without the add-on prints "Development mode is not enabled".

### What hiding does and does not do, in Symfony Console 5.4 and 2.2's fork

- **An exact name is returned before the hidden flag is checked**, which is why the block exists.
- **`list` skips hidden commands, and `list xf-dev` throws**, because a namespace holding only hidden
  commands is not a namespace. The runner logs that throw, and it logs `list <unknown namespace>` the
  same way on a forum without this add-on, so it is not a regression.
- **A mistyped name prints "Did you mean" with the hidden commands in it.** The suggestions come from
  the runner's first lookup, before any listener loads, so this cannot be fixed from an add-on.
- `help <name>` still describes a hidden command.

### Not every family exists on every supported XenForo

**2.2 and 2.3.0 Patch 2 have no `xf-make:*` and no `XF\Cli\Command\Make\`; 2.2 has no `xf-dev:info`.**
So an empty family is a warning only where its core directory exists, and a blank skip where it does
not; otherwise every 2.2 forum would exit 2 under `--strict`. 2.2.19 Patch 2 has 70 development and
14 designer commands, against 80, 35 and 14 on 2.3.13.

**`validate` proves the block two ways, because 2.2 offers nothing safe to run.** The `blocked` row
reads Symfony's private `Command::$code` on every hidden command. The `xf-dev:info` row runs that
command, where it exists, because it only reads and prints. On 2.2 the one unguarded development
command is `xf-dev:metadata-fix`, which rewrites a file if the block has failed, so it is never run.

### The add-on declares no secrets, and does not answer `hampel_admin_api_redaction`

The declaration is for an add-on with options or `config.php` keys. This one has neither: it reads
core's `development` and `designer` keys and owns none. Add the listener in the commit that adds the
first setting; `xenforo-addon-secrets` has it.

### `--unattended` changes nothing

`validate` sends nothing and writes nothing; the one command it runs is `xf-dev:info`, which only
reads and prints, into a buffer that is thrown away. The flag exists because every `validate`
accepts it, so a monitor's command line works against every add-on.

## Commands

From the add-on root; `cmd.php` resolves the install from its own location.

```bash
php ../../../../cmd.php hide-dev-commands:config --commands
php ../../../../cmd.php hide-dev-commands:validate --strict
php ../../../../cmd.php xf-dev:import --addon=Hampel/HideDevCommands
```

**A development install cannot show the hiding**, because development and designer mode are on and
other work depends on that. The suite turns the modes off with `setConfig()`. The end-to-end proof
is a sandbox with the modes off; `TESTING.md` has the checks.

## Tests

```bash
composer install
vendor/bin/phpunit
```

**`forumConsole()` in `tests/TestCase.php` registers every command the way `XF\Cli\Runner` does**,
through an anonymous subclass that exposes `registerCommands()`, so the tests run against the 129
commands this XenForo really has rather than stand-ins. `runIn()` runs a command inside that
console. `runConsoleCommand()` from the framework is not used for this add-on's commands: it adds
the one command to an application of its own, which `CapturesConsole` would then capture.

**Call `fakesErrors()` in any test that makes a check throw.** `probe()` logs what it catches, and
without the fake that is a real row in the development forum's `xf_error_log`. It happened once, on
2026-11-08, and the row was deleted by id.

## Build and release

`build.json` strips the dev-only files: Composer, the suite, the PHPUnit caches, `TESTING.md` and
both Claude files, before `mv *.md _build/` promotes the README, CHANGELOG and LICENSE to the zip
root. There are no runtime Composer dependencies. Follow `xenforo-addon-release`.
