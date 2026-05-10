import { Command, Args } from '@oclif/core'
import { getDevContext } from '../context.js'
import { ProjectDefinition } from '../config/project-definition.js'
import { CloneStep } from '../plugins/core/steps/clone-step.js'
import { CdStep } from '../plugins/core/steps/cd-step.js'
import { UserException } from '../exceptions.js'

export default class Clone extends Command {
  static id = 'clone'
  static description = 'Clone a GitHub repository'
  static strict = false
  static args = {
    repo: Args.string({ description: 'Repository name (owner/repo) or URL', required: true }),
  }

  async run(): Promise<void> {
    const { dev } = await getDevContext()
    const { argv } = await this.parse(Clone)

    const fullName = argv[0] as string | undefined
    if (!fullName) {
      throw new UserException('Repository full name or URL must be provided')
    }

    const extraArgs = argv.slice(1) as string[]
    const definition = new ProjectDefinition(fullName)

    const ok = await dev.runner.execute([
      new CloneStep(definition, extraArgs),
      CdStep.fromDefinition(definition),
    ])

    if (!ok) this.exit(1)
  }
}
