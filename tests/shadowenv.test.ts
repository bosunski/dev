import { describe, expect, test } from 'bun:test'
import { mkdtempSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import { Config } from '../src/config/config.js'
import { Repository } from '../src/execution/repository.js'
import { Runner } from '../src/execution/runner.js'
import { shadowenvAsset } from '../src/plugins/core/steps/shadowenv/ensure-shadowenv-step.js'
import type { IOInterface } from '../src/types/io.js'

const silentIO: IOInterface = {
  writeln: () => {}, write: () => {}, info: () => {}, error: () => {}, dev: () => {},
  stepStart: () => {}, stepEnd: () => {},
  text: async () => '', password: async () => '',
}

describe('Shadowenv installer', () => {
  test('selects the official Linux x86_64 release asset', () => {
    expect(shadowenvAsset('linux', 'x64')).toBe('shadowenv-x86_64-unknown-linux-gnu')
  })

  test('selects the official macOS arm64 release asset', () => {
    expect(shadowenvAsset('darwin', 'arm64')).toBe('shadowenv-aarch64-apple-darwin')
  })

  test('runs commands directly when a fresh home has no shell profile', () => {
    const previousHome = process.env['HOME']
    const previousShell = process.env['SHELL']
    process.env['HOME'] = mkdtempSync(join(tmpdir(), 'dev-shadowenv-home-'))
    process.env['SHELL'] = '/bin/bash'
    try {
      const config = new Config('/project', {})
      const runner = new Runner(config, silentIO, new Repository())
      expect(runner.createShadowEnvCommand(['printf', 'ok'])).toEqual(['printf', 'ok'])
    } finally {
      if (previousHome === undefined) delete process.env['HOME']
      else process.env['HOME'] = previousHome
      if (previousShell === undefined) delete process.env['SHELL']
      else process.env['SHELL'] = previousShell
    }
  })
})
