import { existsSync, readFileSync, writeFileSync } from 'node:fs'
import type { Runner } from '../../../execution/runner.js'
import { BaseStep } from '../../../step/base-step.js'
import type { DopplerConfig } from '../doppler-step-resolver.js'
import type { EnsureDopplerStep } from './ensure-doppler-step.js'

export class DopplerSecretsStep extends BaseStep {
  constructor(
    private readonly config: DopplerConfig,
    private readonly ensureStep: EnsureDopplerStep,
  ) {
    super()
  }

  name(): string {
    return `Fetch secrets from Doppler (${this.config.project}/${this.config.config})`
  }

  async run(runner: Runner): Promise<boolean> {
    if (!(await runner.execute(this.ensureStep))) return false

    const proc = Bun.spawnSync([
      'doppler', 'secrets', 'download',
      '--project', this.config.project,
      '--config', this.config.config,
      '--format', 'env',
      '--no-file',
    ])

    if (proc.exitCode !== 0) {
      runner.getIO().error(`Failed to fetch secrets from Doppler: ${new TextDecoder().decode(proc.stderr)}`)
      return false
    }

    const secrets = new TextDecoder().decode(proc.stdout)
    const envPath = runner.config.cwd('.env')
    const existingContent = existsSync(envPath) ? readFileSync(envPath, 'utf8') : ''
    const merged = this.mergeEnv(existingContent, secrets)

    writeFileSync(envPath, merged)
    return true
  }

  async done(runner: Runner): Promise<boolean> {
    const envPath = runner.config.cwd('.env')
    if (!existsSync(envPath)) return false

    const proc = Bun.spawnSync([
      'doppler', 'secrets', 'download',
      '--project', this.config.project,
      '--config', this.config.config,
      '--format', 'env',
      '--no-file',
    ])

    if (proc.exitCode !== 0) return false

    const remoteSecrets = this.parseEnv(new TextDecoder().decode(proc.stdout))
    const localEnv = this.parseEnv(readFileSync(envPath, 'utf8'))

    return Object.entries(remoteSecrets).every(([key, value]) => localEnv[key] === value)
  }

  private mergeEnv(existing: string, incoming: string): string {
    const existingEntries = this.parseEnv(existing)
    const incomingEntries = this.parseEnv(incoming)

    const merged = { ...existingEntries, ...incomingEntries }

    let content = Object.entries(merged)
      .map(([key, value]) => `${key}="${value}"`)
      .join('\n')

    if (content && !content.endsWith('\n')) content += '\n'
    return content
  }

  private parseEnv(content: string): Record<string, string> {
    const result: Record<string, string> = {}
    for (const line of content.split('\n')) {
      const trimmed = line.trim()
      if (!trimmed || trimmed.startsWith('#')) continue
      const eqIdx = trimmed.indexOf('=')
      if (eqIdx === -1) continue
      const key = trimmed.slice(0, eqIdx).trim()
      let val = trimmed.slice(eqIdx + 1).trim()
      if ((val.startsWith('"') && val.endsWith('"')) || (val.startsWith("'") && val.endsWith("'"))) {
        val = val.slice(1, -1)
      }
      result[key] = val
    }
    return result
  }

  id(): string {
    return `doppler-secrets-${this.config.project}-${this.config.config}`
  }
}
