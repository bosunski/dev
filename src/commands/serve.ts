import { Command, Flags } from '@oclif/core'
import { ServeManager } from '../process/serve-manager.js'
import { getDevContext } from '../context.js'

export default class Serve extends Command {
  static id = 'serve'
  static description = 'Start the application services'
  static aliases = ['s']
  static flags = {}

  async run(): Promise<void> {
    await this.parse(Serve)
    const { dev } = await getDevContext()
    const manager = new ServeManager(dev)
    await manager.run()
  }
}
