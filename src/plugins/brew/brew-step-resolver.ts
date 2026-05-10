import type { StepResolver, Step } from '../../types/step.js'
import { BrewStep } from '../core/steps/brew-step.js'

export class BrewStepResolver implements StepResolver {
  resolve(args: unknown): Step {
    if (!Array.isArray(args)) {
      throw new Error('Brew configuration should be an array!')
    }
    return new BrewStep(args as string[])
  }
}
