import { dirname } from 'node:path'
import type { PathProvider } from '../../types/capability.js'
import type { Dev } from '../../dev.js'
import type { ValetPlugin } from './valet-plugin.js'

export class ValetPathProvider implements PathProvider {
  private readonly dev: Dev
  private readonly plugin: ValetPlugin

  constructor(args: Record<string, unknown>) {
    this.dev = args['dev'] as Dev
    this.plugin = args['plugin'] as ValetPlugin
  }

  paths(): string[] {
    if (!this.plugin.localConfig) return []
    return [dirname(this.plugin.localConfig.get('bin'))]
  }
}
