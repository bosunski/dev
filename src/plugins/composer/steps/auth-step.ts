import type { Runner } from '../../../execution/runner.js'
import { BaseStep } from '../../../step/base-step.js'

export type RawAuth = {
  host: string
  username?: string
  password?: string
}

export class AuthStep extends BaseStep {
  constructor(private readonly auth: RawAuth) { super() }

  name(): string { return `Configure Composer auth: ${this.auth.host}` }
  id(): string { return `composer-auth-${this.auth.host}` }

  async done(_runner: Runner): Promise<boolean> {
    const result = Bun.spawnSync(
      ['composer', 'global', 'config', `http-basic.${this.auth.host}`],
      { stdout: 'pipe', stderr: 'pipe' },
    )
    return result.exitCode === 0
  }

  async run(runner: Runner): Promise<boolean> {
    const io = runner.getIO()
    const username = this.auth.username ?? await io.text(`Username for ${this.auth.host}`)
    const password = this.auth.password ?? await io.password(`Password for ${this.auth.host}`)
    return runner.exec([
      'composer', 'global', 'config',
      `http-basic.${this.auth.host}`,
      username,
      password,
    ])
  }
}
