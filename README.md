# kantui

A minimal transparent php kanban TUI with vim-like keybindings.


![kantui overview](https://i.imgur.com/lIZv28L.png)


## Installation

Install with composer locally or globally and add appropriate paths to your `$PATH` environment variable:

```bash
composer require surgiie/kantui
# or globally
composer global require surgiie/kantui
```

### Use

```bash
# specify context or "board" to use. This lets you manage multiple boards.
export KANTUI_CONTEXT="my-context"
kantui
```

**Note**: All data will be written to `~/.kantui` directory. If you want to use a different directory, you can set the `KANTUI_HOME` environment variable.

## Configuration

A configuration file maybe placed at `~/.kantui/config.json` or at `~/.kantui/contexts/<your-current-context>/config.json` to customize some aspects of the application.

Below is the current available configuration options:

```json
{
  "timezone": "America/New_York",
  "human_readable_date": true,
  "delete_done": false
}
```

- `timezone`: The timezone to use for the application. Defaults to the system timezone. One of any listed [here](https://www.php.net/manual/en/timezones.php).
- `human_readable_date`: Whether to display dates in a human readable format. e.g "2 days ago". Defaults to `true`.
- `delete_done`: Delete finished todos permanently instead of moving it to the "done" status and keeping it in the data file. Defaults to `true`.


## Run With Docker:

If you don't have or want to install php, you can run use the provided docker script to spin up a container which you can utilize to run the application.

### Install Docker Script:

```bash
# Assumes $PATH includes $HOME/.local/bin, add or customize as needed
desired_version=0.2.0 && wget -qO $HOME/.local/bin/kantui https://raw.githubusercontent.com/surgiie/kantui/refs/tags/v$desired_version/docker && chmod +x $HOME/.local/bin/kantui
```

```bash
# start app. Image and container will be created if not already present
kantui

# attach to the container and start a bash shell
kantui --attach
```

**Note** - Your `~/.kantui` directory will automatically be mounted on initial run and any `KANTUI_` env variables will automatically be passed to the container.

