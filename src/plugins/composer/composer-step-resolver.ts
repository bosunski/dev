import type { StepResolver, Step } from '../../types/step.js'
import type { Runner } from '../../execution/runner.js'
import { BaseStep } from '../../step/base-step.js'
import { EnsureComposerStep } from './steps/ensure-composer-step.js'
import { PackagesStep } from './steps/packages-step.js'
import { AuthStep, type RawAuth } from './steps/auth-step.js'

type RawComposerConfig = {
  packages?: unknown[]
  auth?: RawAuth[]
}

export class ComposerStepResolver implements StepResolver {
  resolve(args: unknown): Step {
    if (!args || typeof args !== 'object') {
      throw new Error('Composer configuration should be an object!')
    }
    return new ComposerCompositeStep(args as RawComposerConfig)
  }
}

class ComposerCompositeStep extends BaseStep {
  constructor(private readonly config: RawComposerConfig) { super() }

  name(): string | null { return 'Setup Composer' }

  async run(runner: Runner): Promise<boolean> {
    const steps: Step[] = [new EnsureComposerStep()]
    for (const auth of this.config.auth ?? []) {
      steps.push(new AuthStep(auth))
    }
    if (this.config.packages?.length) {
      steps.push(new PackagesStep(this.config.packages as (Record<string, string> | string)[]))
    }
    for (const step of steps) {
      if (!(await runner.execute(step))) return false
    }
    return true
  }

  async done(_runner: Runner): Promise<boolean> { return false }
  id(): string { return 'composer-setup' }
}
