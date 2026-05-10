import type { StepResolver, Step } from '../../types/step.js'
import type { ValetPlugin } from './valet-plugin.js'
import { ValetStep } from './steps/valet-step.js'
import type { RawSite } from './steps/site-step.js'
import type { Dev } from '../../dev.js'

type RawValetConfig = { php?: string | Record<string, unknown>; sites?: RawSite[] }

export class ValetStepResolver implements StepResolver {
  constructor(private readonly plugin: ValetPlugin, private readonly dev: Dev) {}

  resolve(args: unknown): Step {
    const valetBin = this.plugin.localConfig?.get('bin') ?? 'valet'
    return new ValetStep(
      args as RawValetConfig,
      valetBin,
      this.plugin.localConfig ?? undefined,
      this.dev,
    )
  }
}
