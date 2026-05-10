import type { Runner } from '../execution/runner.js'

export interface Step {
  readonly PRIORITY_HIGH?: 1
  readonly PRIORITY_NORMAL?: 2
  readonly PRIORITY_LOW?: 3

  name(): string | null
  run(runner: Runner): Promise<boolean>
  done(runner: Runner): Promise<boolean>
  id(): string
}

export interface DeferredStep extends Step {
  readonly deferred: true
}

export interface StepResolver {
  resolve(args: unknown): Step
}
