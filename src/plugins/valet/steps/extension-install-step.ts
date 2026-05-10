import { existsSync, writeFileSync, mkdirSync, realpathSync, readFileSync } from 'node:fs'
import { join, dirname } from 'node:path'
import type { Runner } from '../../../execution/runner.js'
import { BaseStep } from '../../../step/base-step.js'
import type { Config } from '../../../config/config.js'
import { UserException } from '../../../exceptions.js'

export type RawExtensionConfig = {
  before?: string
  options?: Record<string, string>
}

export type RawExtensionsMap = Record<string, RawExtensionConfig | null>

export class ExtensionInstallStep extends BaseStep {
  readonly extName: string
  // Name without version suffix: swoole-6.0.2 → swoole
  private readonly extBaseName: string
  private readonly before: string | null
  private readonly options: Record<string, string>

  constructor(
    extName: string,
    before: string | null,
    options: Record<string, string>,
    private readonly devConfig: Config,
  ) {
    super()
    this.extName = extName
    this.extBaseName = extName.includes('-') ? extName.split('-')[0]! : extName
    this.before = before
    this.options = options
  }

  static fromMap(name: string, cfg: RawExtensionConfig | null, devConfig: Config): ExtensionInstallStep {
    return new ExtensionInstallStep(name, cfg?.before ?? null, cfg?.options ?? {}, devConfig)
  }

  name(): string { return `Install and Link PHP extension: ${this.extName}` }
  id(): string { return `${this.devConfig.cwd()}.php.extension.${this.extName}` }

  async done(_runner: Runner): Promise<boolean> {
    // ini file written = run() completed; verify the .so it points to still exists
    const iniPath = this.iniPath()
    if (!existsSync(iniPath)) return false
    return this.soExistsForIni(iniPath) || this.soExists()
  }

  async run(runner: Runner): Promise<boolean> {
    this.ensureIniDir()

    // If the .so is already compiled, skip pecl and just write the ini
    if (this.soExists()) {
      return this.enableExtension()
    }

    if (this.before) {
      if (!await runner.exec(this.before)) {
        throw new UserException(`Failed to run before command: ${this.before}`)
      }
    }

    const phpBinLink = this.devConfig.path('bin/php')
    let realPhpBin: string
    try {
      realPhpBin = realpathSync(phpBinLink)
    } catch {
      throw new UserException(`Linked PHP binary ${phpBinLink} not found`)
    }

    const peclBin = join(dirname(realPhpBin), 'pecl')

    // PEAR's global option parser consumes -D before the install subcommand sees it,
    // so -D configure options are silently dropped. The reliable way is to pipe answers
    // via stdin in the same order as configure_options in package.xml.
    // We download the package first to read package.xml option order, then pipe answers.
    const stdinAnswers = await this.buildStdinAnswers(peclBin)
    const answersStr = stdinAnswers.map(v => v.replace(/'/g, "'\\''")).join('\\n')
    // PHP_INI_SCAN_DIR='' prevents "already loaded" errors from .dev/php.d/ ini files
    const cmd = `printf '${answersStr}\\n' | PHP_INI_SCAN_DIR='' ${peclBin} install ${this.extName}`
    if (!await runner.exec(cmd)) {
      return false
    }

    return this.enableExtension()
  }

  private soExists(): boolean {
    try { return existsSync(this.extensionPath()) } catch { return false }
  }

  private extensionPath(): string {
    const phpBin = this.devConfig.path('bin/php')
    const result = Bun.spawnSync(
      // ini_get('extension_dir') returns the runtime value set by php.ini (where pecl installs);
      // PHP_EXTENSION_DIR is the compiled-in default which may differ from the actual pecl ext_dir
      [phpBin, '-r', "echo ini_get('extension_dir');"],
      // PHP_INI_SCAN_DIR='' suppresses scan dirs but still loads main php.ini (so extension_dir is correct)
      { stdout: 'pipe', stderr: 'pipe', env: { ...process.env as Record<string, string>, PHP_INI_SCAN_DIR: '' } },
    )
    if (result.exitCode !== 0) throw new UserException('Failed to get PHP extension directory')
    const extDir = new TextDecoder().decode(result.stdout).trim()
    return join(extDir, `${this.extBaseName}.so`)
  }

  private soExistsForIni(iniPath: string): boolean {
    try {
      const content = readFileSync(iniPath, 'utf8')
      const match = content.match(/^extension=(.+)$/m)
      if (!match?.[1]) return existsSync(this.extensionPath())
      return existsSync(match[1].trim())
    } catch {
      return false
    }
  }

  private writeIni(soPath: string): boolean {
    try {
      writeFileSync(this.iniPath(), `extension=${soPath}\n`)
      return true
    } catch {
      return false
    }
  }

  private enableExtension(): boolean {
    let soPath: string
    try {
      soPath = this.extensionPath()
    } catch {
      return false
    }
    return this.writeIni(soPath)
  }

  private ensureIniDir(): void {
    const iniDir = this.devConfig.devPath('php.d')
    if (!existsSync(iniDir)) mkdirSync(iniDir, { recursive: true })
  }

  private iniPath(): string {
    return join(this.devConfig.devPath('php.d'), `${this.extBaseName}.ini`)
  }

  private resolveOptionValue(value: string): string {
    return value.replace(/`([^`]*)`/g, (_, cmd: string) => {
      const res = Bun.spawnSync(['sh', '-c', cmd], { stdout: 'pipe', stderr: 'pipe' })
      return res.exitCode === 0 ? new TextDecoder().decode(res.stdout).trim() : ''
    })
  }

  private async buildStdinAnswers(peclBin: string): Promise<string[]> {
    // Download the package to a temp dir and read package.xml to get configure option order
    const tmpDir = `/tmp/pecl-inspect-${this.extName}`
    const downloadResult = Bun.spawnSync(
      ['sh', '-c', `mkdir -p ${tmpDir} && PHP_INI_SCAN_DIR='' ${peclBin} download ${this.extName} && mv *.tgz ${tmpDir}/ 2>/dev/null; true`],
      { stdout: 'pipe', stderr: 'pipe' },
    )
    void downloadResult

    const glob = new Bun.Glob('*.tgz')
    const tarballs = [...glob.scanSync({ cwd: tmpDir, absolute: true })]
    if (tarballs.length === 0) {
      // Can't determine order — just send one newline per known option as fallback
      return Object.values(this.options).map(v => this.resolveOptionValue(v))
    }

    // Extract package.xml from tarball
    const tarball = tarballs[0]!
    Bun.spawnSync(['tar', 'xzf', tarball, '-C', tmpDir, 'package.xml'], { stdout: 'pipe', stderr: 'pipe' })

    const packageXmlPath = `${tmpDir}/package.xml`
    if (!existsSync(packageXmlPath)) {
      return Object.values(this.options).map(v => this.resolveOptionValue(v))
    }

    // Parse configure option names in order from package.xml
    const xml = readFileSync(packageXmlPath, 'utf8')
    const optionOrder: string[] = []
    const defaultValues: Record<string, string> = {}
    for (const match of xml.matchAll(/configureoption[^>]+name="([^"]+)"(?:[^>]+default="([^"]*)")?/g)) {
      optionOrder.push(match[1]!)
      if (match[2] !== undefined) defaultValues[match[1]!] = match[2]
    }

    // Build answers in package.xml order
    return optionOrder.map(name => {
      const raw = this.options[name]
      if (raw !== undefined) return this.resolveOptionValue(raw)
      return defaultValues[name] ?? ''
    })
  }
}
