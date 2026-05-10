import { Command, Flags } from '@oclif/core'
import { existsSync, readFileSync } from 'node:fs'
import { getDevContext } from '../context.js'
import { Config } from '../config/config.js'
import { UserException } from '../exceptions.js'

export default class Kill extends Command {
  static id = 'kill'
  static description = 'Kill the running application services'
  static flags = {
    project: Flags.string({ description: 'Kill services for a specific project' }),
  }

  async run(): Promise<void> {
    const { flags } = await this.parse(Kill)
    const { dev } = await getDevContext()

    let config = dev.config
    if (flags.project) {
      const projects = dev.config.projects()
      const found = projects.find(p => p.repo === flags.project)
      if (!found) throw new UserException(`Project ${flags.project} not found`)
      config = Config.fromProjectName(flags.project)
    }

    const pidFile = config.path('dev')
    if (!existsSync(pidFile)) {
      this.error('No running services found')
      this.exit(1)
    }

    const pidStr = readFileSync(pidFile, 'utf8').trim()
    const pid = parseInt(pidStr, 10)
    if (isNaN(pid)) {
      this.error('Failed to read services PID')
      this.exit(1)
    }

    try {
      process.kill(pid, 'SIGTERM')
      this.log('Killed services')
    } catch {
      this.error('Failed to kill services')
      this.exit(1)
    }
  }
}
