import { existsSync } from 'node:fs'
import { createHash } from 'node:crypto'
import type { Runner } from '../../../execution/runner.js'
import { BaseStep } from '../../../step/base-step.js'
import type { DeferredStep } from '../../../types/step.js'

export class PostUpStep extends BaseStep implements DeferredStep {
  readonly deferred = true as const

  constructor(private readonly sites: string[]) {
    super()
  }

  name(): string | null { return null }

  id(): string {
    return `caddy.post-up.${createHash('md5').update(this.sites.join(',')).digest('hex')}`
  }

  async done(_runner: Runner): Promise<boolean> {
    return this.sites.length === 0
  }

  async run(runner: Runner): Promise<boolean> {
    const caddyfile = runner.config.globalPath('caddy/Caddyfile')
    const caddyBin = runner.config.brewPath('bin/caddy')
    if (!existsSync(caddyBin) || !existsSync(caddyfile)) return true
    return runner.exec([caddyBin, 'reload', '--config', caddyfile])
  }
}
