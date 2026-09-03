import { existsSync, readFileSync } from 'node:fs'
import { UserException } from '../../../exceptions.js'
import { elevationTool, type ElevationTool } from '../../../execution/privilege.js'
import { z } from 'zod'

export const packageManagerNameSchema = z.enum(['apt', 'brew', 'pacman'])
export type PackageManagerName = z.infer<typeof packageManagerNameSchema>

export const packageSchema = z.union([
  z.string().min(1),
  z.object({
    name: z.string().min(1),
    apt: z.string().min(1).optional(),
    brew: z.string().min(1).optional(),
    pacman: z.string().min(1).optional(),
  }),
])
export type RawPackage = z.infer<typeof packageSchema>

export const packagesConfigSchema = z.union([
  z.array(packageSchema),
  z.object({ manager: packageManagerNameSchema.optional(), install: z.array(packageSchema) }),
])
export type RawPackagesConfig = z.infer<typeof packagesConfigSchema>

type PackageNames = Record<PackageManagerName, string | null>

const PACKAGE_NAMES: Record<string, PackageNames> = {
  'aws-cli': { apt: 'awscli', brew: 'awscli', pacman: 'aws-cli' },
  caddy: { apt: 'caddy', brew: 'caddy', pacman: 'caddy' },
  docker: { apt: 'docker.io', brew: 'docker', pacman: 'docker' },
  gettext: { apt: 'gettext', brew: 'gettext', pacman: 'gettext' },
  go: { apt: 'golang-go', brew: 'go', pacman: 'go' },
  lsof: { apt: 'lsof', brew: 'lsof', pacman: 'lsof' },
  node: { apt: 'nodejs', brew: 'node', pacman: 'nodejs' },
  npm: { apt: 'npm', brew: 'node', pacman: 'npm' },
  openssl: { apt: 'openssl', brew: 'openssl@3', pacman: 'openssl' },
  php: { apt: 'php-cli', brew: 'php', pacman: 'php' },
  'php-fpm': { apt: 'php-fpm', brew: 'php', pacman: 'php-fpm' },
  composer: { apt: 'composer', brew: 'composer', pacman: 'composer' },
  unzip: { apt: 'unzip', brew: 'unzip', pacman: 'unzip' },
  redis: { apt: 'redis-server', brew: 'redis', pacman: 'valkey' },
  s5cmd: { apt: null, brew: 'peak/tap/s5cmd', pacman: null },
  setcap: { apt: 'libcap2-bin', brew: null, pacman: 'libcap' },
  'go-air': { apt: null, brew: 'go-air', pacman: null },
}

export class PackageManager {
  constructor(
    public readonly name: PackageManagerName,
    private readonly elevation: ElevationTool = elevationTool(),
  ) {}

  static detect(
    platform = process.platform,
    commandExists: (command: string) => boolean = command => Bun.which(command) !== null,
    osReleasePath = '/etc/os-release',
  ): PackageManager {
    if (platform === 'darwin') {
      if (!commandExists('brew')) throw new UserException('Homebrew is required to install packages on macOS.')
      return new PackageManager('brew')
    }

    if (platform !== 'linux') {
      throw new UserException(`Portable package installation is not supported on ${platform}.`)
    }

    const osRelease = existsSync(osReleasePath) ? readFileSync(osReleasePath, 'utf8') : ''
    const distro = PackageManager.osReleaseValue(osRelease, 'ID')
    const like = PackageManager.osReleaseValue(osRelease, 'ID_LIKE').split(/\s+/).filter(Boolean)

    if ((distro === 'arch' || distro === 'omarchy' || like.includes('arch')) && commandExists('pacman')) {
      return new PackageManager('pacman')
    }

    if ((distro === 'debian' || distro === 'ubuntu' || like.includes('debian') || like.includes('ubuntu'))
      && commandExists('apt-get')) {
      return new PackageManager('apt')
    }

    if (commandExists('pacman')) return new PackageManager('pacman')
    if (commandExists('apt-get')) return new PackageManager('apt')
    if (commandExists('brew')) return new PackageManager('brew')

    throw new UserException('Could not detect a supported package manager. Supported managers: pacman, apt, brew.')
  }

  resolve(packages: RawPackage[]): string[] {
    return [...new Set(packages.map(pkg => this.resolveOne(pkg)))]
  }

  installCommand(packages: string[]): string[] {
    switch (this.name) {
      case 'apt': return [this.elevation, 'apt-get', 'install', '-y', ...packages]
      case 'brew': return ['brew', 'install', ...packages]
      case 'pacman': return [this.elevation, 'pacman', '-S', '--needed', '--noconfirm', ...packages]
    }
  }

  checkCommand(packageName: string): string[] {
    switch (this.name) {
      case 'apt': return [
        'dpkg-query', '-W', '-f=${db:Status-Status}', packageName,
        '|', 'grep', '-Fxq', 'installed',
      ]
      case 'brew': return ['brew', 'list', '--versions', packageName]
      case 'pacman': return ['pacman', '-Q', packageName]
    }
  }

  private resolveOne(pkg: RawPackage): string {
    const id = typeof pkg === 'string' ? pkg : pkg.name
    const explicit = typeof pkg === 'string' ? undefined : pkg[this.name]
    if (explicit) return explicit

    const mapped = PACKAGE_NAMES[id]?.[this.name]
    if (mapped) return mapped

    if (PACKAGE_NAMES[id] && mapped === null) {
      throw new UserException(
        `Package '${id}' has no ${this.name} package mapping. Provide an explicit '${this.name}' override or use a custom installer.`,
      )
    }

    return id
  }

  private static osReleaseValue(content: string, key: string): string {
    const line = content.split('\n').find(candidate => candidate.startsWith(`${key}=`))
    return line?.slice(key.length + 1).replace(/^['"]|['"]$/g, '').toLowerCase() ?? ''
  }
}
