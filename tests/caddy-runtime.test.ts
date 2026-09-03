import { describe, expect, test } from 'bun:test'
import { CaddyRuntime } from '../src/plugins/caddy/caddy-runtime.js'

describe('CaddyRuntime', () => {
  test('runs Caddy as the user on Linux after a one-time bootstrap', () => {
    const runtime = new CaddyRuntime('linux', '/usr/bin/caddy', 'sudo')
    expect(runtime.reloadCommand('/home/user/.dev/caddy/Caddyfile'))
      .toEqual([
        '/usr/bin/caddy', 'reload', '--config', '/home/user/.dev/caddy/Caddyfile',
        '--address', '127.0.0.1:2019', '--force',
      ])
    expect(runtime.linuxBootstrapCommands()).toEqual([
      ['sudo', 'systemctl', 'disable', '--now', 'caddy'],
      ['sudo', 'setcap', 'cap_net_bind_service=+ep', '/usr/bin/caddy'],
    ])
    expect(runtime.caCertificateSource('/home/user', '/home/user/.local/share'))
      .toBe('/home/user/.local/share/caddy/pki/authorities/local/root.crt')
    expect(runtime.linuxUserStartCommands()).toEqual([
      ['systemctl', '--user', 'daemon-reload'],
      ['systemctl', '--user', 'enable', 'dev-caddy.service'],
      ['systemctl', '--user', 'restart', 'dev-caddy.service'],
    ])
  })

  test('uses high ports behind a macOS loopback redirect', () => {
    const runtime = new CaddyRuntime('darwin', '/opt/homebrew/bin/caddy', 'sudo')
    expect(runtime.httpPort()).toBe(8080)
    expect(runtime.httpsPort()).toBe(8443)
    expect(runtime.darwinBootstrapCommands('/Users/dev/.dev/caddy/pf.conf')).toEqual([
      ['sudo', 'pfctl', '-a', 'com.apple/dev.caddy', '-f', '/Users/dev/.dev/caddy/pf.conf'],
      ['sudo', 'pfctl', '-E'],
    ])
    expect(runtime.trustCommand()).toEqual([
      '/opt/homebrew/bin/caddy', 'trust', '--address', '127.0.0.1:2019',
    ])
  })

  test('identifies the current macOS boot session for ephemeral PF rules', () => {
    const runtime = new CaddyRuntime('linux', '/usr/bin/caddy', 'sudo')
    expect(runtime.darwinBootSession()).toBe('')
  })

  test('supports an explicit privilege-free port configuration', () => {
    const runtime = new CaddyRuntime('linux', '/usr/bin/caddy', 'sudo', {
      DEV_CADDY_HTTP_PORT: '18080',
      DEV_CADDY_HTTPS_PORT: '18443',
      DEV_CADDY_ADMIN_PORT: '12019',
    })
    expect(runtime.httpPort()).toBe(18080)
    expect(runtime.httpsPort()).toBe(18443)
    expect(runtime.adminPort()).toBe(12019)
    expect(runtime.trustCommand()).toEqual([
      '/usr/bin/caddy', 'trust', '--address', '127.0.0.1:12019',
    ])
    expect(runtime.usesPrivilegedPorts()).toBeFalse()
    expect(runtime.hasLowPortAccess()).toBeTrue()
  })

  test('allows trust installation to be disabled for headless environments', () => {
    const runtime = new CaddyRuntime('darwin', '/opt/homebrew/bin/caddy', 'sudo', {
      DEV_CADDY_SKIP_TRUST: '1',
    })
    expect(runtime.shouldTrust()).toBeFalse()
    expect(new CaddyRuntime('darwin', '/opt/homebrew/bin/caddy', 'sudo').shouldTrust()).toBeTrue()
  })

  test('allows hosted macOS environments to use high ports without PF redirects', () => {
    const runtime = new CaddyRuntime('darwin', '/opt/homebrew/bin/caddy', 'sudo', {
      DEV_CADDY_DISABLE_PORT_REDIRECT: '1',
    })
    expect(runtime.usesPortRedirect()).toBeFalse()
    expect(new CaddyRuntime('darwin', '/opt/homebrew/bin/caddy', 'sudo').usesPortRedirect()).toBeTrue()
  })
})
