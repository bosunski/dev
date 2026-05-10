import { existsSync } from 'node:fs'
import type { Runner } from '../../../execution/runner.js'
import { BaseStep } from '../../../step/base-step.js'
import type { SpcConfig } from '../config/spc-config.js'

export class SpcBuildStep extends BaseStep {
  constructor(private readonly config: SpcConfig) { super() }
  name(): string { return `Build PHP ${this.config.phpVersion}` }
  id(): string { return `spc.build.${this.config.phpVersion}.${this.config.md5}` }

  async run(runner: Runner): Promise<boolean> {
    return runner.exec(this.config.buildCommand(), runner.config.globalPath(`spc/${this.config.phpVersion}`))
  }

  async done(_runner: Runner): Promise<boolean> {
    return existsSync(this.config.phpPath('buildroot/bin/php'))
  }
}
