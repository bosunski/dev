import { UserException } from '../exceptions.js'

export class ProjectDefinition {
  readonly repo: string
  readonly ref: string | null
  readonly url: string
  readonly source: string

  constructor(public readonly project: string, public readonly host = 'github.com') {
    const [ref, fullName, cloneUrl, source] = this.parse(project)
    this.ref = ref
    this.repo = fullName
    this.url = cloneUrl
    this.source = source
  }

  toString(): string {
    return this.repo
  }

  private parse(projectUrl: string): [string | null, string, string, string] {
    if (!projectUrl) throw new UserException('Cannot provide an empty project name')

    const cleaned = projectUrl.replace(/\.git$/, '')

    // Bare owner/repo shorthand (e.g. "phpsandbox/okra" or "phpsandbox/okra#main")
    if (!cleaned.includes('://')) {
      const noQuery = cleaned.split('?')[0] ?? cleaned
      const hashIdx = noQuery.indexOf('#')
      const repoPath = hashIdx >= 0 ? noQuery.slice(0, hashIdx) : noQuery
      const ref = hashIdx >= 0 ? noQuery.slice(hashIdx + 1) : null
      const parts = repoPath.split('/')
      if (parts.length === 2 && parts[0] && parts[1]) {
        const fullName = `${parts[0]}/${parts[1]}`
        const cloneUrl = `https://${this.host}/${fullName}.git`
        return [ref, fullName, cloneUrl, this.host]
      }
    }

    let parsed: URL | null = null
    try {
      parsed = new URL(cleaned)
    } catch {
      throw new UserException(`Malformed project repo URL ${projectUrl} cannot be parsed`)
    }

    const path = parsed.pathname
    const parts = path.replace(/^\//, '').split('/')
    if (parts.length !== 2) {
      throw new UserException(`Malformed project repo URL ${projectUrl} cannot be parsed`)
    }

    const fullName = parts.join('/')
    const ref = parsed.hash ? parsed.hash.replace('#', '') : null
    const host = parsed.hostname
    const scheme = parsed.protocol.replace(':', '')
    const cloneUrl = `${scheme}://${host}/${fullName}.git`

    return [ref, fullName, cloneUrl, host]
  }
}
