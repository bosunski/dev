import { dirname } from 'node:path'
import type { PathProvider } from '../../types/capability.js'
import type { Dev } from '../../dev.js'
import type { ValetPlugin } from './valet-plugin.js'
import { valetProviderArgs } from './valet-provider-args.js'

export class ValetPathProvider implements PathProvider {
  private readonly dev: Dev
  private readonly plugin: ValetPlugin

  constructor(args: Record<string, unknown>) {
    const parsed = valetProviderArgs(args)
    this.dev = parsed.dev
    this.plugin = parsed.plugin
  }

  paths(): string[] {
    if (!this.plugin.localConfig) return []
    return [dirname(this.plugin.localConfig.get('bin'))]
  }
}
