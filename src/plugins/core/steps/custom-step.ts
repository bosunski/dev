import { randomBytes } from 'node:crypto'
import { join } from 'node:path'
import type { Runner } from '../../../execution/runner.js'
import type { RawScript } from '../../../types/config.js'
import { BaseStep } from '../../../step/base-step.js'

export class CustomStep extends BaseStep {
  private readonly _id = randomBytes(5).toString('hex')

  constructor(private readonly config: RawScript) {
    super()
  }

  name(): string {
    return this.config.desc ?? ''
  }

  command(): string | string[] {
    return this.config.run
  }

  checkCommand(): string | null {
    return this.config['met?'] ?? null
  }

  private cwd(runner: Runner): string {
    return this.config.cwd ? join(runner.config.cwd(), this.config.cwd) : runner.config.cwd()
  }

  async run(runner: Runner): Promise<boolean> {
    const cmd = this.command()
    if (!cmd) return false
    return runner.exec(cmd, this.cwd(runner))
  }

  async done(runner: Runner): Promise<boolean> {
    const cmd = this.checkCommand()
    if (!cmd) return false
    return runner.exec(cmd, this.cwd(runner))
  }

  id(): string {
    return this._id
  }
}
