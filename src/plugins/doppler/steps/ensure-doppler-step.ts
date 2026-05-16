import type { Runner } from '../../../execution/runner.js'
import { BaseStep } from '../../../step/base-step.js'

export class EnsureDopplerStep extends BaseStep {
  name(): string {
    return 'Ensure Doppler CLI is installed and authenticated'
  }

  async run(runner: Runner): Promise<boolean> {
    if (!runner.hasCommand('doppler')) {
      return runner.exec(['brew', 'install', 'gnupg', '&&', 'brew', 'install', 'dopplerhq/cli/doppler'])
    }

    const proc = Bun.spawnSync(['doppler', 'me', '--json'])
    if (proc.exitCode !== 0) {
      runner.getIO().error('Doppler CLI is not authenticated. Run: doppler login')
      return false
    }

    return true
  }

  async done(_runner: Runner): Promise<boolean> {
    const proc = Bun.spawnSync(['doppler', 'me', '--json'])
    return proc.exitCode === 0
  }

  id(): string {
    return 'doppler-ensure'
  }
}
