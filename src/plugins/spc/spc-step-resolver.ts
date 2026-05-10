import type { StepResolver, Step } from '../../types/step.js'
import type { Dev } from '../../dev.js'
import type { Runner } from '../../execution/runner.js'
import { BaseStep } from '../../step/base-step.js'
import { SpcConfig, type RawSpcConfig } from './config/spc-config.js'

export class SpcStepResolver implements StepResolver {
  constructor(private readonly dev: Dev) {}

  resolve(args: unknown): Step {
    if (!args || typeof args !== 'object') {
      throw new Error('Spc configuration should be an object!')
    }

    const config = new SpcConfig(args as RawSpcConfig, this.dev.config)
    return new SpcCompositeStep(config)
  }
}

class SpcCompositeStep extends BaseStep {
  constructor(private readonly config: SpcConfig) { super() }
  name(): string | null { return `Build PHP ${this.config.phpVersion}` }

  async run(runner: Runner): Promise<boolean> {
    for (const step of this.config.steps()) {
      if (!(await runner.execute(step))) return false
    }
    return true
  }

  async done(_runner: Runner): Promise<boolean> { return false }
  id(): string { return `spc-${this.config.phpVersion}-${this.config.md5}` }
}
