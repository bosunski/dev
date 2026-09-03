import type { PluginInterface, Capable } from '../../types/plugin.js'
import type { Capability, CapabilityKey } from '../../types/capability.js'
import { CONFIG_PROVIDER, ENV_PROVIDER, PATH_PROVIDER, SERVE_PROVIDER } from '../../types/capability.js'
import type { Dev } from '../../dev.js'
import {
  PhpRuntimeConfigProvider,
  PhpRuntimeEnvProvider,
  PhpRuntimePathProvider,
  PhpRuntimeServeProvider,
} from './php-runtime-providers.js'

export class PhpRuntimePlugin implements PluginInterface, Capable {
  static readonly NAME = 'php-runtime'
  readonly PLUGIN_API_VERSION = '0.0.0'
  activate(_dev: Dev): void {}
  deactivate(_dev: Dev): void {}
  uninstall(_dev: Dev): void {}

  capabilities(): Partial<Record<CapabilityKey, new (args: Record<string, unknown>) => Capability>> {
    return {
      [CONFIG_PROVIDER]: PhpRuntimeConfigProvider,
      [ENV_PROVIDER]: PhpRuntimeEnvProvider,
      [PATH_PROVIDER]: PhpRuntimePathProvider,
      [SERVE_PROVIDER]: PhpRuntimeServeProvider,
    }
  }
}
