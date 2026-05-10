import { Command, Args, Flags } from '@oclif/core'
import { getDevContext } from '../context.js'
import { CdStep } from '../plugins/core/steps/cd-step.js'

const KNOWN_SOURCES: Record<string, string> = {
  github: 'github.com',
  gitlab: 'gitlab.com',
  bitbucket: 'bitbucket.org',
}

export default class Cd extends Command {
  static id = 'cd'
  static description = 'Change directory to a project repo'
  static args = {
    repo: Args.string({ description: 'Repository name or partial name', required: true }),
  }
  static flags = {
    source: Flags.string({ description: 'Source host alias (github, gitlab, bitbucket)' }),
  }

  async run(): Promise<void> {
    const { args, flags } = await this.parse(Cd)
    const { dev } = await getDevContext()

    if (flags.source && !(flags.source in KNOWN_SOURCES)) {
      this.error(`Unknown source ${flags.source}, please use one of: ${Object.keys(KNOWN_SOURCES).join(', ')}`)
    }

    const source = (flags.source ? KNOWN_SOURCES[flags.source] : null) ?? 'github.com'
    const ok = await dev.runner.execute([new CdStep(source, args.repo)])
    if (!ok) this.exit(1)
  }
}
