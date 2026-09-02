import { existsSync, readFileSync, writeFileSync, copyFileSync } from 'node:fs'
import type { Config } from '../../../config/config.js'
import type { Runner } from '../../../execution/runner.js'
import { BaseStep } from '../../../step/base-step.js'

const MANAGED_START = '# DEV managed environment — generated from dev.yml'
const MANAGED_END = '# End DEV managed environment'

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
    const originalContent = readFileSync(cfg.cwd('.env'), 'utf8')
    let envContent = this.withoutManagedBlock(originalContent)

    const sampleEnvs = this.parseEnv(sampleContent)
    let currentEnvs = this.parseEnv(envContent)

    if (Object.keys(sampleEnvs).length > 0 && !envContent.endsWith('\n')) {
      envContent += '\n'
    }

    for (const [key, value] of Object.entries(sampleEnvs)) {
      const insert = this.assignment(key, value ?? '')
      const exists = key in currentEnvs

      if (!exists) {
        envContent += insert + '\n'
        continue
      }

      const hasValue = !['', 'null', 'NULL'].includes(currentEnvs[key] ?? '')
      if (envContent.includes(`${key}=`) && hasValue) continue

      const hasSampleValue = !['', 'null', 'NULL'].includes(value ?? '')
      if (!hasValue && hasSampleValue) {
        envContent = envContent.replace(this.assignmentPattern(key), insert)
      }
    }

    // Config envs take precedence
    const configEnvs = await cfg.envs()
    const managed: string[] = []
    currentEnvs = this.parseEnv(envContent)
    for (const [key, value] of configEnvs) {
      const insert = this.assignment(key, value)
      if (key in sampleEnvs) {
        envContent = key in currentEnvs
          ? envContent.replace(this.assignmentPattern(key), insert)
          : envContent + insert + '\n'
        continue
      }
      envContent = envContent.replace(this.assignmentLinePattern(key), '')
      managed.push(insert)
    }

    envContent = envContent.replace(/\n{3,}/g, '\n\n').trimEnd() + '\n'
    if (managed.length > 0) {
      envContent += `\n${MANAGED_START}\n${managed.join('\n')}\n${MANAGED_END}\n`
    }

    if (envContent !== originalContent) {
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

  private assignment(key: string, value: string): string {
    if (!value.includes("'")) return `${key}='${value}'`
    const escaped = value
      .replaceAll('\\', '\\\\')
      .replaceAll('"', '\\"')
      .replaceAll('\r', '\\r')
      .replaceAll('\n', '\\n')
    return `${key}="${escaped}"`
  }

  private assignmentPattern(key: string): RegExp {
    return new RegExp(`^${this.escapeRegExp(key)}=.*$`, 'm')
  }

  private assignmentLinePattern(key: string): RegExp {
    return new RegExp(`^${this.escapeRegExp(key)}=.*(?:\\n|$)`, 'm')
  }

  private escapeRegExp(value: string): string {
    return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
  }

  private withoutManagedBlock(content: string): string {
    const start = content.indexOf(MANAGED_START)
    if (start === -1) return content
    const end = content.indexOf(MANAGED_END, start)
    if (end === -1) return content.slice(0, start)
    return content.slice(0, start) + content.slice(end + MANAGED_END.length).replace(/^\n/, '')
  }

  id(): string {
    return `env-substitute-${this.config.projectName()}`
  }
}
