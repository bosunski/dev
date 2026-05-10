import type { PluginInterface, Capable } from '../../types/plugin.js'
import type { Capability, CapabilityKey } from '../../types/capability.js'
import { CONFIG_PROVIDER, PATH_PROVIDER } from '../../types/capability.js'
import { ComposerConfigProvider } from './composer-config-provider.js'
import { ComposerPathProvider } from './composer-path-provider.js'
import type { Dev } from '../../dev.js'

export class ComposerPlugin implements PluginInterface, Capable {
  readonly PLUGIN_API_VERSION = '0.0.0'
  activate(_dev: Dev): void {}
  deactivate(_dev: Dev): void {}
  uninstall(_dev: Dev): void {}

  capabilities(): Partial<Record<CapabilityKey, new (args: Record<string, unknown>) => Capability>> {
    return {
      [CONFIG_PROVIDER]: ComposerConfigProvider,
      [PATH_PROVIDER]: ComposerPathProvider,
    }
  }
}
