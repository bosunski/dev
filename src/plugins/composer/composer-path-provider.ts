import type { PathProvider } from '../../types/capability.js'
import type { Dev } from '../../dev.js'
import { Config } from '../../config/config.js'

export class ComposerPathProvider implements PathProvider {
  private readonly dev: Dev
  constructor(args: Record<string, unknown>) {
    this.dev = args['dev'] as Dev
  }
  paths(): string[] {
    return [Config.home('.composer/vendor/bin')]
  }
}
