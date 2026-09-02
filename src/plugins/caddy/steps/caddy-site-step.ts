import { existsSync, readFileSync, writeFileSync } from 'node:fs'
import { isAbsolute, join, resolve } from 'node:path'
import { createHash } from 'node:crypto'
import type { Runner } from '../../../execution/runner.js'
import { BaseStep } from '../../../step/base-step.js'
import type { RawCaddyRoute, RawCaddySite } from '../caddy-step-resolver.js'
import { caddySiteFilename } from '../caddy-layout.js'

export class CaddySiteStep extends BaseStep {
  readonly host: string
  readonly proxy: string | null
  readonly root: string | null
  readonly phpFastcgi: string | null
  readonly secure: boolean
  readonly tls: 'automatic' | 'internal'
  readonly maxRequestBody: string | null
  readonly responseHeaders: Record<string, string>
  readonly requestHeaders: Record<string, string>
  readonly flushInterval: string | null
  readonly routes: RawCaddyRoute[]

  constructor(site: RawCaddySite, private readonly sitesDir: string) {
    super()
    if (typeof site === 'string') {
      this.host = site
      this.proxy = null
      this.root = null
      this.phpFastcgi = null
      this.secure = true
      this.tls = 'internal'
      this.maxRequestBody = null
      this.responseHeaders = {}
      this.requestHeaders = {}
      this.flushInterval = null
      this.routes = []
    } else {
      this.host = site.host
      this.proxy = site.proxy ?? null
      this.root = site.root ?? null
      this.phpFastcgi = site.php_fastcgi ?? null
      this.secure = site.secure ?? true
      this.tls = site.tls ?? 'internal'
      this.maxRequestBody = site.max_request_body ?? null
      this.responseHeaders = site.response_headers ?? {}
      this.requestHeaders = site.request_headers ?? {}
      this.flushInterval = site.flush_interval ?? null
      this.routes = site.routes ?? []
    }
  }

  name(): string {
    return this.phpFastcgi
      ? `Caddy: serve PHP ${this.host} via ${this.phpFastcgi}`
      : this.proxy
      ? `Caddy: proxy ${this.host} → ${this.proxy}`
      : `Caddy: serve ${this.host}`
  }

  id(): string {
    const config = {
      host: this.host,
      proxy: this.proxy,
      root: this.root,
      phpFastcgi: this.phpFastcgi,
      secure: this.secure,
      tls: this.tls,
      maxRequestBody: this.maxRequestBody,
      responseHeaders: this.responseHeaders,
      requestHeaders: this.requestHeaders,
      flushInterval: this.flushInterval,
      routes: this.routes,
    }
    return `caddy-site-${createHash('md5').update(JSON.stringify(config)).digest('hex')}`
  }

  private confPath(): string {
    return join(this.sitesDir, caddySiteFilename(this.host))
  }

  private buildConf(runner: Runner): string {
    const scheme = this.secure ? 'https' : 'http'
    const addr = `${scheme}://${this.host}`
    const directives: string[] = []

    if (this.secure && this.tls === 'internal') {
      directives.push('\ttls internal')
    }

    if (this.maxRequestBody) {
      directives.push(`\trequest_body {\n\t\tmax_size ${this.maxRequestBody}\n\t}`)
    }

    const responseHeaders = Object.entries(this.responseHeaders)
    if (responseHeaders.length > 0) {
      directives.push(this.buildHeaders(this.responseHeaders, '\t'))
    }

    for (const route of this.routes) {
      const routeDirectives: string[] = []
      if (route.strip_prefix) routeDirectives.push(`\t\turi strip_prefix ${route.strip_prefix}`)
      if (Object.keys(route.response_headers ?? {}).length > 0) {
        routeDirectives.push(this.buildHeaders(route.response_headers ?? {}, '\t\t'))
      }
      if (route.proxy) {
        routeDirectives.push(this.buildProxy(
          route.proxy,
          route.request_headers ?? {},
          route.flush_interval ?? null,
          '\t\t',
        ))
      }
      if (route.file_server) routeDirectives.push('\t\tfile_server')
      directives.push(`\thandle ${route.path} {\n${routeDirectives.join('\n')}\n\t}`)
    }

    if (this.phpFastcgi) {
      const root = this.root
        ? (isAbsolute(this.root) ? this.root : resolve(runner.config.cwd(), this.root))
        : runner.config.cwd()
      directives.push(`\troot * ${this.quote(root)}`, `\tphp_fastcgi ${this.quote(this.phpFastcgi)}`, '\tfile_server')
      return `${addr} {\n${directives.join('\n')}\n}\n`
    }

    if (this.proxy) {
      const proxy = this.buildProxy(this.proxy, this.requestHeaders, this.flushInterval, '\t\t')
      directives.push(this.routes.length > 0
        ? `\thandle {\n${proxy}\n\t}`
        : proxy.replace(/^\t\t/gm, '\t'))
      return `${addr} {\n${directives.join('\n')}\n}\n`
    }

    const root = this.root
      ? (isAbsolute(this.root) ? this.root : resolve(runner.config.cwd(), this.root))
      : runner.config.cwd()
    directives.push(`\troot * ${this.quote(root)}`, '\tfile_server')
    return `${addr} {\n${directives.join('\n')}\n}\n`
  }

  private buildHeaders(headers: Record<string, string>, indent: string): string {
    const values = Object.entries(headers)
      .map(([name, value]) => `${indent}\t?${name} "${value}"`)
      .join('\n')
    return `${indent}header {\n${values}\n${indent}}`
  }

  private quote(value: string): string {
    return `"${value.replace(/\\/g, '\\\\').replace(/"/g, '\\"')}"`
  }

  private buildProxy(
    proxy: string,
    requestHeaders: Record<string, string>,
    flushInterval: string | null,
    indent: string,
  ): string {
    const options = [
      ...Object.entries(requestHeaders).map(([name, value]) => `${indent}\theader_up ${name} "${value}"`),
      ...(flushInterval ? [`${indent}\tflush_interval ${flushInterval}`] : []),
    ]
    return options.length > 0
      ? `${indent}reverse_proxy ${proxy} {\n${options.join('\n')}\n${indent}}`
      : `${indent}reverse_proxy ${proxy}`
  }

  async done(runner: Runner): Promise<boolean> {
    const confPath = this.confPath()
    if (!existsSync(confPath)) return false
    return readFileSync(confPath, 'utf8') === this.buildConf(runner)
  }

  async run(runner: Runner): Promise<boolean> {
    writeFileSync(this.confPath(), this.buildConf(runner))
    return true
  }
}
