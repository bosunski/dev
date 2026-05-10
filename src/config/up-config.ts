import type { RawStep, RawScript } from '../types/config.js'
import type { Step, StepResolver } from '../types/step.js'
import { UserException } from '../exceptions.js'

export class UpConfig {
  constructor(private readonly raw: RawStep[]) {}

  steps(resolvers: Record<string, StepResolver>): Step[] {
    const steps: Step[] = []

    for (const rawStep of this.raw) {
      const step = this.resolveStep(rawStep, resolvers)
      if (step) steps.push(step)
    }

    return steps
  }

  private resolveStep(rawStep: RawStep, resolvers: Record<string, StepResolver>): Step | null {
    if (!rawStep || typeof rawStep !== 'object') return null

    // Check if it's a typed step (e.g., { script: {...} }, { mysql: {...} })
    for (const [key, resolver] of Object.entries(resolvers)) {
      if (key in rawStep) {
        const args = (rawStep as Record<string, unknown>)[key]
        return resolver.resolve(args)
      }
    }

    // Fall back to treating the whole object as a script step
    if ('run' in rawStep) {
      const scriptResolver = resolvers['script']
      if (scriptResolver) {
        return scriptResolver.resolve(rawStep as RawScript)
      }
    }

    const keys = Object.keys(rawStep)
    throw new UserException(`Unknown step type: ${keys.join(', ')}. No resolver found.`)
  }
}
