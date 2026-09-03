import { createHash } from 'node:crypto'
import { join } from 'node:path'
import { z } from 'zod'
import type { Config } from '../../config/config.js'
import { UserException } from '../../exceptions.js'
import type { RawRuntime } from '../../types/config.js'
import { PackageManager, type PackageManagerName } from '../core/config/package-manager.js'

const brewInfoSchema = z.object({ formulae: z.array(z.object({ versions: z.object({ stable: z.string().nullable() }) })) })

type RuntimeCommon = {
  name: string; requirement: string; version: string; phpBinary: string; fpmBinary: string
  socketPath: string; root: string; binDir: string; fpmConfig: string; extensions: string[]
  iniDir: string; iniFile: string
}
export type NativePhpRuntime = RuntimeCommon & {
  provider: 'native'
  packages: string[]
  sourcePhpBinary: string
  sourceFpmBinary: string
}
export type SpcPhpRuntime = RuntimeCommon & {
  provider: 'spc'; spcBinary: string; target: string | null; downloadCache: string; toolchainCache: string
}
export type ResolvedPhpRuntime = NativePhpRuntime | SpcPhpRuntime
type NativeCandidate = {
  version: string; cliPackage: string; fpmPackage: string; phpBinary: string; fpmBinary: string; extensionPrefix: string
}

export class PhpRuntime {
  private static readonly candidateCache = new Map<string, NativeCandidate[]>()

  static definitions(config: Config): Array<[string, RawRuntime]> {
    return Object.entries(config.runtimes()).filter(([, runtime]) => (runtime.type ?? 'php') === 'php')
  }

  static resolve(config: Config, name: string, manager = PackageManager.detect()): ResolvedPhpRuntime {
    const raw = this.definition(config, name)
    if ((raw.server ?? 'fpm') !== 'fpm') throw new UserException(`Unsupported PHP server for '${name}'.`)
    const requirement = raw.version ?? '*'
    const provider = raw.provider ?? 'auto'
    const extensions = [...new Set(raw.extensions ?? [])].sort()
    const native = provider === 'spc' ? undefined : this.cachedCandidates(manager.name, requirement)
      .find(candidate => this.satisfies(candidate.version, requirement))
    if (native) return this.nativeRuntime(config, name, requirement, extensions, native, manager.name)
    if (provider === 'native') throw new UserException(`The native ${manager.name} repositories cannot satisfy PHP '${requirement}'.`)
    return this.spcRuntime(config, name, requirement, extensions)
  }

  static endpoint(config: Config, name: string): string {
    this.definition(config, name)
    return `unix/${this.socketPath(config, name)}`
  }

  static socketPath(config: Config, name: string): string {
    const uid = process.getuid?.() ?? 0
    const fingerprint = createHash('sha256').update(`${config.cwd()}\0${name}`).digest('hex').slice(0, 16)
    return `/tmp/dev-php-${uid}-${fingerprint}.sock`
  }

  static aptFpmBinary(version: string): string {
    const versionLine = version.match(/^\d+\.\d+/)?.[0]
    return versionLine ? `/usr/sbin/php-fpm${versionLine}` : '/usr/sbin/php-fpm'
  }

  static primary(config: Config): [string, RawRuntime] | null {
    const definitions = this.definitions(config)
    if (definitions.length === 0) return null
    const namedPhp = definitions.find(([name]) => name === 'php')
    if (namedPhp) return namedPhp
    if (definitions.length === 1) return definitions[0]!
    throw new UserException("Multiple PHP runtimes require one runtime named 'php' to select the project default.")
  }

