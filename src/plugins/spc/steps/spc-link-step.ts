import { existsSync, symlinkSync, unlinkSync, mkdirSync } from 'node:fs'
import type { Runner } from '../../../execution/runner.js'
import { BaseStep } from '../../../step/base-step.js'
import type { SpcConfig } from '../config/spc-config.js'

export class SpcLinkStep extends BaseStep {
  constructor(private readonly config: SpcConfig) { super() }
  name(): string { return `Link PHP ${this.config.phpVersion} binary` }
  id(): string { return `spc.link.${this.config.phpVersion}` }

  async run(runner: Runner): Promise<boolean> {
    const binDir = runner.config.globalPath('bin')
    if (!existsSync(binDir)) mkdirSync(binDir, { recursive: true })

    const phpLink = runner.config.globalPath('bin/php')
    if (existsSync(phpLink)) unlinkSync(phpLink)

    const phpBin = this.config.phpPath('buildroot/bin/php')
    symlinkSync(phpBin, phpLink)

    return true
  }

  async done(runner: Runner): Promise<boolean> {
    return existsSync(runner.config.globalPath('bin/php'))
  }
}
