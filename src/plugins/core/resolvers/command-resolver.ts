import type { StepResolver } from '../../../types/step.js'
import type { Step } from '../../../types/step.js'
import type { RawCommand } from '../../../types/config.js'
import { CustomStep } from '../steps/custom-step.js'

export class CommandResolver implements StepResolver {
  constructor(private readonly commands: Record<string, RawCommand>) {}

  resolve(args: unknown): Step {
    if (typeof args !== 'string') {
      throw new Error('Command configuration should be the name of a command!')
    }

    const command = this.commands[args]
    if (!command) {
      throw new Error(`Command \`${args}\` not found in configuration!`)
    }

    return new CustomStep(command)
  }
}
