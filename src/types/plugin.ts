import type { Capability, CapabilityKey } from './capability.js'

export interface PluginInterface {
  readonly PLUGIN_API_VERSION: string
  activate(dev: import('../dev.js').Dev): void
  deactivate(dev: import('../dev.js').Dev): void
  uninstall(dev: import('../dev.js').Dev): void
}

export interface Capable {
  capabilities(): Partial<Record<CapabilityKey, new (args: Record<string, unknown>) => Capability>>
}
