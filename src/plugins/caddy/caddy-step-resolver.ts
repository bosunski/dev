import type { StepResolver, Step } from '../../types/step.js'
import type { Dev } from '../../dev.js'
import { CaddyStep } from './steps/caddy-step.js'

export type RawCaddySite = string | { host: string; proxy?: string; secure?: boolean }

export type RawCaddyConfig = {
  sites?: RawCaddySite[]
}

export class CaddyStepResolver implements StepResolver {
  constructor(private readonly dev: Dev) {}

  resolve(args: unknown): Step {
    const sitesDir = this.dev.config.globalPath('caddy/sites')
    return new CaddyStep(args as RawCaddyConfig, sitesDir)
  }
}
