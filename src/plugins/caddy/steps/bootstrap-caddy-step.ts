import { existsSync, writeFileSync } from 'node:fs'
import type { Runner } from '../../../execution/runner.js'
import { BaseStep } from '../../../step/base-step.js'
import { CaddyRuntime } from '../caddy-runtime.js'

export class BootstrapCaddyStep extends BaseStep {
  name(): string { return 'Bootstrap DEV-owned Caddy' }
  id(): string { return 'caddy.bootstrap' }

  async done(runner: Runner): Promise<boolean> {
    const runtime = new CaddyRuntime()
    if (runtime.platform === 'darwin') return existsSync(runner.config.globalPath('caddy/.pf-configured'))
    if (!runtime.usesPrivilegedPorts()) return true
    if (runtime.platform === 'linux') return runtime.hasLowPortAccess() && !runtime.systemServiceActive()
    return false
  }

  async run(runner: Runner): Promise<boolean> {
    const runtime = new CaddyRuntime()
    if (runtime.platform !== 'darwin' && !runtime.usesPrivilegedPorts()) return true
    if (runtime.platform === 'linux') {
      for (const command of runtime.linuxBootstrapCommands()) {
        const disablingService = command.includes('systemctl')
        if (!await runner.exec(command) && !disablingService) return false
      }
      return runtime.hasLowPortAccess() && !runtime.systemServiceActive()
    }

    if (runtime.platform === 'darwin') {
      const rulesFile = runner.config.globalPath('caddy/pf.conf')
      writeFileSync(rulesFile,
        `rdr pass on lo0 inet proto tcp from any to 127.0.0.1 port 80 -> 127.0.0.1 port ${runtime.httpPort()}\n`
        + `rdr pass on lo0 inet proto tcp from any to 127.0.0.1 port 443 -> 127.0.0.1 port ${runtime.httpsPort()}\n`,
      )
      for (const command of runtime.darwinBootstrapCommands(rulesFile)) {
        if (!await runner.exec(command)) return false
      }
      writeFileSync(runner.config.globalPath('caddy/.pf-configured'), 'configured\n')
      return true
    }
    return false
  }
}
