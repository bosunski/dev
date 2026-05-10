import type { Step } from '../types/step.js'
import type { ProjectDefinition } from '../config/project-definition.js'

export type ProjectEntry = {
  id: string
  dev: import('../dev.js').Dev
}

export class Repository {
  steps: Record<string, Step> = {}
  private projects: Map<string, ProjectEntry> = new Map()

  addProject(project: ProjectEntry): void {
    this.projects.set(project.id, project)
  }

  getProject(definition: ProjectDefinition): ProjectEntry | undefined {
    return this.projects.get(definition.repo)
  }

  getProjects(): ProjectEntry[] {
    return [...this.projects.values()]
  }
}
