import type { PackageManagerName } from './package-manager.js'
import { UserException } from '../../../exceptions.js'
import { elevationTool, type ElevationTool } from '../../../execution/privilege.js'
import { z } from 'zod'

type ServiceNames = Record<PackageManagerName, string | null>

const SERVICE_NAMES: Record<string, ServiceNames> = {
  redis: { apt: 'redis-server', brew: 'redis', pacman: 'valkey' },
  caddy: { apt: 'caddy', brew: 'caddy', pacman: 'caddy' },
  docker: { apt: 'docker', brew: null, pacman: 'docker' },
}

export const serviceConfigSchema = z.union([
  z.string().min(1),
  z.object({ name: z.string().min(1), unit: z.string().min(1).optional() }),
])
export type RawServiceConfig = z.infer<typeof serviceConfigSchema>

export class ServiceManager {
  constructor(
    public readonly packageManager: PackageManagerName,
    private readonly elevation: ElevationTool = elevationTool(),
  ) {}

  resolve(config: RawServiceConfig): string {
    const name = typeof config === 'string' ? config : config.name
    const override = typeof config === 'string' ? undefined : config.unit
    if (override) return override

    const mapped = SERVICE_NAMES[name]?.[this.packageManager]
    if (mapped) return mapped
    if (SERVICE_NAMES[name] && mapped === null) {
      throw new UserException(`Service '${name}' is not supported by ${this.packageManager}.`)
    }
    return name
  }

  startCommand(service: string): string[] {
    if (this.packageManager === 'brew') return ['brew', 'services', 'start', service]
    return [this.elevation, 'systemctl', 'enable', '--now', service]
  }

  checkCommand(service: string): string[] {
    if (this.packageManager === 'brew') return ['brew', 'services', 'info', service]
    return ['systemctl', 'is-active', '--quiet', service]
  }
}
