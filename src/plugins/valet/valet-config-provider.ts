import type { ConfigProvider } from '../../types/capability.js'
import type { Step, StepResolver } from '../../types/step.js'
import type { Dev } from '../../dev.js'
import type { ValetPlugin } from './valet-plugin.js'
import { ValetStepResolver } from './valet-step-resolver.js'
import { InstallValetStep } from './steps/install-valet-step.js'
import { PrepareValetStep } from './steps/prepare-valet-step.js'
import { PostUpStep } from './steps/post-up-step.js'

export class ValetConfigProvider implements ConfigProvider {
  private readonly dev: Dev
  private readonly plugin: ValetPlugin

  constructor(args: Record<string, unknown>) {
    this.dev = args['dev'] as Dev
    this.plugin = args['plugin'] as ValetPlugin
  }

  steps(): Step[] {
    const rawSteps = this.dev.config.raw_().steps ?? this.dev.config.raw_().up ?? []
    const sites: string[] = []
    for (const rawStep of rawSteps) {
      if (!rawStep || typeof rawStep !== 'object') continue
      const v = (rawStep as Record<string, unknown>)['valet'] as Record<string, unknown> | undefined
      if (!v) continue
      const rawSites = v['sites']
      if (Array.isArray(rawSites)) {
        for (const site of rawSites) {
          const host = typeof site === 'string' ? site : (site as Record<string, string>)['host']
          if (host) sites.push(host)
        }
      }
    }

    return [
      new InstallValetStep(),
      new PrepareValetStep(this.plugin, this.dev),
      new PostUpStep(sites, this.plugin, this.dev),
    ]
  }

  validate(): boolean { return true }

  stepResolvers(): Record<string, StepResolver> {
    return { valet: new ValetStepResolver(this.plugin, this.dev) }
  }
}
