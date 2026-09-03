import type { Dev } from '../../../../dev.js'
import type { Runner } from '../../../../execution/runner.js'
import { BaseStep } from '../../../../step/base-step.js'

export class ShadowEnvStep extends BaseStep {
  constructor(private readonly dev: Dev) {
    super()
  }

  name(): string {
    return 'Initialize Shadowenv'
  }

  async run(runner: Runner): Promise<boolean> {
    if (!runner.config.isDevProject()) return true
    return this.dev.updateEnvironment()
  }

  async done(runner: Runner): Promise<boolean> {
    if (!runner.config.isDevProject()) return true
    return this.dev.environmentIsCurrent()
  }

  id(): string {
    return `shadowenv-${this.dev.config.path()}`
  }
}