  static satisfies(version: string, requirement: string): boolean {
    if (!requirement || requirement === '*' || requirement === 'latest') return true
    const actual = this.parts(version)
    if (/^\d+\.\d+$/.test(requirement)) return actual[0] === Number(requirement.split('.')[0]) && actual[1] === Number(requirement.split('.')[1])
    if (/^\d+\.\d+\.\d+$/.test(requirement)) return this.compare(actual, this.parts(requirement)) === 0
    if (requirement.startsWith('^')) {
      const minimum = this.parts(requirement.slice(1))
      return this.compare(actual, minimum) >= 0 && actual[0] === minimum[0]
    }
    return requirement.split(/\s+/).every(token => {
      const match = token.match(/^(>=|<=|>|<|=)?(\d+(?:\.\d+){0,2})$/)
      if (!match) return false
      const comparison = this.compare(actual, this.parts(match[2]!))
      return match[1] === '>=' ? comparison >= 0 : match[1] === '<=' ? comparison <= 0
        : match[1] === '>' ? comparison > 0 : match[1] === '<' ? comparison < 0 : comparison === 0
    })
  }

  private static nativeRuntime(config: Config, name: string, requirement: string, extensions: string[], candidate: NativeCandidate, manager: PackageManagerName): NativePhpRuntime {
    const versionLine = candidate.version.match(/^\d+\.\d+/)?.[0] ?? candidate.version
    const root = config.globalPath(`runtimes/php/${versionLine}/native`)
    return {
      provider: 'native', name, requirement, version: candidate.version,
      phpBinary: join(root, 'bin/php'), fpmBinary: join(root, 'bin/php-fpm'),
      sourcePhpBinary: candidate.phpBinary, sourceFpmBinary: candidate.fpmBinary,
      socketPath: this.socketPath(config, name), root, binDir: join(root, 'bin'),
      fpmConfig: config.devPath(`runtimes/${name}/php-fpm.conf`), extensions,
      iniDir: config.devPath(`runtimes/${name}/php.d`), iniFile: config.devPath(`runtimes/${name}/php.d/extensions.ini`),
      packages: [...new Set([candidate.cliPackage, candidate.fpmPackage, ...this.extensionPackages(manager, candidate.extensionPrefix, versionLine, extensions)])],
    }
  }

  private static spcRuntime(config: Config, name: string, requirement: string, extensions: string[]): SpcPhpRuntime {
    const version = requirement.match(/^(\d+\.\d+)(?:\.\d+)?$/)?.[1]
    if (!version) throw new UserException(`SPC requires an exact PHP minor version such as '8.4'; '${requirement}' is ambiguous.`)
    const target = process.platform === 'linux' ? 'native-native-gnu' : null
    const fingerprint = createHash('sha256').update(JSON.stringify({ version, extensions, target })).digest('hex').slice(0, 16)
    const root = config.globalPath(`runtimes/php/${version}/spc/${fingerprint}`)
    const binDir = join(root, 'buildroot/bin')
    return {
      provider: 'spc', name, requirement, version, phpBinary: join(binDir, 'php'), fpmBinary: join(binDir, 'php-fpm'),
      socketPath: this.socketPath(config, name), root, binDir,
      fpmConfig: config.devPath(`runtimes/${name}/php-fpm.conf`), extensions,
      iniDir: config.devPath(`runtimes/${name}/php.d`), iniFile: config.devPath(`runtimes/${name}/php.d/extensions.ini`),
      spcBinary: config.globalPath('bin/spc'),
      target,
      downloadCache: config.globalPath(`runtimes/php/${version}/spc/downloads`),
      toolchainCache: config.globalPath(`runtimes/php/${version}/spc/toolchains/${target ?? 'native'}`),
    }
  }

