import { createHash } from 'node:crypto'
import { existsSync, mkdirSync, readFileSync, readdirSync, rmSync, writeFileSync } from 'node:fs'
import { dirname, join, relative } from 'node:path'
import type { Config } from '../../config/config.js'
import type { Runner } from '../../execution/runner.js'
import { UserException } from '../../exceptions.js'
import { caddyProjectSitesDir } from './caddy-layout.js'
import { CaddyRuntime } from './caddy-runtime.js'

export class CaddyManager {
  private readonly runtime = new CaddyRuntime()

  constructor(private readonly config: Config) {}

  caddyfile(): string { return this.config.globalPath('caddy/Caddyfile') }
  logFile(): string { return this.config.globalPath('caddy/logs/caddy.log') }
  isRunning(): boolean { return this.bootstrapReady() && this.runtime.isRunning() }
  isBootstrapReady(): boolean { return this.bootstrapReady() }
  caIsCurrent(): boolean {
    const source = this.runtime.caCertificateSource()
    const target = this.config.globalPath('caddy/ca/root.crt')
    return existsSync(source) && existsSync(target)
      && readFileSync(source).equals(readFileSync(target))
  }

  configIsValid(): boolean {
    return existsSync(this.caddyfile())
      && Bun.spawnSync(this.runtime.validateCommand(this.caddyfile()), {
        stdout: 'ignore', stderr: 'ignore',
      }).exitCode === 0
  }

  isDeployed(): boolean {
    const content = this.content()
    const hashFile = this.config.globalPath('caddy/.deployed-hash')
    return existsSync(hashFile)
      && readFileSync(hashFile, 'utf8').trim() === this.hash(content)
      && this.caIsCurrent()
      && this.isRunning()
  }

  async deploy(runner: Runner): Promise<boolean> {
    this.requireBootstrap()
    const content = this.content()
    mkdirSync(dirname(this.caddyfile()), { recursive: true })
    mkdirSync(dirname(this.logFile()), { recursive: true })
    writeFileSync(this.caddyfile(), content)
    if (!await runner.exec(this.runtime.validateCommand(this.caddyfile()))) return false

    if (this.isRunning()) {
      if (!await runner.exec(this.runtime.reloadCommand(this.caddyfile()))) return false
    } else {
      this.writeUserService()
      if (!await this.startUserService(runner)) return false
    }
    if (!await this.ensureTrusted(runner)) return false

    writeFileSync(this.config.globalPath('caddy/.deployed-hash'), this.hash(content) + '\n')
    return true
  }

  async start(runner: Runner): Promise<boolean> {
    this.requireBootstrap()
    if (this.isRunning()) return true
    mkdirSync(dirname(this.caddyfile()), { recursive: true })
    mkdirSync(dirname(this.logFile()), { recursive: true })
    if (!existsSync(this.caddyfile())) writeFileSync(this.caddyfile(), this.content())
    if (!await runner.exec(this.runtime.validateCommand(this.caddyfile()))) return false
    this.writeUserService()
    return this.startUserService(runner)
  }

  async reload(runner: Runner): Promise<boolean> {
    return this.isRunning() ? this.deploy(runner) : this.start(runner)
  }

  async restart(runner: Runner): Promise<boolean> {
    this.requireBootstrap()
    this.writeUserService()
    if (this.runtime.platform === 'linux') {
      if (!this.isRunning()) return this.startUserService(runner)
      if (!await runner.exec(['systemctl', '--user', 'daemon-reload'])) return false
      return runner.exec(this.runtime.linuxUserRestartCommand())
    }
    if (this.isRunning() && !await this.stop(runner)) return false
    return this.startUserService(runner)
  }

  async stop(runner: Runner): Promise<boolean> {
    if (!this.isRunning()) return true
    return this.runtime.platform === 'darwin'
      ? runner.exec(this.runtime.darwinUserStopCommand())
      : runner.exec(this.runtime.linuxUserStopCommand())
  }

