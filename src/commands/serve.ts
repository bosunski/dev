import { Command, Args, Flags } from '@oclif/core'
import * as clack from '@clack/prompts'
import { z } from 'zod'
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
      if (!await manager.run(flags.all ? undefined : z.array(z.string()).parse(argv))) this.exit(1)
      return
    }

    const availableGroups = manager.getGroups(dev)
    if (availableGroups.length === 0) {
      if (!await manager.run()) this.exit(1)
      return
    }

    const selected = await clack.multiselect({
      message: 'Which groups do you want to serve?',
      options: availableGroups.map(g => ({ value: g, label: g })),
      required: false,
    })

    if (clack.isCancel(selected)) process.exit(0)

    const selectedGroups = z.array(z.string()).parse(selected)
    const groups = selectedGroups.length > 0 ? selectedGroups : undefined
    if (!await manager.run(groups)) this.exit(1)
  }
}
