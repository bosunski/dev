>[!Important]
> Although this is currently being used in personal projects, it is worth noting that it is not considered to be released yet and breaking changes might be frequent. The project is made public solely as a way to build this in public and collect feedback and not as an indication that it is fit for use in all scenarios.

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

### Testing
If you want to you can follow this process to install the binary:

```bash
# Download and install binary to /usr/local/bin
sudo curl -L 
```

### Contributing
Since, I'm building this in public, I would love to hear your feedback and suggestions. You can open an issue or a PR.

### FAQ

#### Is Dev stable for use?
No, it's still in development.

#### Can I contribute to DEV?
Yes. You can contribute by giving your feedback or suggestion or by add to it via code.

#### Does DEV replaces docker?
No.

#### Why is DEV written in PHP and not Rust?
My Rust is rusty. Also, why not PHP?

### ToDo
- [ ] Plugins
- [ ] Presets
- [ ] Tests
- [ ] Add PHPStan
- [ ] Add Pint for code styling
- [ ] Better CLI UI
