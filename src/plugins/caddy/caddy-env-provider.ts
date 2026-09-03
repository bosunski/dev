import type { EnvProvider } from '../../types/capability.js'
import type { Dev } from '../../dev.js'
import { caddyProjectSitesDir } from './caddy-layout.js'
import { pluginDev } from '../../plugin/plugin-args.js'
import { hasCaddyConfig } from './caddy-step-resolver.js'

export class CaddyEnvProvider implements EnvProvider {
  private readonly dev: Dev

  constructor(args: Record<string, unknown>) {
    this.dev = pluginDev(args)
  }

  envs(): Record<string, string> {
    const steps = this.dev.config.raw_().steps ?? this.dev.config.raw_().up ?? []
    if (!steps.some(hasCaddyConfig)) return {}

    return {
      CADDY_SITE_PATH: caddyProjectSitesDir(
        this.dev.config.globalPath('caddy'),
        this.dev.config.cwd(),
      ),
      LOCAL_DEV_CA_CERT_PATH: this.dev.config.globalPath('caddy/ca/root.crt'),
    }
  }
}
