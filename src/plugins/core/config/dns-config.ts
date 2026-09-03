import { UserException } from '../../../exceptions.js'
import { elevationTool, type ElevationTool } from '../../../execution/privilege.js'
import { z } from 'zod'

export const dnsConfigSchema = z.object({
  server: z.string().min(1),
  domains: z.array(z.string().min(1)).min(1),
  docker_network: z.string().min(1).optional(),
  interface: z.string().min(1).optional(),
})

export type RawDnsConfig = z.infer<typeof dnsConfigSchema>

export class DnsConfig {
  constructor(private readonly config: RawDnsConfig) {}

  server(): string { return this.config.server }
  domains(): string[] { return this.config.domains }

  interface(
    inspect: (network: string) => { id: string; bridge: string } = DnsConfig.inspectDockerNetwork,
  ): string {
    if (this.config.interface) return this.config.interface
    if (!this.config.docker_network) {
      throw new UserException('DNS configuration requires either an interface or docker_network key.')
    }

    const network = inspect(this.config.docker_network)
    if (network.bridge) return network.bridge
    if (!network.id) throw new UserException(`Docker network '${this.config.docker_network}' does not exist.`)
    return `br-${network.id.slice(0, 12)}`
  }

  configureCommands(
    interfaceName: string,
    elevation: ElevationTool = elevationTool(),
    platform = process.platform,
  ): string[][] {
    if (platform !== 'linux') {
      throw new UserException('The DNS step currently supports Linux with systemd-resolved only.')
    }

    return [
      [elevation, 'resolvectl', 'dns', interfaceName, this.server()],
      [elevation, 'resolvectl', 'domain', interfaceName, ...this.domains().map(domain => `~${domain}`)],
      [elevation, 'resolvectl', 'default-route', interfaceName, 'no'],
    ]
  }

  private static inspectDockerNetwork(network: string): { id: string; bridge: string } {
    const proc = Bun.spawnSync([
      'docker', 'network', 'inspect', network,
      '--format', '{{.Id}}|{{index .Options "com.docker.network.bridge.name"}}',
    ])
    if (proc.exitCode !== 0) return { id: '', bridge: '' }
    const [id = '', rawBridge = ''] = new TextDecoder().decode(proc.stdout).trim().split('|')
    const bridge = rawBridge === '<no value>' ? '' : rawBridge
    return { id, bridge }
  }
}
