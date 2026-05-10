import type { Dev } from '../../../dev.js'
import type { Step } from '../../../types/step.js'
import { EnsureDockerStep } from '../steps/mysql/ensure-docker-step.js'
import { StartContainerStep } from '../steps/mysql/start-container-step.js'
import { UpdateEnvironmentStep } from '../steps/mysql/update-environment-step.js'
import { CreateDatabaseStep } from '../steps/mysql/create-database-step.js'

export type RawMySqlConfig = {
  databases: string | string[]
  version?: string
}

export class MySqlConfig {
  constructor(
    private readonly config: RawMySqlConfig,
    public readonly dev: Dev,
  ) {}

  steps(): Step[] {
    return [
      new EnsureDockerStep(),
      new StartContainerStep(),
      new UpdateEnvironmentStep(this.dev),
      new CreateDatabaseStep(this.config.databases),
    ]
  }
}
