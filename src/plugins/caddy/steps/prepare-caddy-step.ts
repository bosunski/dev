import { existsSync, mkdirSync, writeFileSync, readFileSync } from 'node:fs'
import { join } from 'node:path'
import type { Runner } from '../../../execution/runner.js'
import { BaseStep } from '../../../step/base-step.js'
import { z } from 'zod'

const caddyMetadataSchema = z.record(z.string(), z.unknown())

export class PrepareCaddyStep extends BaseStep {
  name(): string { return 'Prepare Caddy' }
  id(): string { return 'caddy.prepare' }

  async done(_runner: Runner): Promise<boolean> {
    return false
  }

  async run(runner: Runner): Promise<boolean> {
    const caddyDir = runner.config.globalPath('caddy')
    const projectsDir = runner.config.globalPath('caddy/projects')
    const logsDir = runner.config.globalPath('caddy/logs')
    const caddyfile = join(caddyDir, 'Caddyfile')

    if (!existsSync(projectsDir)) {
      mkdirSync(projectsDir, { recursive: true })
    }
    if (!existsSync(logsDir)) mkdirSync(logsDir, { recursive: true })

    const configFile = join(caddyDir, 'config.json')
    const configData = existsSync(configFile)
      ? caddyMetadataSchema.parse(JSON.parse(readFileSync(configFile, 'utf8')))
      : {}
    delete configData['sitesDir']
    configData['projectsDir'] = projectsDir
    configData['caddyfile'] = caddyfile
    writeFileSync(configFile, JSON.stringify(configData, null, 2))

    return true
  }
}
