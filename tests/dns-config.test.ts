import { describe, expect, test } from 'bun:test'
import { DnsConfig } from '../src/plugins/core/config/dns-config.js'

describe('DnsConfig', () => {
  test('derives Docker default bridge interface name', () => {
    const config = new DnsConfig({
      server: '172.29.0.53',
      domains: ['ciroue.test', 'phpsandbox.test'],
      docker_network: 'okra-dev',
    })
    expect(config.interface(() => ({
      id: '1234567890abcdef',
      bridge: '',
    }))).toBe('br-1234567890ab')
  })

  test('uses an explicitly named Docker bridge', () => {
    const config = new DnsConfig({
      server: '172.29.0.53',
      domains: ['ciroue.test'],
      docker_network: 'okra-dev',
    })
    expect(config.interface(() => ({ id: 'ignored', bridge: 'okra0' }))).toBe('okra0')
  })

  test('treats Docker no-value bridge output as the default bridge', () => {
    const config = new DnsConfig({
      server: '172.29.0.53',
      domains: ['ciroue.test'],
      docker_network: 'okra-dev',
    })
    expect(config.interface(() => ({ id: 'abcdef1234567890', bridge: '' })))
      .toBe('br-abcdef123456')
  })

  test('builds split-DNS commands for systemd-resolved', () => {
    const config = new DnsConfig({
      server: '172.29.0.53',
      domains: ['ciroue.test', 'phpsandbox.test'],
      interface: 'okra0',
    })
    expect(config.configureCommands('okra0', 'sudo', 'linux')).toEqual([
      ['sudo', 'resolvectl', 'dns', 'okra0', '172.29.0.53'],
      ['sudo', 'resolvectl', 'domain', 'okra0', '~ciroue.test', '~phpsandbox.test'],
      ['sudo', 'resolvectl', 'default-route', 'okra0', 'no'],
    ])
  })

  test('rejects systemd-resolved configuration on macOS', () => {
    const config = new DnsConfig({
      server: '172.29.0.53',
      domains: ['ciroue.test'],
      interface: 'okra0',
    })
    expect(() => config.configureCommands('okra0', 'sudo', 'darwin'))
      .toThrow('The DNS step currently supports Linux with systemd-resolved only.')
  })
})
