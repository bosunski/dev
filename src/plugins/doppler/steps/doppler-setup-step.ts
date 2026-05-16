import type { Runner } from '../../../execution/runner.js'
import { BaseStep } from '../../../step/base-step.js'
import type { DopplerConfig } from '../doppler-step-resolver.js'

export class DopplerSetupStep extends BaseStep {
  constructor(private readonly config: DopplerConfig) {
    super()
  }

  name(): string {
    return `Configure Doppler project (${this.config.project}/${this.config.config})`
  }

  async run(runner: Runner): Promise<boolean> {
    if (!runner.hasCommand('doppler')) {
      const installed = await runner.exec(['brew', 'install', 'gnupg', '&&', 'brew', 'install', 'dopplerhq/cli/doppler'])
      if (!installed) return false
    }

    return runner.exec([
      'doppler', 'setup',
      '--project', this.config.project,
      '--config', this.config.config,
      '--no-interactive',
    ])
  }

  async done(runner: Runner): Promise<boolean> {
    if (!runner.hasCommand('doppler')) return false

    const proc = Bun.spawnSync(['doppler', 'configs', '--json', '--project', this.config.project])
    if (proc.exitCode !== 0) return false

    try {
      const configs = JSON.parse(new TextDecoder().decode(proc.stdout)) as Array<{ name: string }>
      return configs.some(c => c.name === this.config.config)
    } catch {
      return false
    }
  }

  id(): string {
    return `doppler-setup-${this.config.project}-${this.config.config}`
  }
}
