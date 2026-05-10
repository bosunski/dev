import type { ConfigProvider } from '../../types/capability.js'
import type { Step, StepResolver } from '../../types/step.js'
import type { Dev } from '../../dev.js'
import { CaddyStepResolver } from './caddy-step-resolver.js'
import { InstallCaddyStep } from './steps/install-caddy-step.js'
import { PrepareCaddyStep } from './steps/prepare-caddy-step.js'
import { PostUpStep } from './steps/post-up-step.js'

export class CaddyConfigProvider implements ConfigProvider {
  private readonly dev: Dev

  constructor(args: Record<string, unknown>) {
    this.dev = args['dev'] as Dev
  }

  steps(): Step[] {
    const rawSteps = this.dev.config.raw_().steps ?? this.dev.config.raw_().up ?? []
    const sites: string[] = []
    let hasCaddy = false

    for (const rawStep of rawSteps) {
      if (!rawStep || typeof rawStep !== 'object') continue
      const c = (rawStep as Record<string, unknown>)['caddy'] as Record<string, unknown> | undefined
      if (!c) continue
      hasCaddy = true
      const rawSites = c['sites']
      if (Array.isArray(rawSites)) {
        for (const site of rawSites) {
          const host = typeof site === 'string' ? site : (site as Record<string, string>)['host']
          if (host) sites.push(host)
        }
      }
    }

    if (!hasCaddy) return []

    return [
      new InstallCaddyStep(),
      new PrepareCaddyStep(),
      new PostUpStep(sites),
    ]
  }

  validate(): boolean { return true }

  stepResolvers(): Record<string, StepResolver> {
    return { caddy: new CaddyStepResolver(this.dev) }
  }
}
