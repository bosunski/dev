import type { Runner } from '../../../execution/runner.js'
import { BaseStep } from '../../../step/base-step.js'
import type { RawServiceConfig, ServiceManager } from '../config/service-manager.js'

export class ServiceStep extends BaseStep {
  private readonly service: string

  constructor(private readonly manager: ServiceManager, config: RawServiceConfig) {
    super()
    this.service = manager.resolve(config)
  }

  name(): string { return `Start service: ${this.service}` }
  id(): string { return `service.${this.manager.packageManager}.${this.service}` }

  async run(runner: Runner): Promise<boolean> {
    return runner.exec(this.manager.startCommand(this.service))
  }

  async done(runner: Runner): Promise<boolean> {
    return runner.withoutShadowEnv().exec(this.manager.checkCommand(this.service))
  }
}
