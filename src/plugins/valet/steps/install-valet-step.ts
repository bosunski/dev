import { existsSync } from 'node:fs'
import type { Runner } from '../../../execution/runner.js'
import { BaseStep } from '../../../step/base-step.js'
import { UserException } from '../../../exceptions.js'
import { composerValetBin, installedValetBin } from '../valet-bin.js'

export class InstallValetStep extends BaseStep {
  private valetBinary = 'vendor/bin/valet'

  name(): string { return 'Install Laravel Valet' }
  id(): string { return 'valet.install' }

  async done(_runner: Runner): Promise<boolean> {
    this.valetBinary = await this.valetBinPath()
    return existsSync(this.valetBinary)
  }

  async run(runner: Runner): Promise<boolean> {
    this.valetBinary = await this.valetBinPath()
    const pkg = runner.config.isDarwin() ? 'laravel/valet' : 'cpriego/valet-linux'

    // composer global update ensures dependencies work with the current PHP version
    if (!await runner.exec(`composer global require --no-interaction ${pkg} && composer global update --no-interaction && ${this.valetBinary} install`)) return false

    if (runner.config.isLinux()) return true
    return runner.exec(`${this.valetBinary} trust`)
  }

  private async valetBinPath(): Promise<string> {
    const valetBin = installedValetBin() ?? composerValetBin()
    if (valetBin) return valetBin
    throw new UserException('Attempted to install Valet but Composer is not installed or not in PATH.')
  }
}
