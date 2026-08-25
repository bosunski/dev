import { describe, expect, test } from 'bun:test'
import { PackageManager } from '../src/plugins/core/config/package-manager.js'

describe('PackageManager', () => {
  test('detects pacman for Omarchy', () => {
    const manager = PackageManager.detect('linux', command => command === 'pacman', '/etc/os-release')
    expect(manager.name).toBe('pacman')
  })

  test('maps portable package names for pacman', () => {
    const manager = new PackageManager('pacman', 'sudo')
    expect(manager.resolve(['openssl', 'aws-cli', 'redis', 'go', 'caddy']))
      .toEqual(['openssl', 'aws-cli', 'valkey', 'go', 'caddy'])
  })

  test('maps native PHP and Composer across supported managers', () => {
    expect(new PackageManager('pacman', 'sudo').resolve(['php', 'php-fpm', 'composer']))
      .toEqual(['php', 'php-fpm', 'composer'])
    expect(new PackageManager('apt', 'sudo').resolve(['php', 'php-fpm', 'composer']))
      .toEqual(['php-cli', 'php-fpm', 'composer'])
    expect(new PackageManager('brew', 'sudo').resolve(['php', 'php-fpm', 'composer']))
      .toEqual(['php', 'composer'])
  })

  test('uses Homebrew Node for both node and npm without duplicates', () => {
    const manager = new PackageManager('brew', 'sudo')
    expect(manager.resolve(['node', 'npm'])).toEqual(['node'])
  })

  test('supports per-manager overrides', () => {
    const manager = new PackageManager('pacman', 'sudo')
    expect(manager.resolve([{ name: 'custom-tool', pacman: 'custom-tool-bin' }]))
      .toEqual(['custom-tool-bin'])
  })

  test('refuses unsupported native mappings', () => {
    const manager = new PackageManager('pacman', 'sudo')
    expect(() => manager.resolve(['s5cmd'])).toThrow("Package 's5cmd' has no pacman package mapping")
  })

  test('builds non-upgrading pacman installation command', () => {
    const manager = new PackageManager('pacman', 'sudo')
    expect(manager.installCommand(['go', 'caddy']))
      .toEqual(['sudo', 'pacman', '-S', '--needed', '--noconfirm', 'go', 'caddy'])
  })
})
