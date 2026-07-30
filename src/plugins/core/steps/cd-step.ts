import { existsSync, readdirSync } from 'node:fs'
import type { Dirent } from 'node:fs'
import { join, relative } from 'node:path'
import * as clack from '@clack/prompts'
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

      const matches = this.findProjects(sourceDir, this.search)
      if (matches.length === 1) return this.cd(runner, matches[0]!)
      if (matches.length > 1) {
        const match = await this.selectProject(sourceDir, matches)
        if (!match) return false
        return this.cd(runner, match)
      }

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

  private findProjects(sourceDir: string, search: string): string[] {
    const needle = search.toLowerCase()
    const topEntries = this.sortedDirectories(sourceDir)
    const matches: string[] = []

    // First pass: direct match at depth-1 (e.g. ~/src/github.com/okra)
    for (const entry of topEntries) {
      if (entry.isDirectory() && entry.name.toLowerCase().includes(needle)) {
        matches.push(join(sourceDir, entry.name))
      }
    }

    // Second pass: depth-2, inside org directories (e.g. ~/src/github.com/phpsandbox/okra)
    for (const org of topEntries) {
      if (!org.isDirectory()) continue
      const orgDir = join(sourceDir, org.name)
      try {
        const repoEntries = this.sortedDirectories(orgDir)
        for (const repo of repoEntries) {
          if (repo.isDirectory() && repo.name.toLowerCase().includes(needle)) {
            matches.push(join(orgDir, repo.name))
          }
        }
      } catch {
        // skip unreadable dirs
      }
    }

    return [...new Set(matches)]
  }

  private sortedDirectories(path: string): Dirent<string>[] {
    return readdirSync(path, { withFileTypes: true })
      .filter(entry => entry.isDirectory())
      .sort((a, b) => a.name.localeCompare(b.name))
  }

  private async selectProject(sourceDir: string, matches: string[]): Promise<string | null> {
    const selected = await clack.select({
      message: `Multiple projects match "${this.search}". Which one do you want?`,
      options: matches.map(path => ({
        value: path,
        label: relative(sourceDir, path),
      })),
    })

    if (clack.isCancel(selected)) return null
    return selected as string
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
