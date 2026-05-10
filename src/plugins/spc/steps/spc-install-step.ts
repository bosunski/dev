import { existsSync } from 'node:fs'
import type { Runner } from '../../../execution/runner.js'
import { BaseStep } from '../../../step/base-step.js'

export class SpcInstallStep extends BaseStep {
  name(): string { return 'Install Static PHP Compiler' }
  id(): string { return 'spc.install' }

  async run(runner: Runner): Promise<boolean> {
    const spcDir = runner.config.globalPath('spc')
    return runner.exec(
      `curl -fsSL https://github.com/crazywhalecc/static-php-cli/releases/latest/download/spc-linux-x86_64.tar.gz | tar xz -C ${spcDir}`,
    )
  }

  async done(runner: Runner): Promise<boolean> {
    return existsSync(runner.config.globalPath('bin/spc'))
  }
}
