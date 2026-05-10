import { createHash } from 'node:crypto'
import type { Step } from '../../../types/step.js'
import type { Runner } from '../../../execution/runner.js'
import { CaddySiteStep } from './caddy-site-step.js'
import type { RawCaddyConfig } from '../caddy-step-resolver.js'

export class CaddyStep implements Step {
  private readonly subSteps: Step[]
  private readonly _id: string

  constructor(config: RawCaddyConfig, sitesDir: string) {
    this.subSteps = []

    for (const site of config.sites ?? []) {
      this.subSteps.push(new CaddySiteStep(site, sitesDir))
    }

    this._id = `caddy-${createHash('md5').update(JSON.stringify(config)).digest('hex')}`
  }

  name(): string | null {
    return null
  }

  id(): string {
    return this._id
  }

  async done(_runner: Runner): Promise<boolean> {
    return false
  }

  async run(runner: Runner): Promise<boolean> {
    return runner.execute(this.subSteps)
  }
}
