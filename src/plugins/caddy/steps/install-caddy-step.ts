import type { Runner } from '../../../execution/runner.js'
import { BaseStep } from '../../../step/base-step.js'
import { PackageManager } from '../../core/config/package-manager.js'

export class InstallCaddyStep extends BaseStep {
  name(): string { return 'Install Caddy' }
  id(): string { return 'caddy.install' }

  async done(runner: Runner): Promise<boolean> {
    return runner.hasCommand('caddy') && (process.platform !== 'linux' || runner.hasCommand('setcap'))
  }

  async run(runner: Runner): Promise<boolean> {
    const manager = PackageManager.detect()
    const packages = process.platform === 'linux' ? ['caddy', 'setcap'] : ['caddy']
    return runner.exec(manager.installCommand(manager.resolve(packages)))
  }
}
