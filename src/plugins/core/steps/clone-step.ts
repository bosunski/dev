import { existsSync, lstatSync, mkdirSync, rmSync } from 'node:fs'
import { dirname, relative, resolve } from 'node:path'
import type { Runner } from '../../../execution/runner.js'
import { BaseStep } from '../../../step/base-step.js'
import type { ProjectDefinition } from '../../../config/project-definition.js'
import { Config } from '../../../config/config.js'

export function isRepairableManagedCheckout(root: string, path: string): boolean {
  if (lstatSync(path).isSymbolicLink()) return false
  const managedRoot = resolve(root, Config.SrcDir)
  const candidate = resolve(path)
  const child = relative(managedRoot, candidate)
  return child !== '' && child !== '..' && !child.startsWith(`..${process.platform === 'win32' ? '\\' : '/'}`)
}

export class CloneStep extends BaseStep {
  constructor(
    private readonly project: ProjectDefinition,
    private readonly args: string | string[] = [],
    private readonly root: string | null = null,
    private readonly update = false,
  ) {
    super()
  }

  id(): string { return `git-clone-${this.project.repo}` }
  name(): string | null { return null }

  async run(runner: Runner): Promise<boolean> {
    const clonePath = this.clonePath(runner.config)

    if (existsSync(clonePath)) {
      if (this.isGitRepository(clonePath)) {
        runner.getIO().writeln(`Repository already exists at ${clonePath}`)
        return !this.update || this.pullChanges(runner, clonePath)
      }
      if (!this.canRepair(clonePath)) {
        runner.getIO().error(`Refusing to replace an invalid repository outside DEV's managed dependency directory: ${clonePath}`)
        return false
      }
      runner.getIO().writeln(`Repairing incomplete repository at ${clonePath}`)
      rmSync(clonePath, { recursive: true })
    }

    mkdirSync(dirname(clonePath), { recursive: true })

    const extraArgs = Array.isArray(this.args) ? this.args : this.args ? [this.args] : []
    if (this.project.ref) extraArgs.push('--branch', this.project.ref)

    const cmd = ['git', 'clone', ...extraArgs, this.project.url, clonePath]
    const ok = await runner.withoutShadowEnv().withoutEnv().exec(cmd)
    if (!ok) rmSync(clonePath, { recursive: true, force: true })
    return ok
  }

  async pullChanges(runner: Runner, clonePath: string): Promise<boolean> {
    return runner.withoutShadowEnv().withoutEnv().exec(
      'git reset --hard HEAD && git pull',
      clonePath,
      { GIT_DIR: `${clonePath}/.git`, GIT_WORK_TREE: clonePath },
    )
  }

  async done(_runner: Runner): Promise<boolean> { return false }

  private clonePath(config: Config): string {
    return Config.sourcePath(this.project.repo, this.project.source, this.root ?? undefined)
  }

  private isGitRepository(path: string): boolean {
    const result = Bun.spawnSync(['git', '-C', path, 'rev-parse', '--show-toplevel'], {
      stdout: 'pipe', stderr: 'ignore',
    })
    return result.exitCode === 0
      && resolve(result.stdout.toString().trim()) === resolve(path)
  }

  private canRepair(path: string): boolean {
    return this.root !== null && isRepairableManagedCheckout(this.root, path)
  }
}
