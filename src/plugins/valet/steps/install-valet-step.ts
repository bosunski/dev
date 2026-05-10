import { existsSync } from 'node:fs'
import { join } from 'node:path'
import type { Runner } from '../../../execution/runner.js'
import { BaseStep } from '../../../step/base-step.js'
import { UserException } from '../../../exceptions.js'

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
    if (!await runner.exec(`composer global require ${pkg} && composer global update && ${this.valetBinary} install`)) return false

    if (runner.config.isLinux()) return true
    return runner.exec(`${this.valetBinary} trust`)
  }

  private async valetBinPath(): Promise<string> {
    const home = process.env['HOME'] ?? ''
    // Prefer ~/.composer; fall back to ~/.config/composer (XDG path used by some setups)
    for (const candidate of [join(home, '.composer/vendor/bin/valet'), join(home, '.config/composer/vendor/bin/valet')]) {
      if (existsSync(candidate)) return candidate
    }

    // Neither exists yet — determine install target from composer
    const proc = Bun.spawnSync(['composer', 'global', 'config', 'home', '--no-interaction'], {
      stdout: 'pipe',
      stderr: 'pipe',
    })
    if (proc.exitCode !== 0) {
      throw new UserException('Attempted to install Valet but Composer is not installed or not in PATH.')
    }
    const lines = new TextDecoder().decode(proc.stdout).trim().split('\n').filter(l => l.trim())
    const composerHome = lines[lines.length - 1]!.trim()
    return join(composerHome, 'vendor/bin/valet')
  }
}
