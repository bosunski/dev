import { existsSync, mkdirSync } from 'node:fs'
import type { PluginInterface, Capable } from '../../types/plugin.js'
import type { Capability, CapabilityKey } from '../../types/capability.js'
import { CONFIG_PROVIDER, COMMAND_PROVIDER } from '../../types/capability.js'
import { SpcConfigProvider } from './spc-config-provider.js'
import { SpcCommandProvider } from './spc-command-provider.js'
import type { Dev } from '../../dev.js'

export class SpcPlugin implements PluginInterface, Capable {
  readonly PLUGIN_API_VERSION = '0.0.0'

  activate(dev: Dev): void {
    const spcDir = dev.config.globalPath('spc')
    if (!existsSync(spcDir)) mkdirSync(spcDir, { recursive: true })
  }

  deactivate(_dev: Dev): void {}

  uninstall(dev: Dev): void {
    const spcDir = dev.config.globalPath('spc')
    if (existsSync(spcDir)) {
      import('node:fs').then(({ rmSync }) => rmSync(spcDir, { recursive: true, force: true }))
    }
  }

  capabilities(): Partial<Record<CapabilityKey, new (args: Record<string, unknown>) => Capability>> {
    return {
      [CONFIG_PROVIDER]: SpcConfigProvider,
      [COMMAND_PROVIDER]: SpcCommandProvider,
    }
  }
}
