import { Command, Args } from '@oclif/core'
import { writeFileSync, existsSync } from 'node:fs'
import { getDevContext } from '../context.js'
import { Config } from '../config/config.js'
import { UserException } from '../exceptions.js'

const INIT_YAML = `name: project

steps:
    - command: hello

commands:
    hello:
        description: Say Hello
        run: echo "Hello, DEV!"
`

export default class Init extends Command {
  static id = 'init'
  static description = 'Create a new dev.yml file in the project root'
  static args = {
    path: Args.string({ description: 'The path to the project root', required: false }),
  }

  async run(): Promise<void> {
    const { args } = await this.parse(Init)
    const { dev } = await getDevContext()

    const config = args.path ? Config.fromPath(args.path) : dev.config

    if (existsSync(config.file())) {
      throw new UserException('DEV is already initialized for this project. See the dev.yml file in the project root.')
    }

    try {
      writeFileSync(config.file(), INIT_YAML)
    } catch {
      throw new UserException('Could not create the dev.yml file.')
    }

    this.log(`Initialized DEV at ${config.file()}`)
  }
}
