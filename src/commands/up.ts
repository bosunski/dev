import { Command, Flags } from '@oclif/core'
import { getDevContext, createDevFor } from '../context.js'
import { Config } from '../config/config.js'
import { Dev } from '../dev.js'
import { Repository } from '../execution/repository.js'
import { CloneStep } from '../plugins/core/steps/clone-step.js'
import { CacheFilesStep } from '../plugins/core/steps/cache-files-step.js'
import { CheckUpdateStep } from '../plugins/core/steps/check-update-step.js'
import type { Step, DeferredStep } from '../types/step.js'
import { UserException } from '../exceptions.js'
import type { ProjectDefinition } from '../config/project-definition.js'

type ProjectEntry = { id: string; dev: Dev }

export default class Up extends Command {
  static id = 'up'
  static description = 'Bootstrap a project by running all configured steps'
  static flags = {
    self: Flags.boolean({ description: 'Skip dependency projects' }),
    force: Flags.boolean({ char: 'f', description: 'Force the execution of all steps' }),
  }

  async run(): Promise<void> {
    const { flags } = await this.parse(Up)
    const { dev } = await getDevContext()

    if (!dev.isInitialized()) {
      throw new UserException('DEV is not initialized for this project. Run `dev init` to initialize DEV.')
    }

    const repo = new Repository()

    if (!flags.self && dev.config.projects().length > 0) {
      this.log(`🚀 Project contains ${dev.config.projects().length} dependency projects. Resolving all dependency projects...`)
      for (const project of dev.config.projects()) {
        await this.resolveProject(project, dev, repo, dev.config.path())
      }
    }

    const rootEntry: ProjectEntry = { id: dev.config.projectName(), dev }
    repo.addProject(rootEntry)

    const force = flags.force
    const deferred: Array<{ entry: ProjectEntry; step: Step }> = []

    for (const entry of repo.getProjects()) {
      const projectConfig = Config.fromPath(entry.dev.config.cwd())
      const project = { id: entry.id, dev: entry.dev }

      // Get steps via plugin manager
      const steps = await this.getSteps(entry.dev)
      if (steps.length === 0) continue

      this.log(`🚀 Running steps for ${project.id}...`)

      for (const step of steps) {
        if (this.isDeferred(step)) {
          deferred.push({ entry, step })
          continue
        }

        if (!(await entry.dev.runner.execute(step, force))) {
          const failed = entry.dev.runner.lastFailedStep ?? step
          const name = failed.name() || failed.id()
          throw new UserException(`Failed to run step '${name}' in ${project.id}`)
        }
      }
    }

    deferred.push({ entry: rootEntry, step: new CacheFilesStep(dev) })
    deferred.push({ entry: rootEntry, step: new CheckUpdateStep() })

    if (deferred.length > 0) {
      this.log('⏳ Running deferred steps...')
    }

    for (const { entry, step } of deferred) {
      if (!(await entry.dev.runner.execute(step, force))) {
        const name = step.name()
        throw new UserException(name ? `Failed to run deferred step '${name}'` : 'Failed to run deferred step')
      }
    }

    dev.config.writeSettings()
  }

  private async getSteps(dev: Dev): Promise<Step[]> {
    const { CONFIG_PROVIDER } = await import('../types/capability.js')
    type CapType = import('../types/capability.js').ConfigProvider

    const pm = dev.getPluginManager()
    const resolvers: Record<string, import('../types/step.js').StepResolver> = {}
    let steps: Step[] = []

    for (const cap of pm.getPluginCapabilities<CapType>(CONFIG_PROVIDER, { dev })) {
      Object.assign(resolvers, cap.stepResolvers())
      steps = [...steps, ...cap.steps()]
    }

    return [...steps, ...dev.config.up().steps(resolvers)]
  }

  private isDeferred(step: Step): step is DeferredStep {
    return 'deferred' in step && step.deferred === true
  }

  private async resolveProject(
    projectDef: ProjectDefinition,
    parentDev: Dev,
    repo: Repository,
    root: string,
  ): Promise<ProjectEntry> {
    // Already resolved?
    const existing = repo.getProject(projectDef)
    if (existing) return existing

    // Clone it
    const cloneStep = new CloneStep(projectDef, ['--depth=1'], root, true)
    if (!(await parentDev.runner.execute([cloneStep]))) {
      throw new UserException(`Failed to clone ${projectDef}`)
    }

    const depConfig = Config.fromProjectName(projectDef, root)
    for (const subProject of depConfig.projects()) {
      await this.resolveProject(subProject, parentDev, repo, root)
    }

    const depDev = createDevFor(depConfig)
    const entry: ProjectEntry = { id: depConfig.projectName(), dev: depDev }

    if (depDev.isInitialized()) {
      repo.addProject(entry)
    }

    return entry
  }
}
