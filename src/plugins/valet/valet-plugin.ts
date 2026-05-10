import { existsSync, mkdirSync } from 'node:fs'
import type { PluginInterface, Capable } from '../../types/plugin.js'
import type { Capability, CapabilityKey } from '../../types/capability.js'
import { CONFIG_PROVIDER, ENV_PROVIDER, PATH_PROVIDER, COMMAND_PROVIDER } from '../../types/capability.js'
import { ValetConfigProvider } from './valet-config-provider.js'
import { ValetEnvProvider } from './valet-env-provider.js'
import { ValetPathProvider } from './valet-path-provider.js'
import { ValetCommandProvider } from './valet-command-provider.js'
import { LocalValetConfig } from './config/local-valet-config.js'
import type { Dev } from '../../dev.js'

export class ValetPlugin implements PluginInterface, Capable {
  static readonly NAME = 'valet'
  readonly PLUGIN_API_VERSION = '0.0.0'

  localConfig: LocalValetConfig | null = null

  activate(dev: Dev): void {
    if (!dev.initialized) return

    const phpDir = dev.config.devPath('php.d')
    if (!existsSync(phpDir)) {
      mkdirSync(phpDir, { recursive: true })
    }

    this.localConfig = new LocalValetConfig(dev.config)
  }

  deactivate(_dev: Dev): void {}

  uninstall(dev: Dev): void {
    const phpDir = dev.config.devPath('php.d')
    if (existsSync(phpDir)) {
      import('node:fs').then(({ rmSync }) => rmSync(phpDir, { recursive: true, force: true }))
    }
  }

  capabilities(): Partial<Record<CapabilityKey, new (args: Record<string, unknown>) => Capability>> {
    return {
      [CONFIG_PROVIDER]: ValetConfigProvider,
      [ENV_PROVIDER]: ValetEnvProvider,
      [PATH_PROVIDER]: ValetPathProvider,
      [COMMAND_PROVIDER]: ValetCommandProvider,
    }
  }
}
