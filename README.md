>[!Important]
> Although this is currently being used in personal projects, it is worth noting that it is not considered to be released yet and breaking changes might be frequent. The project is made public solely as a way to build this more in public and collect feedback and not as an indication that it is fit for use in all scenarios.

DEV is a tool for creating a consistent and evolvable project development environment. It is inspired by an internal Shopify tool, DEV, that I used while at Shopify. It is designed to adjust a development environment to match a configuration defined inside a `dev.yml` file.

### Features
- Cloning
- Plugins
- Preset
- Custom scripts
- Procfiles
- Customisable environment variable using ShadowEnv
- Project dependencies as services
- Custom commands
- Sites
- Single Binary
- Environment variable patching
- File tracking - `composer.json`, `composer.lock`, `package-lock.json`, etc.
- MySQL Database

### ToDo
- [ ] Plugins
- [ ] Presets
- [ ] Automated Tests
- [ ] Add PHPStan
- [ ] Add Pint for code styling.
- [ ] Better CLI UI
- [ ] Prioritisation of steps
- [ ] Extend functionality to other OSes.
- [ ] Documentation

### Trying DEV
At the moment, DEV works on MacOS as some aspect of the code assumes this. The plan is to support other platforms, too.

To install DEV for the first time, you can follow this process to install the pre-built MacOS binary using [GitHub CLI](https://cli.github.com):

```bash
sudo gh release --repo bosunski/dev download --clobber -p "dev-*-macOS-arm64" -O /usr/local/bin/dev && sudo chmod +x /usr/local/bin/dev
```

Once DEV is installed, you should add this to your SHELL profile:
```bash
# DEV
eval "$(/usr/local/bin/dev init zsh)"
```

This will make it possible for DEV to provide notices when there are changes in your project and you need to run `dev up` command

### Updating Dev
If you already have DEV installed, you can run this to update the existing binary to the latest available version:

```bash
export GITHUB_TOKEN=your_token_here
sudo -E dev self-update
```

For this, you will need your GITHUB_TOKEN since the repo is currently private.

### Configuration
DEV uses a `dev.yml` file at the root of the project to store configurations. So, projects that can be managed using dev must contain this file.

#### name
The `name`, while optional, defines the name of your project. When not, specified, it defaults to the name of the directory containing the `dev.yml` configuration. You can set the name of the project at the top level like this:
```yaml
name: core
```
#### steps

The steps define the steps you want to go through to set up a project. These steps are performed one after the other as they have been defined.

#### projects

You can define projects for which the current projects depend so that DEV can ensure that they are present when provisioning the current project. This is useful when a project depends on another to perform its functions. A common example will be a front-end service depending on a backend service that exposes an API. Normally, one would probably specify this in a README or documentation of the "front end" about this important detail.

When `dev up` runs, it ensures that all projects defined as a dependency are cloned and then provisioned (if they are managed with DEV ) before the current project is provisioned. You can define projects from the top-level config like this:

```yaml
name: org/frontend
projects:
	- org/backend
```

As you can see, projects are defined by their repository names as in `owner/repo` or `org/repo` so as to inform DEV where to source the project when it is missing locally.

It is also worth noting that, if any dependency project contains dependencies, DEV will also resolve them down to the last one before starting the provisioning process.
##### Order of project Resolution
The current project is resolved last and will be provisioned last since it depends on other projects. DEV resolves projects in the order in which they are defined in the configuration so, a configuration like this:

```yaml
name: org/frontend
projects:
	- org/payments # depends on org/websocket
	- org/backend
```

Will end up being resolved in this order:
- `org/payments`
- `org/websocket`
- `org/backend`
- `org/frontend`
As a result, the provisioning will also happen in that order when `dev up` runs.
#### commands
Sometimes, you may want to keep utility commands that are specific to the project to help perform tasks around your project. You can define these commands using the `commands` top-level attribute. For example, if we want to add a command that formats the code, we can add a `style` command like this:

```yaml
commands:
	style: prettier 
```

The command, once defined, can now be run using dev as `dev style`  or `dev run style` .  
#### sites

#### serve

#### env

### Use of Shadowenv
### Contributing to DEV
#### Requirements
- PHP >=8.2
- Composer

#### Setting up the project

1. Clone the project
```shell
git clone https://github.com/bosunski/dev
```
2. Install composer dependencies
```shell
composer install
```
3. Use DEV to set up the remaining aspects
```shell
php dev up
```

Alternatively, if you have DEV binary already installed, you can use it to set up the project like this:
```shell
dev clone bosunski/dev && dev up
```

### FAQ

#### How can I contribute to DEV?
- You can test and give feedback. Feel free to open an [issue](https://github.com/bosunski/dev/issues/new)!
- You can suggest [features](https://github.com/bosunski/dev/issues/new)
- You can also add features (see the ToDo)

#### Is Dev stable for use?
Although I use DEV as a daily driver at this point. I won't consider it stable for use since there are other things
that affects this stability that are not yet in place.

#### Does DEV replace docker?
Yes.
