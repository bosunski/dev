import type { Runner } from '../../../../execution/runner.js'
import type { Dev } from '../../../../dev.js'
import { BaseStep } from '../../../../step/base-step.js'

export class UpdateEnvironmentStep extends BaseStep {
  constructor(private readonly dev: Dev) {
    super()
  }

  id(): string { return `mysql-update-environment-${this.dev.config.path()}` }
  name(): string { return 'Update MySQL environment' }

  async run(runner: Runner): Promise<boolean> {
    runner.config.putenv('DEV_MYSQL_HOST', '127.0.0.1')
    return this.dev.updateEnvironment()
  }

  async done(_runner: Runner): Promise<boolean> {
    return false
  }
}
