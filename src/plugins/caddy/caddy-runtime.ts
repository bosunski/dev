import { join } from 'node:path'
import { elevationTool, type ElevationTool } from '../../execution/privilege.js'

export class CaddyRuntime {
  constructor(
    public readonly platform = process.platform,
    public readonly binary = Bun.which('caddy') ?? 'caddy',
    private readonly elevation: ElevationTool = elevationTool(),
    private readonly env: Record<string, string | undefined> = process.env,
  ) {}

  httpPort(): number { return this.port('DEV_CADDY_HTTP_PORT', this.platform === 'darwin' ? 8080 : 80) }
  httpsPort(): number { return this.port('DEV_CADDY_HTTPS_PORT', this.platform === 'darwin' ? 8443 : 443) }
  adminPort(): number { return this.port('DEV_CADDY_ADMIN_PORT', 2019) }
  usesPrivilegedPorts(): boolean { return this.httpPort() < 1024 || this.httpsPort() < 1024 }
  shouldTrust(): boolean { return this.env['DEV_CADDY_SKIP_TRUST'] !== '1' }
  usesPortRedirect(): boolean {
    return this.platform === 'darwin' && this.env['DEV_CADDY_DISABLE_PORT_REDIRECT'] !== '1'
  }

  reloadCommand(config: string): string[] {
    return [this.binary, 'reload', '--config', config, '--address', `127.0.0.1:${this.adminPort()}`, '--force']
  }
  validateCommand(config: string): string[] { return [this.binary, 'validate', '--config', config] }

  trustCommand(): string[] {
    return [this.binary, 'trust', '--address', `127.0.0.1:${this.adminPort()}`]
  }

  isRunning(): boolean {
    return Bun.spawnSync(['curl', '-fsS', `http://127.0.0.1:${this.adminPort()}/config/`], {
      stdout: 'ignore', stderr: 'ignore',
    }).exitCode === 0
  }

  systemServiceActive(): boolean {
    return this.platform === 'linux'
      && Bun.spawnSync(['systemctl', 'is-active', '--quiet', 'caddy'], {
        stdout: 'ignore', stderr: 'ignore',
      }).exitCode === 0
  }

  hasLowPortAccess(): boolean {
    if (!this.usesPrivilegedPorts()) return true
    if (this.platform !== 'linux') return this.platform === 'darwin'
    const result = Bun.spawnSync(['getcap', this.binary], { stdout: 'pipe', stderr: 'ignore' })
    return result.exitCode === 0 && result.stdout.toString().includes('cap_net_bind_service=ep')
  }

  linuxBootstrapCommands(): string[][] {
    const commands = [[this.elevation, 'systemctl', 'disable', '--now', 'caddy']]
    if (this.usesPrivilegedPorts()) {
      commands.push([this.elevation, 'setcap', 'cap_net_bind_service=+ep', this.binary])
    }
    return commands
  }

  darwinBootstrapCommands(rulesFile: string): string[][] {
    return [
      [this.elevation, 'pfctl', '-a', 'com.apple/dev.caddy', '-f', rulesFile],
      [this.elevation, 'pfctl', '-E'],
    ]
  }

  darwinBootSession(): string {
    if (this.platform !== 'darwin') return ''
    const result = Bun.spawnSync(['sysctl', '-n', 'kern.boottime'], { stdout: 'pipe', stderr: 'ignore' })
    return result.exitCode === 0 ? result.stdout.toString().trim() : ''
  }

  userServiceFile(home = process.env['HOME'] ?? ''): string {
    return this.platform === 'darwin'
      ? join(home, 'Library/LaunchAgents/com.bosunski.dev.caddy.plist')
      : join(home, '.config/systemd/user/dev-caddy.service')
  }

  userServiceContent(config: string, logFile: string): string {
    if (this.platform === 'darwin') {
      return `<?xml version="1.0" encoding="UTF-8"?>\n`
        + `<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">\n`
        + `<plist version="1.0"><dict>\n`
        + `<key>Label</key><string>com.bosunski.dev.caddy</string>\n`
        + `<key>ProgramArguments</key><array><string>${this.xml(this.binary)}</string><string>run</string>`
        + `<string>--config</string><string>${this.xml(config)}</string></array>\n`
        + `<key>RunAtLoad</key><true/><key>KeepAlive</key><true/>\n`
        + `<key>StandardOutPath</key><string>${this.xml(logFile)}</string>`
        + `<key>StandardErrorPath</key><string>${this.xml(logFile)}</string>\n`
        + `</dict></plist>\n`
    }
    return `[Unit]\nDescription=DEV-owned Caddy\nAfter=network-online.target\n\n`
      + `[Service]\nExecStart=${this.systemd(this.binary)} run --config ${this.systemd(config)}\n`
      + `ExecReload=${this.systemd(this.binary)} reload --config ${this.systemd(config)}`
      + ` --address 127.0.0.1:${this.adminPort()} --force\n`
      + `Restart=on-failure\nRestartSec=1\n\n`
      + `[Install]\nWantedBy=default.target\n`
  }

  linuxUserStartCommands(): string[][] {
    return [
      ['systemctl', '--user', 'daemon-reload'],
      ['systemctl', '--user', 'enable', 'dev-caddy.service'],
      ['systemctl', '--user', 'restart', 'dev-caddy.service'],
    ]
  }

  linuxUserStopCommand(): string[] { return ['systemctl', '--user', 'stop', 'dev-caddy.service'] }
  linuxUserRestartCommand(): string[] { return ['systemctl', '--user', 'restart', 'dev-caddy.service'] }

  darwinUserBootstrapCommand(serviceFile: string, uid = process.getuid?.() ?? 0): string[] {
    return ['launchctl', 'bootstrap', `gui/${uid}`, serviceFile]
  }

  darwinUserKickstartCommand(uid = process.getuid?.() ?? 0): string[] {
    return ['launchctl', 'kickstart', '-k', `gui/${uid}/com.bosunski.dev.caddy`]
  }

  darwinUserStopCommand(uid = process.getuid?.() ?? 0): string[] {
    return ['launchctl', 'bootout', `gui/${uid}/com.bosunski.dev.caddy`]
  }

  caCertificateSource(
    home = process.env['HOME'] ?? '',
    dataHome = process.env['XDG_DATA_HOME'] ?? join(home, '.local/share'),
  ): string {
    if (this.platform === 'darwin') return join(home, 'Library/Application Support/Caddy/pki/authorities/local/root.crt')
    return join(dataHome, 'caddy/pki/authorities/local/root.crt')
  }

  private port(name: string, fallback: number): number {
    const parsed = Number(this.env[name])
    return Number.isInteger(parsed) && parsed > 0 && parsed <= 65535 ? parsed : fallback
  }

  private xml(value: string): string {
    return value.replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;')
  }

  private systemd(value: string): string {
    return `"${value.replaceAll('\\', '\\\\').replaceAll('"', '\\"')}"`
  }
}
