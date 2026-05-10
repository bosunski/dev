import { existsSync, readFileSync, writeFileSync } from 'node:fs'
import { join } from 'node:path'
import { createHash } from 'node:crypto'
import type { Runner } from '../../../execution/runner.js'
import { BaseStep } from '../../../step/base-step.js'
import type { RawCaddySite } from '../caddy-step-resolver.js'

export class CaddySiteStep extends BaseStep {
  readonly host: string
  readonly proxy: string | null
  readonly secure: boolean

  constructor(site: RawCaddySite, private readonly sitesDir: string) {
    super()
    if (typeof site === 'string') {
      this.host = site
      this.proxy = null
      this.secure = false
    } else {
      this.host = site.host
      this.proxy = site.proxy ?? null
      this.secure = site.secure ?? false
    }
  }

  name(): string {
    return this.proxy
      ? `Caddy: proxy ${this.host} → ${this.proxy}`
      : `Caddy: serve ${this.host}`
  }

  id(): string {
    return `caddy-site-${createHash('md5').update(`${this.host}:${this.proxy ?? ''}:${this.secure}`).digest('hex')}`
  }

  private confPath(): string {
    return join(this.sitesDir, `${this.host}.conf`)
  }

  private buildConf(runner: Runner): string {
    const addr = `https://${this.host}`
    if (this.proxy) {
      return `${addr} {\n  reverse_proxy ${this.proxy}\n}\n`
    }

    return `${addr} {\n  root * ${runner.config.cwd()}\n  file_server\n}\n`
  }

  async done(runner: Runner): Promise<boolean> {
    const confPath = this.confPath()
    if (!existsSync(confPath)) return false
    return readFileSync(confPath, 'utf8') === this.buildConf(runner)
  }

  async run(runner: Runner): Promise<boolean> {
    writeFileSync(this.confPath(), this.buildConf(runner))
    return true
  }
}
