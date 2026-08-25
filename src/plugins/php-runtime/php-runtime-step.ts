import { existsSync, lstatSync, mkdirSync, readFileSync, symlinkSync, unlinkSync, writeFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { z } from 'zod'
import type { Config } from '../../config/config.js'
import type { Runner } from '../../execution/runner.js'
import { UserException } from '../../exceptions.js'
import { BaseStep } from '../../step/base-step.js'
import { PackageManager } from '../core/config/package-manager.js'
import { PhpRuntime, type NativePhpRuntime, type ResolvedPhpRuntime, type SpcPhpRuntime } from './php-runtime.js'

const runtimeMetadataSchema = z.object({
  provider: z.enum(['native', 'spc']),
  version: z.string(),
  extensions: z.array(z.string()),
})

export class PhpRuntimeStep extends BaseStep {
  constructor(private readonly config: Config, private readonly runtimeName: string) { super() }

  name(): string { return `Prepare PHP runtime: ${this.runtimeName}` }
  id(): string { return `php-runtime-${this.runtimeName}` }

  async done(): Promise<boolean> {
    const runtime = PhpRuntime.resolve(this.config, this.runtimeName)
    const metadata = join(runtime.root, 'runtime.json')
    if (!existsSync(metadata) || !existsSync(runtime.fpmConfig)) return false
    try {
      const recorded = runtimeMetadataSchema.parse(JSON.parse(readFileSync(metadata, 'utf8')))
      return recorded.provider === runtime.provider
        && recorded.version === runtime.version
        && JSON.stringify(recorded.extensions) === JSON.stringify(runtime.extensions)
        && existsSync(runtime.phpBinary)
        && existsSync(runtime.fpmBinary)
        && existsSync(runtime.iniFile)
        && readFileSync(runtime.iniFile, 'utf8') === this.extensionsContent(runtime)
        && readFileSync(runtime.fpmConfig, 'utf8') === this.fpmContent(runtime)
        && this.extensionsAvailable(runtime, runtime.phpBinary)
    } catch {
      return false
    }
  }

  async run(runner: Runner): Promise<boolean> {
    const runtime = PhpRuntime.resolve(this.config, this.runtimeName)
    const prepared = runtime.provider === 'native'
      ? await this.prepareNative(runner, runtime)
      : await this.prepareSpc(runner, runtime)
    if (!prepared || !this.extensionsAvailable(runtime, runtime.phpBinary)) return false

    mkdirSync(runtime.iniDir, { recursive: true })
    writeFileSync(runtime.iniFile, this.extensionsContent(runtime))
    mkdirSync(dirname(runtime.fpmConfig), { recursive: true })
    writeFileSync(runtime.fpmConfig, this.fpmContent(runtime))
    writeFileSync(join(runtime.root, 'runtime.json'), JSON.stringify({
      provider: runtime.provider,
      version: runtime.version,
      extensions: runtime.extensions,
    }, null, 2) + '\n')
    return true
  }

  private async prepareNative(runner: Runner, runtime: NativePhpRuntime): Promise<boolean> {
    const manager = PackageManager.detect()
    const missing = runtime.packages.filter(packageName =>
      Bun.spawnSync(manager.checkCommand(packageName), { stdout: 'ignore', stderr: 'ignore' }).exitCode !== 0
    )
    if (missing.length > 0 && !await runner.exec(manager.installCommand(missing))) return false
    const php = this.binary(runtime.sourcePhpBinary)
    const fpm = this.binary(runtime.sourceFpmBinary)
    if (!php || !fpm) return false
    mkdirSync(runtime.binDir, { recursive: true })
    this.replaceLink(runtime.phpBinary, php)
    this.replaceLink(runtime.fpmBinary, fpm)
    return true
  }

  private async prepareSpc(runner: Runner, runtime: SpcPhpRuntime): Promise<boolean> {
    if (existsSync(runtime.phpBinary) && existsSync(runtime.fpmBinary)
      && this.extensionsAvailable(runtime, runtime.phpBinary)) return true
    const direct = runner.withoutShadowEnv()
    const environment = runtime.target ? { SPC_TARGET: runtime.target } : {}
    if (!existsSync(runtime.spcBinary) && !await this.installSpc(direct, runtime)) return false
    if (!await this.installSpcRequirements(direct)) return false
    mkdirSync(runtime.root, { recursive: true })
    this.prepareSpcCaches(runtime)
    if (!await direct.exec([runtime.spcBinary, 'doctor', '--auto-fix', '--no-interaction'], runtime.root, environment)) return false
    if (!await direct.exec([runtime.spcBinary, 'doctor', '--no-interaction'], runtime.root, environment)) return false
    const extensions = runtime.extensions.join(',')
    if (!await direct.exec([
      runtime.spcBinary, 'download', `--for-extensions=${extensions}`, `--with-php=${runtime.version}`,
    ], runtime.root, environment)) return false
    return direct.exec([runtime.spcBinary, 'build', extensions, '--build-cli', '--build-fpm'], runtime.root, environment)
  }

  private prepareSpcCaches(runtime: SpcPhpRuntime): void {
    mkdirSync(runtime.downloadCache, { recursive: true })
    const downloads = join(runtime.root, 'downloads')
    if (!existsSync(downloads)) symlinkSync(runtime.downloadCache, downloads, 'dir')
    mkdirSync(runtime.toolchainCache, { recursive: true })
    const toolchain = join(runtime.root, 'pkgroot')
    if (!existsSync(toolchain)) symlinkSync(runtime.toolchainCache, toolchain, 'dir')
  }

  private async installSpcRequirements(runner: Runner): Promise<boolean> {
    const manager = PackageManager.detect()
    const packages = manager.resolve(['cmake', 're2c'])
    const missing = packages.filter(packageName =>
      Bun.spawnSync(manager.checkCommand(packageName), { stdout: 'ignore', stderr: 'ignore' }).exitCode !== 0
    )
    return missing.length === 0 || runner.exec(manager.installCommand(missing))
  }

  private async installSpc(runner: Runner, runtime: SpcPhpRuntime): Promise<boolean> {
    const platform = process.platform === 'linux' ? 'linux' : process.platform === 'darwin' ? 'macos' : null
    const architecture = process.arch === 'x64' ? 'x86_64' : process.arch === 'arm64' ? 'aarch64' : null
    if (!platform || !architecture) throw new UserException(`SPC does not support ${process.platform}/${process.arch}.`)
    const binDir = dirname(runtime.spcBinary)
    const archive = join(binDir, `spc-${platform}-${architecture}.tar.gz`)
    const url = `https://github.com/crazywhalecc/static-php-cli/releases/latest/download/spc-${platform}-${architecture}.tar.gz`
    mkdirSync(binDir, { recursive: true })
    if (!await runner.exec(['curl', '-fL', '-o', archive, url])) return false
    if (!await runner.exec(['tar', '-xzf', archive, '-C', binDir])) return false
    return runner.exec(['chmod', '0755', runtime.spcBinary])
  }

  private binary(candidate: string): string | null {
    return candidate.startsWith('/') ? (existsSync(candidate) ? candidate : null) : Bun.which(candidate)
  }

  private replaceLink(path: string, target: string): void {
    try { lstatSync(path); unlinkSync(path) } catch {}
    symlinkSync(target, path)
  }

  private fpmContent(runtime: ResolvedPhpRuntime): string {
    return `[global]\ndaemonize = no\nerror_log = syslog\n\n[${runtime.name}]\n`
      + `listen = ${runtime.socketPath}\nlisten.mode = 0600\npm = ondemand\npm.max_children = 8\n`
      + `pm.process_idle_timeout = 10s\nclear_env = no\ncatch_workers_output = yes\n`
  }

  private extensionsContent(runtime: ResolvedPhpRuntime): string {
    if (runtime.provider === 'spc') return ''
    return runtime.extensions.filter(extension => !this.extensionLoaded(runtime.phpBinary, extension))
      .map(extension => `extension=${extension}\n`).join('')
  }

  private extensionLoaded(php: string, extension: string, iniDir?: string): boolean {
    const expression = extension === 'mbregex'
      ? "function_exists('mb_split')"
      : `extension_loaded('${extension}')`
    return Bun.spawnSync([php, '-r', `exit(${expression} ? 0 : 1);`], {
      env: iniDir ? { ...process.env, PHP_INI_SCAN_DIR: iniDir } : process.env,
      stdout: 'ignore', stderr: 'ignore',
    }).exitCode === 0
  }

  private extensionsAvailable(runtime: ResolvedPhpRuntime, php: string): boolean {
    return runtime.extensions.every(extension => this.extensionLoaded(php, extension, runtime.iniDir))
  }
}
