import type { Runner } from '../../../execution/runner.js'
import { BaseStep } from '../../../step/base-step.js'

export class InstallCaddyStep extends BaseStep {
  name(): string { return 'Install Caddy' }
  id(): string { return 'caddy.install' }

  async done(runner: Runner): Promise<boolean> {
    return runner.hasCommand('caddy')
  }

  async run(runner: Runner): Promise<boolean> {
    const caddyBin = runner.config.brewPath('bin/caddy')
    return runner.exec(`brew install caddy && ${caddyBin} trust`)
  }
}
