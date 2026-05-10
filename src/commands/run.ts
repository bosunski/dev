import { Command, Args, Flags } from '@oclif/core'
import * as clack from '@clack/prompts'
import { getDevContext } from '../context.js'
import { UserException } from '../exceptions.js'

export default class Run extends Command {
  static id = 'run'
  static description = 'Run a custom command defined in dev.yml'
  static args = {
    name: Args.string({ description: 'Command name', required: false }),
  }
  static flags = {
    project: Flags.string({ description: 'Run command in a dependency project' }),
  }

  async run(): Promise<void> {
    const { args, flags } = await this.parse(Run)
    let { dev } = await getDevContext()

    if (flags.project) {
      const { createDevFor } = await import('../context.js')
      const { Config } = await import('../config/config.js')
      const projects = dev.config.projects()
      const found = projects.find(p => p.repo === flags.project)
      if (!found) throw new UserException(`Project ${flags.project} not found`)
      const depConfig = Config.fromProjectName(flags.project, dev.config.path())
      dev = createDevFor(depConfig)
    }

    const commands = dev.config.commands()
    const names = Object.keys(commands)

    if (names.length === 0) {
      throw new UserException('No commands found')
    }

    let name = args.name
    if (!name) {
      const options = Object.fromEntries(names.map(n => [n, commands[n]?.desc ?? n]))
      const selected = await clack.select({
        message: 'Which command do you want to run?',
        options: Object.entries(options).map(([value, label]) => ({ value, label })),
      })
      if (clack.isCancel(selected)) process.exit(0)
      name = selected as string
    }

    const command = commands[name]
    if (!command) {
      throw new UserException(`Command ${name} not found. Are you sure you have it configured?`)
    }

    const proc = await dev.runner.spawn(command.run, dev.config.cwd())
    const code = await proc.exited
    if (code !== 0) this.exit(code)
  }
}
