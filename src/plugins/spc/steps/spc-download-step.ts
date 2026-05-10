import { existsSync } from 'node:fs'
import type { Runner } from '../../../execution/runner.js'
import { BaseStep } from '../../../step/base-step.js'
import type { SpcConfig } from '../config/spc-config.js'

export class SpcDownloadStep extends BaseStep {
  constructor(private readonly config: SpcConfig) { super() }
  name(): string { return `Download PHP ${this.config.phpVersion} sources` }
  id(): string { return `spc.download.${this.config.phpVersion}` }

  async run(runner: Runner): Promise<boolean> {
    const cmd = [this.config.bin(), 'download', ...this.config.extensions]
    return runner.exec(cmd, runner.config.globalPath(`spc/${this.config.phpVersion}`))
  }

  async done(_runner: Runner): Promise<boolean> {
    return existsSync(this.config.phpPath('buildroot/bin/php'))
  }
}