  private static candidates(manager: PackageManagerName, requirement: string): NativeCandidate[] {
    if (manager === 'pacman') return [
      this.pacmanCandidate('php', 'php-fpm', 'php', 'php-fpm'),
      this.pacmanCandidate('php-legacy', 'php-legacy-fpm', 'php-legacy', 'php-fpm-legacy'),
    ].filter((candidate): candidate is NativeCandidate => candidate !== null)
    const exactLine = requirement.match(/^(?:\^)?(\d+\.\d+)$/)?.[1]
    if (manager === 'apt') {
      const names = exactLine ? [[`php${exactLine}-cli`, `php${exactLine}-fpm`, `/usr/bin/php${exactLine}`]] : [['php-cli', 'php-fpm', '/usr/bin/php']]
      return names.map(([cli, fpm, phpBin]) => this.aptCandidate(cli!, fpm!, phpBin!)).filter((candidate): candidate is NativeCandidate => candidate !== null)
    }
    const formula = exactLine ? `php@${exactLine}` : 'php'
    const info = Bun.spawnSync(['brew', 'info', '--json=v2', formula], { stdout: 'pipe', stderr: 'pipe' })
    if (info.exitCode !== 0) return []
    const version = brewInfoSchema.parse(JSON.parse(info.stdout.toString())).formulae[0]?.versions.stable
    if (!version) return []
    const prefix = join(Bun.spawnSync(['brew', '--prefix'], { stdout: 'pipe', stderr: 'pipe' }).stdout.toString().trim(), 'opt', formula)
    return [{ version, cliPackage: formula, fpmPackage: formula, phpBinary: join(prefix, 'bin/php'), fpmBinary: join(prefix, 'sbin/php-fpm'), extensionPrefix: formula }]
  }

  private static cachedCandidates(manager: PackageManagerName, requirement: string): NativeCandidate[] {
    const key = `${manager}:${requirement}`
    const existing = this.candidateCache.get(key)
    if (existing) return existing
    const candidates = this.candidates(manager, requirement)
    this.candidateCache.set(key, candidates)
    return candidates
  }

  private static definition(config: Config, name: string): RawRuntime {
    const raw = config.runtimes()[name]
    if (!raw || (raw.type ?? 'php') !== 'php') throw new UserException(`Unknown PHP runtime '${name}'.`)
    return raw
  }

  private static pacmanCandidate(cliPackage: string, fpmPackage: string, phpBinary: string, fpmBinary: string): NativeCandidate | null {
    const result = Bun.spawnSync(['pacman', '-Si', cliPackage], { stdout: 'pipe', stderr: 'pipe' })
    const version = result.exitCode === 0 ? result.stdout.toString().match(/^Version\s*:\s*([^\s-]+)/m)?.[1] : undefined
    return version ? { version, cliPackage, fpmPackage, phpBinary, fpmBinary, extensionPrefix: cliPackage } : null
  }

  private static aptCandidate(cliPackage: string, fpmPackage: string, phpBinary: string): NativeCandidate | null {
    const result = Bun.spawnSync(['apt-cache', 'policy', cliPackage], { stdout: 'pipe', stderr: 'pipe' })
    const version = result.exitCode === 0 ? result.stdout.toString().match(/Candidate:\s*(?:\d+:)?([^\s~+-]+)/)?.[1] : undefined
    return version && version !== '(none)' ? {
      version, cliPackage, fpmPackage, phpBinary, fpmBinary: this.aptFpmBinary(version),
      extensionPrefix: `php${version.match(/^\d+\.\d+/)?.[0] ?? ''}`,
    } : null
  }

  private static extensionPackages(manager: PackageManagerName, prefix: string, version: string, extensions: string[]): string[] {
    if (manager === 'brew') return []
    return extensions.flatMap(extension => {
      const packageName = manager === 'pacman' ? `${prefix}-${extension === 'mysqli' ? 'mysql' : extension}` : `php${version}-${extension}`
      const result = Bun.spawnSync(manager === 'pacman' ? ['pacman', '-Si', packageName] : ['apt-cache', 'policy', packageName], { stdout: 'pipe', stderr: 'ignore' })
      return result.exitCode !== 0 || (manager === 'apt' && result.stdout.toString().includes('Candidate: (none)')) ? [] : [packageName]
    })
  }

  private static parts(version: string): [number, number, number] {
    const values = version.match(/\d+/g)?.slice(0, 3).map(Number) ?? []
    return [values[0] ?? 0, values[1] ?? 0, values[2] ?? 0]
  }
  private static compare(a: [number, number, number], b: [number, number, number]): number {
    for (let i = 0; i < 3; i++) if (a[i] !== b[i]) return a[i]! - b[i]!
    return 0
  }
}
