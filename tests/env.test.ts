import { describe, expect, test } from 'bun:test'
import { Env } from '../src/config/env.js'
import { Config } from '../src/config/config.js'
import { mkdtempSync, readFileSync, writeFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import { commandWorkingDirectory } from '../src/config/command.js'
import { EnvSubstituteStep } from '../src/plugins/core/steps/env-substitute-step.js'
import { Runner } from '../src/execution/runner.js'
import { Repository } from '../src/execution/repository.js'
import type { IOInterface } from '../src/types/io.js'

const silentIO: IOInterface = {
  writeln: () => {}, write: () => {}, info: () => {}, error: () => {}, dev: () => {},
  stepStart: () => {}, stepEnd: () => {},
  text: async () => '', password: async () => '',
}

describe('Env', () => {
  test('uses a supplied process value instead of prompting or persisting it', async () => {
    const env = new Env({
      TOKEN: {
        prompt: 'Token?',
        required: true,
      },
    }, { TOKEN: 'temporary-token' })

    const [resolved, prompted] = await env.resolve()

    expect(resolved.get('TOKEN')).toBe('temporary-token')
    expect(prompted).toEqual({})
    expect(env.wasPrompted()).toBeFalse()
  })

  test('accepts an explicitly supplied empty process value without prompting', async () => {
    const env = new Env({ TOKEN: { prompt: 'Token?', required: false } }, { TOKEN: '' })

    const [resolved, prompted] = await env.resolve()
    expect(resolved.get('TOKEN')).toBe('')
    expect(prompted).toEqual({})
    expect(env.wasPrompted()).toBeFalse()
  })
})

describe('command configuration', () => {
  test('preserves a project-relative working directory', () => {
    const root = mkdtempSync(join(tmpdir(), 'dev-command-cwd-'))
    writeFileSync(join(root, 'dev.yml'), 'commands:\n  inspect:\n    cwd: go\n    run: pwd\n')

    const config = Config.read(root)
    const command = config.commands()['inspect']
    expect(command?.cwd).toBe('go')
    expect(command && commandWorkingDirectory(config, command)).toBe(join(root, 'go'))
  })
})

describe('environment file updates', () => {
  test('uses single quotes and groups values owned by DEV', async () => {
    const root = mkdtempSync(join(tmpdir(), 'dev-env-file-'))
    writeFileSync(join(root, 'dev.yml'), [
      'env:',
      '  APP_NAME: Okra',
      '  DEV_ONLY: grouped',
      '',
    ].join('\n'))
    writeFileSync(join(root, '.env.example'), 'APP_NAME=Example\nSAMPLE_ONLY=sample\n')
    writeFileSync(join(root, '.env'), 'APP_NAME="Old"\n')
    const config = Config.read(root)
    const runner = new Runner(config, silentIO, new Repository())

    expect(await new EnvSubstituteStep(config).run(runner)).toBeTrue()
    expect(readFileSync(join(root, '.env'), 'utf8')).toBe([
      "APP_NAME='Okra'",
      "SAMPLE_ONLY='sample'",
      '',
      '# DEV managed environment — generated from dev.yml',
      "DEV_ONLY='grouped'",
      '# End DEV managed environment',
      '',
    ].join('\n'))
  })

  test('uses a valid double-quoted dotenv value when the value contains an apostrophe', async () => {
    const root = mkdtempSync(join(tmpdir(), 'dev-env-quote-'))
    writeFileSync(join(root, 'dev.yml'), "env:\n  OWNER: O'Reilly\n")
    writeFileSync(join(root, '.env.example'), 'OWNER=\n')
    writeFileSync(join(root, '.env'), 'OWNER=\n')
    const config = Config.read(root)

    expect(await new EnvSubstituteStep(config).run(new Runner(config, silentIO, new Repository()))).toBeTrue()
    expect(readFileSync(join(root, '.env'), 'utf8')).toBe('OWNER="O\'Reilly"\n')
  })
})
