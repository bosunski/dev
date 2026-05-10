import type { PluginInterface, Capable } from '../../types/plugin.js'
import type { Capability, CapabilityKey } from '../../types/capability.js'
import { CONFIG_PROVIDER, ENV_PROVIDER, COMMAND_PROVIDER } from '../../types/capability.js'
import { CaddyConfigProvider } from './caddy-config-provider.js'
import { CaddyEnvProvider } from './caddy-env-provider.js'
import { CaddyCommandProvider } from './caddy-command-provider.js'
import type { Dev } from '../../dev.js'

export class CaddyPlugin implements PluginInterface, Capable {
  static readonly NAME = 'caddy'
  readonly PLUGIN_API_VERSION = '0.0.0'

  activate(_dev: Dev): void {}
  deactivate(_dev: Dev): void {}
  uninstall(_dev: Dev): void {}

  capabilities(): Partial<Record<CapabilityKey, new (args: Record<string, unknown>) => Capability>> {
    return {
      [CONFIG_PROVIDER]: CaddyConfigProvider,
      [ENV_PROVIDER]: CaddyEnvProvider,
      [COMMAND_PROVIDER]: CaddyCommandProvider,
    }
  }
}
