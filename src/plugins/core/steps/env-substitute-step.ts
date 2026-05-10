import { existsSync, readFileSync, writeFileSync, copyFileSync } from 'node:fs'
import type { Config } from '../../../config/config.js'
import type { Runner } from '../../../execution/runner.js'
import { BaseStep } from '../../../step/base-step.js'

export class EnvSubstituteStep extends BaseStep {
  constructor(private readonly config: Config) {
    super()
  }

  name(): string {
    return 'Substituting variables in .env file with discovered .env.example'
  }

  async run(runner: Runner): Promise<boolean> {
    const cfg = runner.config

    if (!existsSync(cfg.cwd('.env')) && existsSync(cfg.cwd('.env.example'))) {
      copyFileSync(cfg.cwd('.env.example'), cfg.cwd('.env'))
    }

    if (!existsSync(cfg.cwd('.env.example')) || !existsSync(cfg.cwd('.env'))) {
      return true
    }

    const sampleContent = readFileSync(cfg.cwd('.env.example'), 'utf8')
    let envContent = existsSync(cfg.cwd('.env')) ? readFileSync(cfg.cwd('.env'), 'utf8') : ''

    const sampleEnvs = this.parseEnv(sampleContent)
    const currentEnvs = this.parseEnv(envContent)

    if (Object.keys(sampleEnvs).length > 0 && !envContent.endsWith('\n')) {
      envContent += '\n'
    }

    let envWasAdded = false
    for (const [key, value] of Object.entries(sampleEnvs)) {
      const insert = `${key}="${value ?? ''}"`
      const exists = key in currentEnvs

      if (!exists) {
        envContent += insert + '\n'
        envWasAdded = true
        continue
      }

      const hasValue = !['', 'null', 'NULL'].includes(currentEnvs[key] ?? '')
      if (envContent.includes(`${key}=`) && hasValue) continue

      const hasSampleValue = !['', 'null', 'NULL'].includes(value ?? '')
      if (!hasValue && hasSampleValue) {
        const replaced = envContent.replace(new RegExp(`${key}=(.*)`, 'm'), insert)
        if (replaced !== envContent) {
          envContent = replaced
          envWasAdded = true
        }
      }
    }

    // Config envs take precedence
    const configEnvs = await cfg.envs()
    for (const [key, value] of configEnvs) {
      const insert = `${key}="${value}"`
      if (!new RegExp(`^${key}=(.*)`, 'm').test(envContent)) {
        if (!envContent.endsWith('\n')) envContent += '\n'
        envContent += insert + '\n'
        envWasAdded = true
      } else {
        const replaced = envContent.replace(new RegExp(`^${key}=(.*)`, 'm'), insert)
        if (replaced !== envContent) {
          envContent = replaced
          envWasAdded = true
        }
      }
    }

    if (envWasAdded) {
      envContent = envContent.replace(/\n{3,}/g, '\n\n')
      if (!envContent.endsWith('\n')) envContent += '\n'
      writeFileSync(cfg.cwd('.env'), envContent)
    }

    return true
  }

  async done(_runner: Runner): Promise<boolean> {
    return false
  }

  private parseEnv(content: string): Record<string, string | null> {
    const result: Record<string, string | null> = {}
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
    return `env-substitute-${this.config.projectName()}`
  }
}
