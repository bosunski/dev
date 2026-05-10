import type { ConfigProvider } from '../../types/capability.js'
import type { Step, StepResolver } from '../../types/step.js'
import type { Dev } from '../../dev.js'
import { SpcStepResolver } from './spc-step-resolver.js'

export class SpcConfigProvider implements ConfigProvider {
  private readonly dev: Dev
  constructor(args: Record<string, unknown>) {
    this.dev = args['dev'] as Dev
  }
  steps(): Step[] { return [] }
  validate(): boolean { return true }
  stepResolvers(): Record<string, StepResolver> {
    return { spc: new SpcStepResolver(this.dev) }
  }
}
