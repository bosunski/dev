import type { ConfigProvider } from '../../types/capability.js'
import type { Step, StepResolver } from '../../types/step.js'
import { BrewStepResolver } from './brew-step-resolver.js'

export class BrewConfigProvider implements ConfigProvider {
  constructor(_args: Record<string, unknown>) {}

  steps(): Step[] { return [] }
  validate(): boolean { return true }
  stepResolvers(): Record<string, StepResolver> {
    return { brew: new BrewStepResolver() }
  }
}
