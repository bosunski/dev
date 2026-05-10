import type { Runner } from '../../../execution/runner.js'
import { BaseStep } from '../../../step/base-step.js'
import type { Config } from '../../../config/config.js'

export class PromptEnvStep extends BaseStep {
  constructor(private readonly config: Config) {
    super()
  }

  name(): string | null {
    return null
  }

  async done(_runner: Runner): Promise<boolean> {
    return false
  }

  async run(_runner: Runner): Promise<boolean> {
    await this.config.envs()
    return true
  }

  id(): string {
    return `prompt-env-${this.config.projectName()}`
  }
}
