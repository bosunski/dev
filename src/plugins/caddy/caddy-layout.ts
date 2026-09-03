import { createHash } from 'node:crypto'
import { basename, join } from 'node:path'

export function caddyProjectId(cwd: string): string {
  const label = basename(cwd).replace(/[^a-zA-Z0-9._-]+/g, '-').replace(/^-+|-+$/g, '') || 'project'
  const hash = createHash('sha256').update(cwd).digest('hex').slice(0, 12)
  return `${label}-${hash}`
}

export function caddyProjectSitesDir(globalCaddyDir: string, cwd: string): string {
  return join(globalCaddyDir, 'projects', caddyProjectId(cwd), 'sites')
}

export function caddySiteFilename(host: string): string {
  const label = host.replace(/[^a-zA-Z0-9._*-]+/g, '-').replace(/^-+|-+$/g, '') || 'site'
  const hash = createHash('sha256').update(host).digest('hex').slice(0, 12)
  return `${label}-${hash}.conf`
}
