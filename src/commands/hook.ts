import { Command } from '@oclif/core'
import { existsSync, readFileSync, writeFileSync } from 'node:fs'
import { createHash } from 'node:crypto'
import { getDevContext } from '../context.js'
import type { Config } from '../config/config.js'

export default class Hook extends Command {
  static id = 'hook'
  static description = 'Internal hook command (run by shell integration)'
  static hidden = true

  async run(): Promise<void> {
    await this.parse(Hook)
    const { dev } = await getDevContext()

    if (!dev.isInitialized()) return
    if (!this.shouldShowMessage(dev.config)) return

    const message = this.trackedFilesHaveChanged(dev.config)
    if (message) {
      const prefix = ' DEV '
      const styledPrefix = `\x1b[30;107m${prefix}\x1b[0m`
      process.stderr.write(`\n${styledPrefix} \x1b[90m${message}\x1b[0m\n`)
    }

    this.updateLastMessageAt(dev.config)
  }

  private shouldShowMessage(config: Config): boolean {
    const lastHookFile = config.path('.last-hook')
    if (!existsSync(lastHookFile)) return true
    try {
      const lastAt = new Date(readFileSync(lastHookFile, 'utf8').trim()).getTime()
      return Date.now() - lastAt > 5 * 60 * 1000
    } catch {
      return true
    }
  }

  private trackedFilesHaveChanged(config: Config): string | false {
    const locks = config.settings.locks ?? {}
    for (const [name, md5] of Object.entries(locks)) {
      const path = config.cwd(name)
      if (!existsSync(path)) continue
      const current = createHash('md5').update(readFileSync(path)).digest('hex')
      if (current !== md5) {
        return `The file \x1b[33m${name}\x1b[90m has changed, you should run dev up!`
      }
    }
    return false
  }

  private updateLastMessageAt(config: Config): void {
    writeFileSync(config.path('.last-hook'), new Date().toISOString())
  }
}
