import { createHash } from 'node:crypto'
import type { Step } from '../../../types/step.js'
import type { Runner } from '../../../execution/runner.js'
import { CaddySiteStep } from './caddy-site-step.js'
import type { RawCaddyConfig } from '../caddy-step-resolver.js'
import { existsSync, lstatSync, mkdirSync, readdirSync, unlinkSync } from 'node:fs'
import { join } from 'node:path'
import { caddySiteFilename } from '../caddy-layout.js'

export class CaddyStep implements Step {
  private readonly subSteps: Step[]
  private readonly _id: string
  private readonly sites: NonNullable<RawCaddyConfig['sites']>

  constructor(
    config: RawCaddyConfig,
    private readonly sitesDir: string,
    private readonly deploy?: (runner: Runner) => Promise<boolean>,
  ) {
    this.subSteps = []
    this.sites = config.sites ?? []

    for (const site of this.sites) {
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
    if (!existsSync(this.sitesDir)) mkdirSync(this.sitesDir, { recursive: true })

    const expected = new Set(this.sites.map(site => caddySiteFilename(
      typeof site === 'string' ? site : site.host,
    )))
    for (const file of readdirSync(this.sitesDir)) {
      if (!file.endsWith('.conf') || expected.has(file)) continue
      const path = join(this.sitesDir, file)
      const stat = lstatSync(path)
      if (stat.isFile() || stat.isSymbolicLink()) unlinkSync(path)
    }

    if (!await runner.execute(this.subSteps)) return false
    return this.deploy ? this.deploy(runner) : true
  }
}
