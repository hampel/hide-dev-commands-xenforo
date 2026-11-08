# CHANGELOG

## 1.0.0 (unreleased)

- first release
- hides `xf-dev:*` and `xf-make:*` from `cmd.php list` when development mode is off, and
  `xf-designer:*` when designer mode is off; each hidden command also refuses to run by name
- also hides any add-on's command that uses `RequiresDevModeTrait` or `RequiresDesignerModeTrait`,
  by the mode its trait checks
- `$config['hideDevCommands']['hide']` in `config.php` hides any other commands by name or `*`
  pattern; they are hidden from the list but still run by name
- `hide-dev-commands:config` shows which modes are on and what is hidden
- `hide-dev-commands:validate` proves the hiding still works, for running after a XenForo upgrade
