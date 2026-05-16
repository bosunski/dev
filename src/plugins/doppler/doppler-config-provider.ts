import type { ConfigProvider } from '../../types/capability.js'
import type { Step, StepResolver } from '../../types/step.js'
import { DopplerStepResolver } from './doppler-step-resolver.js'

export class DopplerConfigProvider implements ConfigProvider {
  constructor(_args: Record<string, unknown>) {}

  steps(): Step[] {
    return []
  }

  validate(): boolean {
    return true
  }

  stepResolvers(): Record<string, StepResolver> {
    return { doppler: new DopplerStepResolver() }
  }
}
