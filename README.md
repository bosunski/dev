>[!Important]
> Although this is currently being used in personal projects, it is worth noting that it is not considered to be released yet and breaking changes might be frequent. The project is made public solely as a way to build this more in public and collect feedback and not as an indication that it is fit for use in all scenarios.

DEV is a tool for creating consistent and evolve-able development environment for projects. It is inspired by an internal Shopify tool, also named DEV that I used while at Shopify. It is designed to adjust a development environment to match a configuration defined inside a `dev.yml` file.

### Features
- Cloning
- Plugins
- Preset
- Custom scripts
- Procfiles
- Customizable environment variable using ShadowEnv
- Project dependencies as services
- Custom commands
- Sites
- Single Binary
- Environment variable patching
- Configuration tracking - `composer.json`, `composer.lock`, `package-lock.json`, etc.

### ToDo
- [ ] Plugins
- [ ] Presets
- [ ] Automated Tests
- [ ] Add PHPStan
- [ ] Add Pint for code styling
- [ ] Better CLI UI
- [ ] Prioritization of steps
- [ ] Extend functionality to other OSes
- [ ] Documentation

### Trying DEV
At the moment, DEV works on MacOS as some aspect of the code assumes this. The future plan is to support other platforms too.

To install DEV for the first time, you can follow this process to install the pre-built MacOS binary using [GitHub CLI](https://cli.github.com):

```bash
sudo gh release --repo phpsandbox/dev download --clobber -p "dev-*-macOS-arm64" -O /usr/local/bin/dev 
```

### Updating Dev
If you already have DEV installed, you can run this to update to the the existing binary to the latest available version:

```
sudo dev self-update
```

### Contributing
### Requirements
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

#### Does DEV replaces docker?
No.
