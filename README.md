>[!Important]
> Although this is currently being used in personal projects, it is worth noting that it is not considered to be released yet and breaking changes might be frequent. The project is made public solely as a way to build this more in public and collect feedback and not as an indication that it is fit for use in all scenarios.

DEV is a tool for creating a consistent and evolvable project development environment. It is inspired by an internal Shopify tool, DEV, that I used while at Shopify. It is designed to adjust a development environment to match a configuration defined inside a `dev.yml` file.

### Features
- Cloning
- Plugins
- Preset [Upcoming]
- Custom scripts
- Built-in Procfiles support
- Customisable environment using ShadowEnv
- Project dependencies
- Custom project commands
- Sites
- Single Binary
- Environment Variable management
- Config tracking - `composer.json`, `composer.lock`, `package-lock.json`, etc.
- MySQL Database

### Installing DEV
At the moment, DEV works on MacOS as some aspect of the code assumes this. The plan is to support other platforms, too.

To install DEV for the first time, you can follow this process to install the pre-built binary:

```bash
curl -fsSL https://raw.githubusercontent.com/bosunski/dev/main/scripts/install.sh | bash
```

The command above will:
- Install latest version of DEV inside `$HOME/.dev/bin`
- Prepend `$HOME/.dev/bin` to `$PATH`
- Configure hooks that will make it possible for DEV to provide notices when there are changes in your project and you need to run `dev up` command

### Updating Dev
If you already have DEV installed, you can run this to update the existing binary to the latest available version:

```bash
dev upgrade
```
If you want to downgrade/upgrade to a specific version, you can use:

```bash
dev upgrade <desired version>
```

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
You can define sites that are part of the project. This is useful when you have multiple sites that are part of the project and you want to manage them using DEV. You can define sites like this:

```yaml
sites:
    admin: https://example.com/admin
    frontend: https://example.com
```

#### serve
The `serve` attribute is used to define processes that should run when your projects are being served - when you run `dev serve`. This is useful when you have a project that requires multiple services to be running at the same time. You can define the processes like this:

```yaml
serve:
    app: php artisan serve
    queue: php artisan queue:work
    vite: vite
```

While the serve attribute is Procfile-like, it is not a Procfile as it adds some extra features. It is a simple way to define processes that should run when you run `dev serve`. The processes are started in the order in which they are defined and the stoppage of one of the processes will stop the others as well.

It is also worth noting that when a dependency project has a `serve` attribute, DEV will also start the processes defined in the dependency project when you run `dev serve` for the current project. This is useful when you have a project that depends on another project that requires some services to be running.

If you have a `.env.<environment>` file in the project, DEV will make sure that the environment variables are loaded before starting the processes defined in the `serve` attribute. Env files loaded are per-project basis and not shaared across projects. The default env file is `.env` but you can specify a different one like this:
    
```yaml
serve:
    web:
        run: php artisan serve
        env: local # .env.local
    queue: php artisan queue:work
```

You can also instruct DEV to not load env files by setting the `env` attribute to `false` like this:

```yaml
serve:
    web:
        run: php artisan serve
        env: false
```

#### env
The `env` attribute is used to define environment variables that should be set at all times during the provisioning of the project, when running custom commands and when running the `serve` command. They will also be injected into all shells whose working diretory is the project where you defined them. You can define environment variables like this:

```yaml
env:
    SOCK: /var/run/docker.sock
```

Since the environment variables are set at all times, you can use them in your commands, scripts, and processes. For example, you can use the `APP_ENV` environment variable in your `serve` attribute like this:

```yaml
serve:
    web: ./bin/serve --sock=${SOCK}
commands:
    show-sock: echo ${SOCK}
```

Variables defined in the `env` attribute can also be dynmically set in cases where you want to set them based on the environment. You can use the `${ENV}` syntax to set the value of the environment variable based on the environment or use the backticks to run commands within the env value. We can combine the two like this:

```yaml
env:
    SOCK: "${HOME}/run/`echo $PWD | md5`.sock" # /Users/bosun/run/1a79a4d60de6718e8e5b326e338ae533.sock
```

DEV will evaluate the values of environment variables that are dynamically set and inject them appropriately when running commands, scripts, and processes.

There might be times you want to set a variable based on user input, maybe a password, or a token that you don't want to store in dev.yml. You can define this type of variables like this:

```yaml
env:
    STRIPE_SECRET:
        prompt: "What is your Stripe Secret Key?"
        hint: "You can get the Secret Key at https://dashboard.stripe.com/test/apikeys"
        placeholder: "Looks like sk_test_xGgsdfgvsdf..."
```

When you run `dev up`, DEV will prompt you to enter the value of the `STRIPE_SECRET` environment variable. The value entered will be stored in the `$PWD/.dev/config.json` file where it can be reused when running commands, serve, and provisioning.

> [!Note]
> It is important to run `dev up` after modifying the `env` attributes so that the environment variables can injected in the shells, since the default ShadowEnv lisp file is generated during the provisioning process.

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

### FAQ

#### How can I contribute to DEV?
- You can test and give feedback. Feel free to open an [issue](https://github.com/bosunski/dev/issues/new)!
- You can suggest [features](https://github.com/bosunski/dev/issues/new)
- You can also add features (see the ToDo)

#### Is Dev stable for use?
Although I use DEV as a daily driver at this point. I won't consider it stable for use since there are other things
that affects this stability that are not yet in place.
