import { existsSync, mkdirSync, writeFileSync, readFileSync } from 'node:fs'
import { join } from 'node:path'
import type { Runner } from '../../../execution/runner.js'
import { BaseStep } from '../../../step/base-step.js'

export class PrepareCaddyStep extends BaseStep {
  name(): string { return 'Prepare Caddy' }
  id(): string { return 'caddy.prepare' }

  async done(_runner: Runner): Promise<boolean> {
    return false
  }

  async run(runner: Runner): Promise<boolean> {
    const caddyDir = runner.config.globalPath('caddy')
    const sitesDir = runner.config.globalPath('caddy/sites')
    const caddyfile = join(caddyDir, 'Caddyfile')

    if (!existsSync(sitesDir)) {
      mkdirSync(sitesDir, { recursive: true })
    }

    const importLine = `import ${sitesDir}/*`
    const existingContent = existsSync(caddyfile) ? readFileSync(caddyfile, 'utf8') : ''
    if (existingContent.trim() !== importLine) {
      writeFileSync(caddyfile, importLine + '\n')
    }

    const configFile = join(caddyDir, 'config.json')
    const configData = existsSync(configFile)
      ? (JSON.parse(readFileSync(configFile, 'utf8')) as Record<string, unknown>)
      : {}
    configData['sitesDir'] = sitesDir
    configData['caddyfile'] = caddyfile
    writeFileSync(configFile, JSON.stringify(configData, null, 2))

    const caddyBin = runner.config.brewPath('bin/caddy')
    if (existsSync(caddyBin)) {
      const running = Bun.spawnSync(['sh', '-c', 'curl -sf http://localhost:2019/config/ > /dev/null 2>&1']).exitCode === 0
      if (!running) {
        if (!await runner.exec(`${caddyBin} start --config ${caddyfile}`)) return false
        await new Promise(resolve => setTimeout(resolve, 1000))
      } else {
        if (!await runner.exec([caddyBin, 'reload', '--config', caddyfile])) return false
      }
      // trust after caddy is running so it can reach the admin API
      await runner.exec(`${caddyBin} trust`)
    }

    return true
  }
}
