import type { PluginInterface, Capable } from '../types/plugin.js'
import type { Capability, CapabilityKey } from '../types/capability.js'
import type { Dev } from '../dev.js'
import type { IOInterface } from '../types/io.js'

export class PluginManager {
  private plugins: PluginInterface[] = []

  constructor(
    private readonly dev: Dev,
    private readonly io: IOInterface,
  ) {}

  addPlugin(plugin: PluginInterface): void {
    this.plugins.push(plugin)
    plugin.activate(this.dev)
  }

  loadInstalledPlugins(pluginClasses: Array<new () => PluginInterface>): void {
    for (const Ctor of pluginClasses) {
      this.addPlugin(new Ctor())
    }
  }

  getPluginCapabilities<T extends Capability>(
    key: CapabilityKey,
    ctorArgs: Record<string, unknown> = {},
  ): T[] {
    const results: T[] = []

    for (const plugin of this.plugins) {
      const cap = this.getPluginCapability<T>(plugin, key, ctorArgs)
      if (cap !== null) results.push(cap)
    }

    return results
  }

  getPluginCapability<T extends Capability>(
    plugin: PluginInterface,
    key: CapabilityKey,
    ctorArgs: Record<string, unknown> = {},
  ): T | null {
    if (!this.isCapable(plugin)) return null

    const capabilities = plugin.capabilities()
    const Ctor = capabilities[key]
    if (!Ctor) return null

    return new Ctor({ ...ctorArgs, plugin }) as T
  }

  private isCapable(plugin: PluginInterface): plugin is PluginInterface & Capable {
    return 'capabilities' in plugin && typeof (plugin as Capable).capabilities === 'function'
  }
}
