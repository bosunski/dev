import { Command, Flags } from '@oclif/core'
import { existsSync, readFileSync } from 'node:fs'
import { getDevContext } from '../context.js'
import { CaddyManager } from '../plugins/caddy/caddy-manager.js'

abstract class CaddyCommand extends Command {
  protected async context(): Promise<Awaited<ReturnType<typeof getDevContext>> & { manager: CaddyManager }> {
    const context = await getDevContext()
    return { ...context, manager: new CaddyManager(context.dev.config) }
  }
}

export class CaddyStatus extends CaddyCommand {
  static id = 'caddy:status'
  static description = 'Show DEV-owned Caddy status'
  async run(): Promise<void> {
    await this.parse(CaddyStatus)
    const { manager } = await this.context()
    this.log(`Caddy: ${manager.isRunning() ? 'running' : 'stopped'}`)
    this.log(`Machine bootstrap: ${manager.isBootstrapReady() ? 'ready' : 'required'}`)
    this.log(`Configuration: ${manager.configIsValid() ? 'valid' : 'missing or invalid'}`)
    this.log(`Local CA export: ${manager.caIsCurrent() ? 'current' : 'missing or stale'}`)
    this.log(`Caddyfile: ${manager.caddyfile()}`)
  }
}

export class CaddyStart extends CaddyCommand {
  static id = 'caddy:start'
  static description = 'Start DEV-owned Caddy'
  async run(): Promise<void> {
    await this.parse(CaddyStart)
    const { dev, manager } = await this.context()
    if (!await manager.start(dev.runner)) this.exit(1)
  }
}

export class CaddyStop extends CaddyCommand {
  static id = 'caddy:stop'
  static description = 'Stop DEV-owned Caddy'
  async run(): Promise<void> {
    await this.parse(CaddyStop)
    const { dev, manager } = await this.context()
    if (!await manager.stop(dev.runner)) this.exit(1)
  }
}

export class CaddyReload extends CaddyCommand {
  static id = 'caddy:reload'
  static description = 'Regenerate and reload DEV-owned Caddy'
  async run(): Promise<void> {
    await this.parse(CaddyReload)
    const { dev, manager } = await this.context()
    if (!await manager.reload(dev.runner)) this.exit(1)
  }
}

export class CaddyRestart extends CaddyCommand {
  static id = 'caddy:restart'
  static description = 'Restart DEV-owned Caddy'
  async run(): Promise<void> {
    await this.parse(CaddyRestart)
    const { dev, manager } = await this.context()
    if (!await manager.restart(dev.runner)) this.exit(1)
  }
}

export class CaddyTrust extends CaddyCommand {
  static id = 'caddy:trust'
  static description = 'Trust the DEV-owned Caddy local CA'
  async run(): Promise<void> {
    await this.parse(CaddyTrust)
    const { dev, manager } = await this.context()
    if (!await manager.trust(dev.runner)) this.exit(1)
  }
}

export class CaddyDoctor extends CaddyCommand {
  static id = 'caddy:doctor'
  static description = 'Check DEV-owned Caddy configuration and bootstrap state'
  async run(): Promise<void> {
    await this.parse(CaddyDoctor)
    const { manager } = await this.context()
    const checks = [
      ['Caddy process', manager.isRunning()],
      ['Machine bootstrap', manager.isBootstrapReady()],
      ['Caddy configuration', manager.configIsValid()],
      ['Local CA export', manager.caIsCurrent()],
    ] as const
    for (const [name, passed] of checks) this.log(`${passed ? '✓' : '✗'} ${name}`)
    if (checks.some(([, passed]) => !passed)) this.exit(1)
  }
}

export class CaddyLogs extends CaddyCommand {
  static id = 'caddy:logs'
  static description = 'Show DEV-owned Caddy logs'
  static flags = { follow: Flags.boolean({ char: 'f', description: 'Follow log output' }) }
  async run(): Promise<void> {
    const { flags } = await this.parse(CaddyLogs)
    const { dev, manager } = await this.context()
    if (!existsSync(manager.logFile())) return
    if (flags.follow) {
      if (!await dev.runner.exec(['tail', '-n', '100', '-f', manager.logFile()])) this.exit(1)
      return
    }
    this.log(readFileSync(manager.logFile(), 'utf8').split('\n').slice(-101).join('\n'))
  }
}

export class CaddyUnlink extends CaddyCommand {
  static id = 'caddy:unlink'
  static description = 'Remove this project from DEV-owned Caddy'
  async run(): Promise<void> {
    await this.parse(CaddyUnlink)
    const { dev, manager } = await this.context()
    if (!await manager.unlink(dev.runner)) this.exit(1)
  }
}
