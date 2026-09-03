import { existsSync, readFileSync, writeFileSync } from 'node:fs'
import type { Runner } from '../../../execution/runner.js'
import { BaseStep } from '../../../step/base-step.js'
import { CaddyRuntime } from '../caddy-runtime.js'

export class BootstrapCaddyStep extends BaseStep {
  name(): string { return 'Bootstrap DEV-owned Caddy' }
  id(): string { return 'caddy.bootstrap' }

  async done(runner: Runner): Promise<boolean> {
    const runtime = new CaddyRuntime()
    if (runtime.platform === 'darwin') {
      if (!runtime.usesPortRedirect()) return true
      const marker = runner.config.globalPath('caddy/.pf-configured')
      const session = runtime.darwinBootSession()
      return !!session && existsSync(marker) && readFileSync(marker, 'utf8').trim() === session
    }
    if (runtime.platform === 'linux') {
      return (!runtime.usesPrivilegedPorts() || runtime.hasLowPortAccess()) && !runtime.systemServiceActive()
    }
    if (!runtime.usesPrivilegedPorts()) return true
    return false
  }

  async run(runner: Runner): Promise<boolean> {
    const runtime = new CaddyRuntime()
    if (runtime.platform === 'darwin' && !runtime.usesPortRedirect()) return true
    if (runtime.platform === 'linux') {
      for (const command of runtime.linuxBootstrapCommands()) {
        const disablingService = command.includes('systemctl')
        if (!await runner.exec(command) && !disablingService) return false
      }
      return (!runtime.usesPrivilegedPorts() || runtime.hasLowPortAccess()) && !runtime.systemServiceActive()
    }
    if (runtime.platform !== 'darwin' && !runtime.usesPrivilegedPorts()) return true

    if (runtime.platform === 'darwin') {
      const rulesFile = runner.config.globalPath('caddy/pf.conf')
      writeFileSync(rulesFile,
        `rdr pass on lo0 inet proto tcp from any to 127.0.0.1 port 80 -> 127.0.0.1 port ${runtime.httpPort()}\n`
        + `rdr pass on lo0 inet proto tcp from any to 127.0.0.1 port 443 -> 127.0.0.1 port ${runtime.httpsPort()}\n`,
      )
      for (const command of runtime.darwinBootstrapCommands(rulesFile)) {
        if (!await runner.exec(command)) return false
      }
      const session = runtime.darwinBootSession()
      if (!session) return false
      writeFileSync(runner.config.globalPath('caddy/.pf-configured'), `${session}\n`)
      return true
    }
    return false
  }
}
