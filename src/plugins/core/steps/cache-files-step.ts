import { existsSync, readFileSync } from 'node:fs'
import { createHash } from 'node:crypto'
import type { Dev } from '../../../dev.js'
import type { Runner } from '../../../execution/runner.js'
import { BaseStep } from '../../../step/base-step.js'
import { Config } from '../../../config/config.js'

export class CacheFilesStep extends BaseStep {
  constructor(private readonly dev: Dev) {
    super()
  }

  name(): string { return 'Lock Files' }

  async run(_runner: Runner): Promise<boolean> {
    const locks: Record<string, string> = {}
    for (const file of Config.LockFiles) {
      const path = this.dev.config.cwd(file)
      if (existsSync(path)) {
        const hash = createHash('md5').update(readFileSync(path)).digest('hex')
        locks[file] = hash
      }
    }
    this.dev.config.settings.locks = locks
    return true
  }

  async done(_runner: Runner): Promise<boolean> { return false }

  id(): string { return 'cache-files' }
}
