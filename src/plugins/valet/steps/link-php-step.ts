import { existsSync, lstatSync, realpathSync, mkdirSync } from 'node:fs'
import { dirname } from 'node:path'
import type { Runner } from '../../../execution/runner.js'
import { BaseStep } from '../../../step/base-step.js'
import type { LocalValetConfig } from '../config/local-valet-config.js'
import type { Dev } from '../../../dev.js'
import { UserException } from '../../../exceptions.js'

// Match PHP_VERSION_MAP from PHP source
const PHP_VERSION_MAP: Record<string, string> = {
  '8.5': 'php',
  '8.4': 'php@8.4',
  '8.3': 'php@8.3',
  '8.2': 'php@8.2',
  '8.1': 'php@8.1',
  '8.0': 'php@8.0',
  '7.4': 'php@7.4',
}

export class LinkPhpStep extends BaseStep {
  constructor(
    private readonly version: string,
    private readonly localConfig: LocalValetConfig,
    private readonly dev: Dev,
  ) {
    super()
  }

  name(): string {
    return `Install and Link PHP v${this.version}`
  }

  id(): string {
    return `valet-link-php-${this.version}`
  }

  async done(_runner: Runner): Promise<boolean> {
    const linkPath = this.dev.config.path('bin/php')
    try {
      const stat = lstatSync(linkPath)
      if (!stat.isSymbolicLink()) return false
      const realTarget = realpathSync(linkPath)
      const expectedBin = this.phpPath(this.version)
      return realTarget === expectedBin && this.localConfig.get('php') === realTarget
    } catch {
      return false
    }
  }

  async run(runner: Runner): Promise<boolean> {
    const formula = this.formula(this.version)
    if (!await runner.exec(['brew', 'install', formula])) return false

    const phpBin = this.phpPath(this.version)
    if (!existsSync(phpBin)) {
      runner.getIO().error(`PHP v${this.version} is not installed`)
      return false
    }

    const linkPath = this.dev.config.path('bin/php')
    const linkDir = dirname(linkPath)
    if (!existsSync(linkDir)) mkdirSync(linkDir, { recursive: true })

    // ln -sf: atomic symlink replacement
    if (!await runner.exec(`ln -sf ${phpBin} ${linkPath}`)) return false

    this.localConfig.put('php', phpBin)
    return this.dev.updateEnvironment()
  }

  private formula(version: string): string {
    const f = PHP_VERSION_MAP[version]
    if (!f) throw new UserException(`Unknown PHP version '${version}'. Supported: ${Object.keys(PHP_VERSION_MAP).join(', ')}`)
    return f
  }

  private phpPath(version: string): string {
    const formula = this.formula(version)
    const cellarPath = this.dev.config.brewPath(`Cellar/${formula}`)
    if (!existsSync(cellarPath)) throw new UserException(`Valet: PHP ${version} is not installed in ${cellarPath}`)

    const glob = new Bun.Glob(`${version}.*/bin/php`)
    const matches = [...glob.scanSync({ cwd: cellarPath, absolute: true })]
    if (matches.length === 0) throw new UserException(`Valet: PHP ${version} is not installed in ${cellarPath}`)

    // Pick the latest patch version
    return matches.sort((a, b) => {
      const va = a.match(new RegExp(`${version}\\.\\d+`))?.[0] ?? ''
      const vb = b.match(new RegExp(`${version}\\.\\d+`))?.[0] ?? ''
      return vb.localeCompare(va, undefined, { numeric: true })
    })[0]!
  }
}
