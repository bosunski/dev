import type { StepResolver } from '../../../types/step.js'
import type { Step } from '../../../types/step.js'
import type { RawScript } from '../../../types/config.js'
import { CustomStep } from '../steps/custom-step.js'

export class ScriptResolver implements StepResolver {
  resolve(args: unknown): Step {
    if (!args || typeof args !== 'object' || !('run' in args)) {
      throw new Error('Script configuration should be an object with a run key!')
    }
    return new CustomStep(args as RawScript)
  }
}