  async trust(runner: Runner): Promise<boolean> {
    if (!this.isRunning() && !await this.start(runner)) return false
    return this.ensureTrusted(runner, true)
  }

  async unlink(runner: Runner): Promise<boolean> {
    this.requireBootstrap()
    const sitesDir = caddyProjectSitesDir(this.config.globalPath('caddy'), this.config.cwd())
    const projectsDir = this.config.globalPath('caddy/projects')
    const child = relative(projectsDir, sitesDir)
    if (child.startsWith('..') || child === '' || !sitesDir.endsWith('/sites')) {
      throw new UserException(`Refusing to unlink unexpected Caddy path: ${sitesDir}`)
    }
    if (existsSync(sitesDir)) rmSync(sitesDir, { recursive: true })
    return this.deploy(runner)
  }

  content(): string {
    const projectsDir = this.config.globalPath('caddy/projects')
    const files: string[] = []
    if (existsSync(projectsDir)) {
      for (const project of readdirSync(projectsDir).sort()) {
        const sitesDir = join(projectsDir, project, 'sites')
        if (!existsSync(sitesDir)) continue
        for (const file of readdirSync(sitesDir).filter(candidate => candidate.endsWith('.conf')).sort()) {
          files.push(join(sitesDir, file))
        }
      }
    }

    const options = [
      '{',
      `\tadmin 127.0.0.1:${this.runtime.adminPort()}`,
      '\tskip_install_trust',
      ...((this.runtime.httpPort() !== 80 || this.runtime.httpsPort() !== 443)
        ? [`\thttp_port ${this.runtime.httpPort()}`, `\thttps_port ${this.runtime.httpsPort()}`]
        : []),
      '\tlog default {',
      `\t\toutput file ${JSON.stringify(this.logFile())}`,
      '\t}',
      '}',
    ].join('\n')
    const raw = `${options}\n${files.map(file => readFileSync(file, 'utf8')).join('\n')}`
    const formatted = Bun.spawnSync([this.runtime.binary, 'fmt', '-'], {
      stdin: Buffer.from(raw), stdout: 'pipe', stderr: 'pipe',
    })
    return formatted.exitCode === 0 ? formatted.stdout.toString() : raw
  }

  private async ensureTrusted(runner: Runner, force = false): Promise<boolean> {
    if (!force && this.caIsCurrent()) return true
    if (!await runner.exec(this.runtime.trustCommand())) return false
    const source = this.runtime.caCertificateSource()
    if (!existsSync(source)) return false
    return runner.exec(['install', '-D', '-m', '0644', source, this.config.globalPath('caddy/ca/root.crt')])
  }

  private writeUserService(): void {
    const serviceFile = this.runtime.userServiceFile()
    mkdirSync(dirname(serviceFile), { recursive: true })
    writeFileSync(serviceFile, this.runtime.userServiceContent(this.caddyfile(), this.logFile()))
  }

  private async startUserService(runner: Runner): Promise<boolean> {
    if (this.runtime.platform === 'linux') {
      for (const command of this.runtime.linuxUserStartCommands()) {
        if (!await runner.exec(command)) return false
      }
      return true
    }
    const serviceFile = this.runtime.userServiceFile()
    if (await runner.exec(this.runtime.darwinUserBootstrapCommand(serviceFile))) return true
    return runner.exec(this.runtime.darwinUserKickstartCommand())
  }

  private bootstrapReady(): boolean {
    if (this.runtime.platform === 'darwin') {
      return existsSync(this.config.globalPath('caddy/.pf-configured'))
    }
    if (!this.runtime.usesPrivilegedPorts()) return true
    if (this.runtime.platform === 'linux') {
      return this.runtime.hasLowPortAccess() && !this.runtime.systemServiceActive()
    }
    return false
  }

  private requireBootstrap(): void {
    if (!this.bootstrapReady()) {
      throw new UserException('DEV-owned Caddy requires its one-time machine bootstrap. Run `dev up` first.')
    }
  }

  private hash(content: string): string {
    return createHash('sha256').update(content).digest('hex')
  }
}
