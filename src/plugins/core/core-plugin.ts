import type { PluginInterface, Capable } from '../../types/plugin.js'
import type { Capability, CapabilityKey } from '../../types/capability.js'
import { CONFIG_PROVIDER, COMMAND_PROVIDER } from '../../types/capability.js'
import { CoreConfigProvider } from './core-config-provider.js'
import { CoreCommandProvider } from './core-command-provider.js'
import type { Dev } from '../../dev.js'

export class CorePlugin implements PluginInterface, Capable {
  readonly PLUGIN_API_VERSION = '0.0.0'

  activate(_dev: Dev): void {}
  deactivate(_dev: Dev): void {}
  uninstall(_dev: Dev): void {}

  capabilities(): Partial<Record<CapabilityKey, new (args: Record<string, unknown>) => Capability>> {
    return {
      [CONFIG_PROVIDER]: CoreConfigProvider,
      [COMMAND_PROVIDER]: CoreCommandProvider,
    }
  }
}
