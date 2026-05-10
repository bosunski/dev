import type { Dev } from '../../../dev.js'
import type { StepResolver, Step } from '../../../types/step.js'
import type { RawMySqlConfig } from '../config/mysql-config.js'
import { MySqlConfig } from '../config/mysql-config.js'
import { UserException } from '../../../exceptions.js'

export class MySqlResolver implements StepResolver {
  constructor(private readonly dev: Dev) {}

  resolve(args: unknown): Step {
    if (!args || typeof args !== 'object') {
      throw new Error('MySQL configuration should be an object!')
    }

    const raw = args as Record<string, unknown>
    if (!raw['databases']) {
      throw new UserException('MySQL configuration should have a databases key!')
    }

    const config = new MySqlConfig(raw as unknown as RawMySqlConfig, this.dev)
    // MySqlConfig returns multiple steps — wrap in a composite step
    return new MySqlCompositeStep(config)
  }
}

import type { Runner } from '../../../execution/runner.js'
import { BaseStep } from '../../../step/base-step.js'

class MySqlCompositeStep extends BaseStep {
  constructor(private readonly config: MySqlConfig) { super() }

  name(): string | null { return 'Setup MySQL' }

  async run(runner: Runner): Promise<boolean> {
    for (const step of this.config.steps()) {
      if (!(await runner.execute(step))) return false
    }
    return true
  }

  async done(_runner: Runner): Promise<boolean> { return false }
  id(): string { return `mysql-${this.config.dev.config.path()}` }
}
