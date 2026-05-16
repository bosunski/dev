import { Config } from './config/config.js'
import { Runner } from './execution/runner.js'
import { Repository } from './execution/repository.js'
import { Dev } from './dev.js'
import { PluginManager } from './plugin/plugin-manager.js'
import { StdIO } from './io/stdio.js'
import { Value } from './config/value.js'
import { CorePlugin } from './plugins/core/core-plugin.js'
import { BrewPlugin } from './plugins/brew/brew-plugin.js'
import { ComposerPlugin } from './plugins/composer/composer-plugin.js'
import { ValetPlugin } from './plugins/valet/valet-plugin.js'
import { SpcPlugin } from './plugins/spc/spc-plugin.js'
import { CaddyPlugin } from './plugins/caddy/caddy-plugin.js'
import { DopplerPlugin } from './plugins/doppler/doppler-plugin.js'
import type { PluginInterface } from './types/plugin.js'

const DEFAULT_PLUGINS: Array<new () => PluginInterface> = [
  CorePlugin,
  ValetPlugin,
  BrewPlugin,
  ComposerPlugin,
  SpcPlugin,
  CaddyPlugin,
  DopplerPlugin,
]

let _dev: Dev | null = null

export async function getDevContext(): Promise<{ dev: Dev; io: StdIO }> {
  if (_dev) return { dev: _dev, io: _dev.io() as StdIO }

  const io = new StdIO()
  Value.setIO(io)

  const config = Config.read(process.cwd())
  const repo = new Repository()
  const runner = new Runner(config, io, repo)
  const dev = new Dev(config, runner, io)

  const pm = new PluginManager(dev, io)
  pm.loadInstalledPlugins(DEFAULT_PLUGINS)
  dev.setPluginManager(pm)

  _dev = dev
  return { dev, io }
}

export function createDevFor(config: Config): Dev {
  const io = new StdIO()
  const repo = new Repository()
  const runner = new Runner(config, io, repo)
  const dev = new Dev(config, runner, io)

  // Reuse the same plugin manager from the singleton for step resolvers
  if (_dev) {
    dev.setPluginManager(_dev.getPluginManager())
  } else {
    const pm = new PluginManager(dev, io)
    pm.loadInstalledPlugins(DEFAULT_PLUGINS)
    dev.setPluginManager(pm)
  }

  return dev
}
