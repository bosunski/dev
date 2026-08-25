import { describe, expect, test } from 'bun:test'
import { PhpRuntime } from '../src/plugins/php-runtime/php-runtime.js'
import { Config } from '../src/config/config.js'
import { mkdtempSync, writeFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'

describe('PhpRuntime', () => {
  test('supports exact minor and bounded version requirements', () => {
    expect(PhpRuntime.satisfies('8.2.33', '8.2')).toBeTrue()
    expect(PhpRuntime.satisfies('8.3.0', '8.2')).toBeFalse()
    expect(PhpRuntime.satisfies('8.5.9', '>=8.2 <8.6')).toBeTrue()
    expect(PhpRuntime.satisfies('8.6.0', '>=8.2 <8.6')).toBeFalse()
    expect(PhpRuntime.satisfies('8.4.2', '^8.2')).toBeTrue()
    expect(PhpRuntime.satisfies('9.0.0', '^8.2')).toBeFalse()
  })

  test('uses a project-scoped Unix socket endpoint', () => {
    const root = mkdtempSync(join(tmpdir(), 'dev-php-runtime-'))
    writeFileSync(join(root, 'dev.yml'), 'runtimes:\n  php:\n    version: "8.5"\n')
    const config = Config.read(root)
    expect(PhpRuntime.endpoint(config, 'php'))
      .toBe(`unix/${root}/.dev/runtimes/php/php-fpm.sock`)
  })

  test('resolves an SPC runtime into an extension-specific cache', () => {
    const root = mkdtempSync(join(tmpdir(), 'dev-php-spc-'))
    writeFileSync(join(root, 'dev.yml'), [
      'runtimes:',
      '  php:',
      '    version: "8.4"',
      '    provider: spc',
      '    extensions: [curl, pdo_mysql]',
      '',
    ].join('\n'))
    const runtime = PhpRuntime.resolve(Config.read(root), 'php')
    expect(runtime.provider).toBe('spc')
    expect(runtime.version).toBe('8.4')
    expect(runtime.binDir).toContain('/runtimes/php/8.4/spc/')
    expect(runtime.phpBinary).toBe(join(runtime.binDir, 'php'))
    expect(runtime.fpmBinary).toBe(join(runtime.binDir, 'php-fpm'))
  })

  test('uses separate SPC artifacts for distinct extension sets', () => {
    const root = mkdtempSync(join(tmpdir(), 'dev-php-spc-cache-'))
    const configPath = join(root, 'dev.yml')
    writeFileSync(configPath, 'runtimes:\n  php:\n    version: "8.4"\n    provider: spc\n    extensions: [curl]\n')
    const curlRuntime = PhpRuntime.resolve(Config.read(root), 'php')
    writeFileSync(configPath, 'runtimes:\n  php:\n    version: "8.4"\n    provider: spc\n    extensions: [curl, zip]\n')
    const zipRuntime = PhpRuntime.resolve(Config.read(root), 'php')
    expect(curlRuntime.root).not.toBe(zipRuntime.root)
  })

})
