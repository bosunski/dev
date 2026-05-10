import type { EnvProvider } from '../../types/capability.js'
import type { Dev } from '../../dev.js'

export class CaddyEnvProvider implements EnvProvider {
  private readonly dev: Dev

  constructor(args: Record<string, unknown>) {
    this.dev = args['dev'] as Dev
  }

  envs(): Record<string, string> {
    return {
      CADDY_SITE_PATH: this.dev.config.globalPath('caddy/sites'),
    }
  }
}
