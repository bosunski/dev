import type { Step } from '../types/step.js'
import type { Runner } from '../execution/runner.js'

export abstract class BaseStep implements Step {
  abstract name(): string | null
  abstract run(runner: Runner): Promise<boolean>

  async done(_runner: Runner): Promise<boolean> {
    return false
  }

  abstract id(): string
}
