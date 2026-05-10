import type { Step } from '../../../types/step.js'
import type { Runner } from '../../../execution/runner.js'
import type { LocalValetConfig } from '../config/local-valet-config.js'
import { createHash } from 'node:crypto'

export type RawSite = string | { host: string; proxy?: string; secure?: boolean }

export class SiteStep implements Step {
  readonly host: string
  readonly proxy: string | null
  readonly secure: boolean

  constructor(site: RawSite, private readonly valetBinOrConfig: string | LocalValetConfig, private readonly phpVersion: string | null) {
    if (typeof site === 'string') {
      this.host = site
      this.proxy = null
      this.secure = true
    } else {
      this.host = site.host
      this.proxy = site.proxy ?? null
      this.secure = site.secure ?? true
    }
  }

  name(): string {
    return this.proxy
      ? `Valet: proxy ${this.host} → ${this.proxy}`
      : `Valet: link ${this.host}`
  }

  id(): string {
    return `valet-site-${createHash('md5').update(`${this.host}:${this.proxy ?? ''}:${this.secure}`).digest('hex')}`
  }

  async done(_runner: Runner): Promise<boolean> {
    return false
  }

  private resolvedValetBin(): string {
    if (typeof this.valetBinOrConfig === 'string') return this.valetBinOrConfig
    return this.valetBinOrConfig.get('bin') || 'valet'
  }

  async run(runner: Runner): Promise<boolean> {
    const valet = this.resolvedValetBin()

    if (this.proxy) {
      const args = [valet, 'proxy', this.host, this.proxy]
      if (this.secure) args.push('--secure')
      if (!await runner.exec(args)) return false
    } else {
      const args = [valet, 'link', this.host]
      if (this.secure) args.push('--secure')
      if (!await runner.exec(args)) return false

      if (this.phpVersion) {
        const phpFormula = this.phpVersion === '8.3' ? 'php' : `php@${this.phpVersion}`
        if (!await runner.exec([valet, 'isolate', '--site', this.host, phpFormula])) return false
      }
    }

    return true
  }
}
