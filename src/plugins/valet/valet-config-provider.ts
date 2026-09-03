import type { ConfigProvider } from '../../types/capability.js'
import type { Step, StepResolver } from '../../types/step.js'
import type { Dev } from '../../dev.js'
import type { ValetPlugin } from './valet-plugin.js'
import { rawValetConfigSchema, ValetStepResolver } from './valet-step-resolver.js'
import { InstallValetStep } from './steps/install-valet-step.js'
import { PrepareValetStep } from './steps/prepare-valet-step.js'
import { PostUpStep } from './steps/post-up-step.js'
import { valetProviderArgs } from './valet-provider-args.js'
import { PackageManager } from '../core/config/package-manager.js'
import { PackagesStep } from '../core/steps/packages-step.js'

export class ValetConfigProvider implements ConfigProvider {
  private readonly dev: Dev
  private readonly plugin: ValetPlugin

  constructor(args: Record<string, unknown>) {
    const parsed = valetProviderArgs(args)
    this.dev = parsed.dev
    this.plugin = parsed.plugin
  }

  steps(): Step[] {
    const rawSteps = this.dev.config.raw_().steps ?? this.dev.config.raw_().up ?? []
    const valetSteps = rawSteps.filter(rawStep => 'valet' in rawStep)

    if (valetSteps.length === 0) return []

    const sites: string[] = []
    for (const rawStep of valetSteps) {
      const v = rawValetConfigSchema.parse(rawStep['valet'])
      const rawSites = v.sites
      if (Array.isArray(rawSites)) {
        for (const site of rawSites) {
          const host = typeof site === 'string' ? site : site.host
          if (host) sites.push(host)
        }
      }
    }

    const prerequisites: Step[] = this.dev.config.isLinux()
      ? [new PackagesStep(PackageManager.detect(), ['xsel'])]
      : []

    return [
      ...prerequisites,
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
