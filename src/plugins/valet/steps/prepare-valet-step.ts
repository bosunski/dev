import { existsSync, mkdirSync } from 'node:fs'
import { join } from 'node:path'
import type { Runner } from '../../../execution/runner.js'
import { BaseStep } from '../../../step/base-step.js'
import type { ValetPlugin } from '../valet-plugin.js'
import type { Dev } from '../../../dev.js'
import { UserException } from '../../../exceptions.js'

export class PrepareValetStep extends BaseStep {
  constructor(private readonly plugin: ValetPlugin, private readonly dev: Dev) { super() }

  name(): string { return 'Prepare Laravel Valet' }
  id(): string { return 'valet.prepare' }

  async done(_runner: Runner): Promise<boolean> {
    return false
  }

  async run(runner: Runner): Promise<boolean> {
    if (!this.plugin.localConfig) return false

    const found = this.findValetBin()
    if (!found) throw new UserException('Valet is not installed. Run `composer global require laravel/valet` to install it.')

    const { bin: valetBin, version } = found
    const phpLinkPath = runner.config.path('bin/php')

    this.plugin.localConfig.put('bin', valetBin)
    this.plugin.localConfig.put('version', version)
    this.plugin.localConfig.put('php', phpLinkPath)

    // Ensure sites storage directory exists
    const sitesPath = runner.config.globalPath('valet/sites')
    if (!existsSync(sitesPath)) mkdirSync(sitesPath, { recursive: true })

    return this.dev.updateEnvironment()
  }

  private findValetBin(): { bin: string; version: string } | null {
    const home = process.env['HOME'] ?? ''
    const candidates = [
      join(home, '.composer/vendor/bin/valet'),
      join(home, '.config/composer/vendor/bin/valet'),
    ]

    for (const bin of candidates) {
      if (!existsSync(bin)) continue
      const result = Bun.spawnSync([bin, '--version'], {
        stdout: 'pipe',
        stderr: 'pipe',
        env: process.env as Record<string, string>,
      })
      if (result.exitCode !== 0) continue
      const lines = new TextDecoder().decode(result.stdout).trim().split('\n').filter(l => l.trim())
      const version = lines[lines.length - 1]?.trim() ?? ''
      return { bin, version }
    }

    return null
  }
}
