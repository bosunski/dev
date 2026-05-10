import { Command, Args } from '@oclif/core'
import * as clack from '@clack/prompts'
import { getDevContext } from '../../context.js'
import { UserException } from '../../exceptions.js'

export default class ProjectDisable extends Command {
  static description = 'Disable a dependency project'
  static id = 'project:disable'
  static args = {
    project: Args.string({ description: 'Project name', required: false }),
  }

  async run(): Promise<void> {
    const { args } = await this.parse(ProjectDisable)
    const { dev } = await getDevContext()

    const projects = dev.config.projects(true)
    if (projects.length === 0) {
      this.error('No registered projects found')
      this.exit(2)
    }

    let project = args.project
    if (!project) {
      const selected = await clack.select({
        message: 'Which project do you want to disable?',
        options: projects.map(p => ({ value: p.repo, label: p.repo })),
      })
      if (clack.isCancel(selected)) process.exit(0)
      project = selected as string
    }

    const found = projects.find(p => p.repo === project)
    if (!found) {
      this.error(`Project ${project} not found in configuration`)
      this.exit(2)
    }

    const disabled = dev.config.settings.disabled ?? []
    if (disabled.includes(project)) {
      this.log(`Project ${project} is already disabled`)
      return
    }

    dev.config.settings.disabled.push(project)
    dev.config.writeSettings()
    this.log(`Project ${project} disabled`)
  }
}
