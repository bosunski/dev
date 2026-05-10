import type { Runner } from '../../../execution/runner.js'
import { BaseStep } from '../../../step/base-step.js'
import type { SpcConfig } from '../config/spc-config.js'

export class SpcCacheStep extends BaseStep {
  constructor(private readonly config: SpcConfig) { super() }

  name(): string { return 'Cache SPC sources' }
  id(): string { return `spc.cache.${this.config.phpVersion}` }

  async done(_runner: Runner): Promise<boolean> { return false }
  async run(_runner: Runner): Promise<boolean> { return true }
}
