import type { ConfigProvider } from '../../types/capability.js'
import type { Step, StepResolver } from '../../types/step.js'
import { ComposerStepResolver } from './composer-step-resolver.js'

export class ComposerConfigProvider implements ConfigProvider {
  constructor(_args: Record<string, unknown>) {}
  steps(): Step[] { return [] }
  validate(): boolean { return true }
  stepResolvers(): Record<string, StepResolver> {
    return { composer: new ComposerStepResolver() }
  }
}
