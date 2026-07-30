import { Command } from '@oclif/core'
import { getDevContext } from '../context.js'
import { EnsureShadowEnvStep } from '../plugins/core/steps/shadowenv/ensure-shadowenv-step.js'
import { ShadowEnvStep } from '../plugins/core/steps/shadowenv/shadowenv-step.js'
import { EnvSubstituteStep } from '../plugins/core/steps/env-substitute-step.js'
import { PromptEnvStep } from '../plugins/core/steps/prompt-env-step.js'
import { UserException } from '../exceptions.js'
import type { Step } from '../types/step.js'

export default class Refresh extends Command {
  static id = 'refresh'
  static description = 'Update project environment and Shadowenv configs without running all steps'

  async run(): Promise<void> {
    await this.parse(Refresh)
    const { dev } = await getDevContext()

    if (!dev.isInitialized()) {
      throw new UserException('DEV is not initialized for this project. Run `dev init` to initialize DEV.')
    }

    const steps: Step[] = [
      new PromptEnvStep(dev.config),
      new EnsureShadowEnvStep(),
      new ShadowEnvStep(dev),
      new EnvSubstituteStep(dev.config),
    ]

    this.log('🔄 Refreshing environment...')

    for (const step of steps) {
      if (!(await dev.runner.execute(step))) {
        const name = step.name() || step.id()
        throw new UserException(`Failed to run step '${name}'`)
      }
    }

    dev.config.writeSettings()
  }
}
