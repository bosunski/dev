import { describe, expect, test } from 'bun:test'
import { ServiceManager } from '../src/plugins/core/config/service-manager.js'

describe('ServiceManager', () => {
  test('maps Redis to Valkey on Pacman systems', () => {
    const manager = new ServiceManager('pacman', 'sudo')
    expect(manager.resolve('redis')).toBe('valkey')
    expect(manager.startCommand('valkey')).toEqual([
      'sudo', 'systemctl', 'enable', '--now', 'valkey',
    ])
  })

  test('uses brew services only when Brew was selected', () => {
    const manager = new ServiceManager('brew', 'sudo')
    expect(manager.resolve('redis')).toBe('redis')
    expect(manager.startCommand('redis')).toEqual(['brew', 'services', 'start', 'redis'])
  })

  test('allows an explicit native unit override', () => {
    const manager = new ServiceManager('apt', 'sudo')
    expect(manager.resolve({ name: 'redis', unit: 'custom-redis' })).toBe('custom-redis')
  })
})
