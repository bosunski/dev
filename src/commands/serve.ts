import { Command, Args, Flags } from '@oclif/core'
import * as clack from '@clack/prompts'
import { ServeManager } from '../process/serve-manager.js'
import { getDevContext } from '../context.js'

export default class Serve extends Command {
  static id = 'serve'
  static description = 'Start the application services'
  static aliases = ['s']
  static strict = false
  static args = {
    groups: Args.string({ description: 'Only run serves in these groups', required: false }),
  }

  static flags = {
    all: Flags.boolean({ description: 'Run all serves without prompting', default: false }),
  }

  async run(): Promise<void> {
    const { argv, flags } = await this.parse(Serve)
    const { dev } = await getDevContext()
    const manager = new ServeManager(dev)

    if (argv.length > 0 || flags.all) {
      await manager.run(flags.all ? undefined : (argv as string[]))
      return
    }

    const availableGroups = manager.getGroups(dev)
    if (availableGroups.length === 0) {
      await manager.run()
      return
    }

    const selected = await clack.multiselect({
      message: 'Which groups do you want to serve?',
      options: availableGroups.map(g => ({ value: g, label: g })),
      required: false,
    })

    if (clack.isCancel(selected)) process.exit(0)

    const groups = (selected as string[]).length > 0 ? (selected as string[]) : undefined
    await manager.run(groups)
  }
}
