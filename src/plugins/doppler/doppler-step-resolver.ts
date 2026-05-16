import type { StepResolver, Step } from '../../types/step.js'
import { EnsureDopplerStep } from './steps/ensure-doppler-step.js'
import { DopplerSecretsStep } from './steps/doppler-secrets-step.js'
import { DopplerSetupStep } from './steps/doppler-setup-step.js'

export type DopplerConfig = {
  project: string
  config: string
  setup?: boolean
}

export class DopplerStepResolver implements StepResolver {
  resolve(args: unknown): Step {
    if (typeof args !== 'object' || args === null || Array.isArray(args)) {
      throw new Error('Doppler configuration should be an object with "project" and "config" keys.')
    }

    const cfg = args as DopplerConfig

    if (!cfg.project || !cfg.config) {
      throw new Error('Doppler configuration requires "project" and "config" keys.')
    }

    if (cfg.setup) {
      return new DopplerSetupStep(cfg)
    }

    return new DopplerSecretsStep(cfg, new EnsureDopplerStep())
  }
}
