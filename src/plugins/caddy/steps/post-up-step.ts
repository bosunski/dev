import { createHash } from 'node:crypto'
import type { Runner } from '../../../execution/runner.js'
import { BaseStep } from '../../../step/base-step.js'
import type { DeferredStep } from '../../../types/step.js'
import { CaddyManager } from '../caddy-manager.js'

export class PostUpStep extends BaseStep implements DeferredStep {
  readonly deferred = true as const

  constructor(private readonly sites: string[]) { super() }

  name(): string | null { return null }
  id(): string { return `caddy.post-up.${createHash('md5').update(this.sites.join(',')).digest('hex')}` }

  async done(runner: Runner): Promise<boolean> {
    return new CaddyManager(runner.config).isDeployed()
  }

  async run(runner: Runner): Promise<boolean> {
    return new CaddyManager(runner.config).deploy(runner)
  }
}
