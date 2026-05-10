import { Command, Args } from '@oclif/core'
import * as clack from '@clack/prompts'
import { getDevContext } from '../../context.js'

export default class ProjectEnable extends Command {
  static description = 'Enable a disabled dependency project'
  static id = 'project:enable'
  static args = {
    project: Args.string({ description: 'Project name', required: false }),
  }

  async run(): Promise<void> {
    const { args } = await this.parse(ProjectEnable)
    const { dev } = await getDevContext()

    const projects = dev.config.projects(true)
    if (projects.length === 0) {
      this.error('No registered dependency projects found')
      this.exit(2)
    }

    let project = args.project
    if (!project) {
      const selected = await clack.select({
        message: 'Which project do you want to enable?',
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
    if (!disabled.includes(project)) {
      this.log(`Project ${project} is not disabled`)
      return
    }

    dev.config.settings.disabled = disabled.filter(d => d !== project)
    dev.config.writeSettings()
    this.log(`Project ${project} enabled`)
  }
}
