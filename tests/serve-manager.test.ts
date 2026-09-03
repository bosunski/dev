import { describe, expect, test } from 'bun:test'
import { mkdtempSync, writeFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import { Config } from '../src/config/config.js'
import { Dev } from '../src/dev.js'
import { Repository } from '../src/execution/repository.js'
import { Runner } from '../src/execution/runner.js'
import { PluginManager } from '../src/plugin/plugin-manager.js'
import { ServeManager } from '../src/process/serve-manager.js'
import type { IOInterface } from '../src/types/io.js'

const silentIO: IOInterface = {
  writeln: () => {}, write: () => {}, info: () => {}, error: () => {}, dev: () => {},
  stepStart: () => {}, stepEnd: () => {},
  text: async () => '', password: async () => '',
}

function devAt(root: string): Dev {
  const config = Config.read(root)
  const dev = new Dev(config, new Runner(config, silentIO, new Repository()), silentIO)
  dev.setPluginManager(new PluginManager(dev, silentIO))
  return dev
}

describe('ServeManager environments', () => {
  test('lets explicit project configuration override dotenv values', async () => {
    const root = mkdtempSync(join(tmpdir(), 'dev-serve-env-'))
    writeFileSync(join(root, 'dev.yml'), 'env:\n  APP_MODE: configured\nserve:\n  app: printenv APP_MODE\n')
    writeFileSync(join(root, '.env'), 'APP_MODE=stale\n')
    const manager = new ServeManager(devAt(root))
    const entries = await (manager as unknown as {
      collectProcesses(groups?: string[]): Promise<Array<{ env: Record<string, string> }>>
    }).collectProcesses()

    expect(entries[0]?.env['APP_MODE']).toBe('configured')
  })

  test('decodes escapes in generated double-quoted dotenv values', () => {
    const root = mkdtempSync(join(tmpdir(), 'dev-serve-dotenv-'))
    writeFileSync(join(root, 'dev.yml'), '')
    const manager = new ServeManager(devAt(root))
    const parsed = (manager as unknown as {
      parseDotenv(content: string): Record<string, string>
    }).parseDotenv('VALUE="line 1\\nO\'Reilly \\"quoted\\""\n')

    expect(parsed['VALUE']).toBe('line 1\nO\'Reilly "quoted"')
  })
})
