import type { Runner } from '../../../execution/runner.js'
import { BaseStep } from '../../../step/base-step.js'
import type { SpcConfig } from '../config/spc-config.js'

export class SpcInstallRequirementsStep extends BaseStep {
  constructor(private readonly config: SpcConfig) { super() }

  name(): string { return 'Install SPC requirements' }
  id(): string { return 'spc.requirements' }

  async done(_runner: Runner): Promise<boolean> {
    const result = Bun.spawnSync([this.config.bin(), 'doctor', '--no-interaction'], {
      stdout: 'pipe',
      stderr: 'pipe',
    })
    return result.exitCode === 0
  }

  async run(runner: Runner): Promise<boolean> {
    return runner.exec([this.config.bin(), 'doctor', '--auto-fix', '--no-interaction'])
  }
}
