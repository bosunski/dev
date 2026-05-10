import { existsSync, readFileSync, writeFileSync, mkdirSync } from 'node:fs'
import { join } from 'node:path'
import { createHash } from 'node:crypto'
import type { Runner } from '../../../execution/runner.js'
import { BaseStep } from '../../../step/base-step.js'
import type { ValetPlugin } from '../valet-plugin.js'
import type { Dev } from '../../../dev.js'

export class PostUpStep extends BaseStep {
  constructor(
    private readonly sites: string[],
    private readonly plugin: ValetPlugin,
    private readonly dev: Dev,
  ) {
    super()
  }

  name(): string | null { return null }

  id(): string {
    return `valet.post-up.${createHash('md5').update(this.sites.join(',')).digest('hex')}`
  }

  async done(_runner: Runner): Promise<boolean> {
    if (this.sites.length === 0) return true
    const valetDir = this.plugin.localConfig?.get('dir') ?? ''
    const storePath = this.dev.config.globalPath('valet/sites')
    return this.sites.every(host => {
      const storedPath = join(storePath, `${host}.md5`)
      if (!existsSync(storedPath)) return false
      const nginxConf = join(valetDir, 'Nginx', `${host}.conf`)
      if (!existsSync(nginxConf)) return false
      const current = createHash('md5').update(readFileSync(nginxConf)).digest('hex')
      return readFileSync(storedPath, 'utf8').trim() === current
    })
  }

  async run(_runner: Runner): Promise<boolean> {
    const valetDir = this.plugin.localConfig?.get('dir') ?? ''
    const storePath = this.dev.config.globalPath('valet/sites')
    if (!existsSync(storePath)) mkdirSync(storePath, { recursive: true })

    for (const host of this.sites) {
      const nginxConf = join(valetDir, 'Nginx', `${host}.conf`)
      if (!existsSync(nginxConf)) continue
      const md5 = createHash('md5').update(readFileSync(nginxConf)).digest('hex')
      writeFileSync(join(storePath, `${host}.md5`), md5)
    }

    return true
  }
}
