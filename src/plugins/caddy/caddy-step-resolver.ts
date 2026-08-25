import type { StepResolver, Step } from '../../types/step.js'
import type { Dev } from '../../dev.js'
import { CaddyStep } from './steps/caddy-step.js'
import { caddyProjectSitesDir } from './caddy-layout.js'
import { PhpRuntime } from '../php-runtime/php-runtime.js'
import { z } from 'zod'
import type { RawStep } from '../../types/config.js'
import { CaddyManager } from './caddy-manager.js'

const headersSchema = z.record(z.string(), z.string())
const caddyRouteSchema = z.object({
  path: z.string(),
  proxy: z.string().optional(),
  file_server: z.boolean().optional(),
  strip_prefix: z.string().optional(),
  response_headers: headersSchema.optional(),
  request_headers: headersSchema.optional(),
  flush_interval: z.string().optional(),
}).refine(route => route.proxy || route.file_server, { message: 'A Caddy route requires proxy or file_server.' })
const caddySiteSchema = z.union([z.string(), z.object({
  host: z.string(),
  proxy: z.string().optional(),
  root: z.string().optional(),
  php_fastcgi: z.string().optional(),
  runtime: z.string().optional(),
  secure: z.boolean().optional(),
  tls: z.enum(['automatic', 'internal']).optional(),
  max_request_body: z.string().optional(),
  response_headers: headersSchema.optional(),
  request_headers: headersSchema.optional(),
  flush_interval: z.string().optional(),
  routes: z.array(caddyRouteSchema).optional(),
}).refine(site => !(site.runtime && site.php_fastcgi), {
  message: 'A Caddy site cannot define both runtime and php_fastcgi.',
})])
export const rawCaddyConfigSchema = z.object({ sites: z.array(caddySiteSchema).optional() })
export type RawCaddyRoute = z.infer<typeof caddyRouteSchema>
export type RawCaddySite = z.infer<typeof caddySiteSchema>
export type RawCaddyConfig = z.infer<typeof rawCaddyConfigSchema>

export function hasCaddyConfig(step: RawStep): boolean {
  if (!('caddy' in step)) return false
  rawCaddyConfigSchema.parse(step['caddy'])
  return true
}

export class CaddyStepResolver implements StepResolver {
  constructor(private readonly dev: Dev) {}

  resolve(args: unknown): Step {
    const sitesDir = caddyProjectSitesDir(
      this.dev.config.globalPath('caddy'),
      this.dev.config.cwd(),
    )
    const config = rawCaddyConfigSchema.parse(args)
    const sites = (config.sites ?? []).map(site => {
      if (typeof site === 'string' || !site.runtime) return site
      return { ...site, php_fastcgi: PhpRuntime.endpoint(this.dev.config, site.runtime) }
    })
    return new CaddyStep(
      { ...config, sites },
      sitesDir,
      runner => new CaddyManager(runner.config).deploy(runner),
    )
  }
}
