import { describe, expect, test } from 'bun:test'
import { mkdtempSync, readFileSync, readdirSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import { CaddySiteStep } from '../src/plugins/caddy/steps/caddy-site-step.js'
import { Runner } from '../src/execution/runner.js'
import { Config } from '../src/config/config.js'
import { Repository } from '../src/execution/repository.js'
import type { IOInterface } from '../src/types/io.js'

const silentIO: IOInterface = {
  writeln: () => {}, write: () => {}, info: () => {}, error: () => {}, dev: () => {},
  stepStart: () => {}, stepEnd: () => {},
  text: async () => '', password: async () => '',
}

function configAt(dir: string): string {
  const files = readdirSync(dir).filter(file => file.endsWith('.conf'))
  expect(files).toHaveLength(1)
  return readFileSync(join(dir, files[0]!), 'utf8')
}

function runnerFor(cwd: string): Runner {
  return new Runner(new Config(cwd, {}), silentIO, new Repository())
}

describe('CaddySiteStep', () => {
  test('uses internal HTTPS by default and supports wildcard hosts', async () => {
    const dir = mkdtempSync(join(tmpdir(), 'dev-caddy-site-'))
    const step = new CaddySiteStep({ host: '*.example.test', proxy: 'http://127.0.0.1:9401' }, dir)
    expect(await step.run(runnerFor('/project'))).toBeTrue()
    const config = configAt(dir)
    expect(config).toContain('https://*.example.test')
    expect(config).toContain('tls internal')
  })

  test('allows automatic TLS as an explicit opt-in', async () => {
    const dir = mkdtempSync(join(tmpdir(), 'dev-caddy-site-'))
    const step = new CaddySiteStep({
      host: 'public.example.com',
      proxy: 'http://127.0.0.1:9401',
      tls: 'automatic',
    }, dir)
    expect(await step.run(runnerFor('/project'))).toBeTrue()
    expect(configAt(dir)).not.toContain('tls internal')
  })

  test('honors secure false', async () => {
    const dir = mkdtempSync(join(tmpdir(), 'dev-caddy-site-'))
    const step = new CaddySiteStep({ host: 'plain.example.test', secure: false }, dir)
    expect(await step.run(runnerFor('/project'))).toBeTrue()
    expect(configAt(dir))
      .toContain('http://plain.example.test')
  })

  test('renders Okra proxy behavior declaratively', async () => {
    const dir = mkdtempSync(join(tmpdir(), 'dev-caddy-site-'))
    const step = new CaddySiteStep({
      host: '*.ciroue.test',
      proxy: 'http://127.0.0.1:8787',
      max_request_body: '26MB',
      flush_interval: '-1',
      request_headers: { 'X-NginX-Proxy': 'true' },
      response_headers: {
        'Cross-Origin-Resource-Policy': 'cross-origin',
        'Cross-Origin-Embedder-Policy': 'credentialless',
        'Access-Control-Allow-Origin': '*',
      },
    }, dir)
    expect(await step.run(runnerFor('/project'))).toBeTrue()
    const config = configAt(dir)
    expect(config).toContain('max_size 26MB')
    expect(config).toContain('tls internal')
    expect(config).toContain('?Cross-Origin-Resource-Policy "cross-origin"')
    expect(config).toContain('header_up X-NginX-Proxy "true"')
    expect(config).toContain('flush_interval -1')
  })

  test('renders path routes, URI stripping, and a fallback proxy', async () => {
    const dir = mkdtempSync(join(tmpdir(), 'dev-caddy-site-'))
    const step = new CaddySiteStep({
      host: 'app.example.test',
      proxy: 'http://127.0.0.1:9500',
      routes: [
        {
          path: '/socket*',
          strip_prefix: '/socket',
          proxy: 'http://127.0.0.1:6001',
        },
        {
          path: '/build/assets*',
          proxy: 'http://127.0.0.1:9500',
          response_headers: { 'Cross-Origin-Opener-Policy': 'same-origin' },
        },
      ],
    }, dir)

    expect(await step.run(runnerFor('/project'))).toBeTrue()
    const config = configAt(dir)
    expect(config).toContain('handle /socket*')
    expect(config).toContain('uri strip_prefix /socket')
    expect(config).toContain('reverse_proxy http://127.0.0.1:6001')
    expect(config).toContain('handle /build/assets*')
    expect(config).toContain('?Cross-Origin-Opener-Policy "same-origin"')
    expect(config).toContain('handle {\n\t\treverse_proxy http://127.0.0.1:9500')
  })

  test('renders a project-relative PHP-FPM site', async () => {
    const dir = mkdtempSync(join(tmpdir(), 'dev-caddy-site-'))
    const step = new CaddySiteStep({
      host: 'php.example.test',
      root: 'public',
      php_fastcgi: '127.0.0.1:9074',
    }, dir)
    expect(await step.run(runnerFor('/project'))).toBeTrue()
    const config = configAt(dir)
    expect(config).toContain('root * "/project/public"')
    expect(config).toContain('php_fastcgi "127.0.0.1:9074"')
    expect(config).toContain('file_server')
  })

  test('quotes document roots containing spaces', async () => {
    const dir = mkdtempSync(join(tmpdir(), 'dev-caddy-site-'))
    const step = new CaddySiteStep({
      host: 'spaces.example.test',
      root: 'public files',
      php_fastcgi: '127.0.0.1:9074',
    }, dir)
    expect(await step.run(runnerFor('/project root'))).toBeTrue()
    expect(configAt(dir)).toContain('root * "/project root/public files"')
  })

  test('renders a static-file route without a proxy', async () => {
    const dir = mkdtempSync(join(tmpdir(), 'dev-caddy-site-'))
    const step = new CaddySiteStep({
      host: 'php.example.test',
      root: 'public',
      php_fastcgi: '127.0.0.1:9074',
      routes: [{
        path: '/build/assets*',
        file_server: true,
        response_headers: { 'Cross-Origin-Resource-Policy': 'cross-origin' },
      }],
    }, dir)
    expect(await step.run(runnerFor('/project'))).toBeTrue()
    const config = configAt(dir)
    expect(config).toContain('handle /build/assets*')
    expect(config).toContain('?Cross-Origin-Resource-Policy "cross-origin"')
    expect(config).toContain('\t\tfile_server')
  })
})
