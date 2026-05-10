import { existsSync, appendFileSync } from 'node:fs'
import type { Runner } from '../../../../execution/runner.js'
import { BaseStep } from '../../../../step/base-step.js'
import { UserException } from '../../../../exceptions.js'
import { BrewStep } from '../brew-step.js'

export class EnsureShadowEnvStep extends BaseStep {
  private installed = false
  private hookInstalled = false

  name(): string {
    return 'Ensure ShadowEnv is Set Up'
  }

  async run(runner: Runner): Promise<boolean> {
    if (!this.installed) {
      const installed = await runner.withoutShadowEnv().execute(new BrewStep(['shadowenv']))
      if (!installed) return false
    }

    if (this.hookInstalled) return true

    const shell = runner.shell(null)
    if (!shell) {
      throw new UserException('Unable to determine the current shell. Make sure you are using one of the supported shells: bash, zsh, fish.')
    }

    if (!existsSync(shell.profile)) {
      throw new UserException(`Unable to find the profile file: ${shell.profile}. Please setup Shadowenv manually.`)
    }

    const evalLine = this.evalConfig(shell.name)
    try {
      appendFileSync(shell.profile, evalLine)
    } catch {
      throw new UserException(`Unable to update the profile file: ${shell.profile}. Please setup Shadowenv manually.`)
    }

    return this.done(runner)
  }

  private evalConfig(shell: string): string {
    return `\n# Shadow Env\neval "$(shadowenv init ${shell})"\n`
  }

  async done(runner: Runner): Promise<boolean> {
    const [hookInstalled, binaryInstalled] = runner.checkShadowEnv(true)
    if (hookInstalled) {
      this.hookInstalled = true
      return true
    }

    this.installed = binaryInstalled
    return false
  }

  id(): string {
    return 'shadowenv.setup'
  }
}
