import { existsSync, readdirSync } from 'node:fs'
import { join } from 'node:path'
import type { Runner } from '../../../execution/runner.js'
import { BaseStep } from '../../../step/base-step.js'
import { Config } from '../../../config/config.js'
import type { ProjectDefinition } from '../../../config/project-definition.js'

export class CdStep extends BaseStep {
  constructor(
    private readonly source: string,
    private readonly search: string,
  ) {
    super()
  }

  static fromDefinition(definition: ProjectDefinition): CdStep {
    return new CdStep(definition.source, definition.repo)
  }

  name(): string | null { return null }

  async run(runner: Runner): Promise<boolean> {
    const isSingleSegment = !this.search.includes('/')

    if (isSingleSegment) {
      const sourceDir = Config.sourcePath(undefined, this.source)
      if (!existsSync(sourceDir)) {
        runner.getIO().error(`Unable to find a project matching ${this.search}.`)
        return false
      }

      const match = this.findProject(sourceDir, this.search)
      if (match) return this.cd(runner, match)

      runner.getIO().error(`Unable to find a project matching ${this.search}.`)
      return false
    }

    const path = Config.sourcePath(this.search, this.source)
    if (!existsSync(path)) {
      runner.getIO().error('Directory does not exist.')
      return false
    }

    if (process.cwd() === path) return true
    return this.cd(runner, path)
  }

  private findProject(sourceDir: string, search: string): string | null {
    const needle = search.toLowerCase()
    const topEntries = readdirSync(sourceDir, { withFileTypes: true })

    // First pass: direct match at depth-1 (e.g. ~/src/github.com/okra)
    for (const entry of topEntries) {
      if (entry.isDirectory() && entry.name.toLowerCase().includes(needle)) {
        return join(sourceDir, entry.name)
      }
    }

    // Second pass: depth-2, inside org directories (e.g. ~/src/github.com/phpsandbox/okra)
    for (const org of topEntries) {
      if (!org.isDirectory()) continue
      const orgDir = join(sourceDir, org.name)
      try {
        const repoEntries = readdirSync(orgDir, { withFileTypes: true })
        for (const repo of repoEntries) {
          if (repo.isDirectory() && repo.name.toLowerCase().includes(needle)) {
            return join(orgDir, repo.name)
          }
        }
      } catch {
        // skip unreadable dirs
      }
    }

    return null
  }

  private async cd(runner: Runner, path: string): Promise<boolean> {
    const shell = runner.shell(null)
    if (!shell) {
      runner.getIO().error('Unable to determine the current shell.')
      return false
    }

    const proc = Bun.spawn([shell.bin], {
      cwd: path,
      env: { ...process.env as Record<string, string>, DEV_SHELL: '1' },
      stdin: 'inherit',
      stdout: 'inherit',
      stderr: 'inherit',
    })
    await proc.exited
    return true
  }

  async done(_runner: Runner): Promise<boolean> { return false }
  id(): string { return `cd-${this.search}` }
}
