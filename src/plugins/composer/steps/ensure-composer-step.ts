import type { Runner } from '../../../execution/runner.js'
import { BaseStep } from '../../../step/base-step.js'

export class EnsureComposerStep extends BaseStep {
  name(): string { return 'Ensure Composer is installed' }
  id(): string { return 'ensure-composer' }

  async run(runner: Runner): Promise<boolean> {
    // Install composer via brew if not available
    return runner.withoutShadowEnv().exec([
      runner.config.brewPath('bin/brew'), 'install', 'composer',
    ], undefined, { HOMEBREW_NO_AUTO_UPDATE: '1' })
  }

  async done(runner: Runner): Promise<boolean> {
    return runner.hasCommand('composer')
  }
}
