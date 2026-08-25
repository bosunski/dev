import type { Runner } from '../../../execution/runner.js'
import { BaseStep } from '../../../step/base-step.js'
import { PackageManager } from '../../core/config/package-manager.js'

export class EnsureComposerStep extends BaseStep {
  name(): string { return 'Ensure Composer is installed' }
  id(): string { return 'ensure-composer' }

  async run(runner: Runner): Promise<boolean> {
    const manager = PackageManager.detect()
    return runner.withoutShadowEnv().exec(manager.installCommand(manager.resolve(['composer'])))
  }

  async done(runner: Runner): Promise<boolean> {
    return runner.hasCommand('composer')
  }
}
