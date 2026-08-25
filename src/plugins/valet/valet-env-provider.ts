import { join } from 'node:path'
import { realpathSync } from 'node:fs'
import type { EnvProvider } from '../../types/capability.js'
import type { Dev } from '../../dev.js'
import type { ValetPlugin } from './valet-plugin.js'
import { rawValetConfigSchema } from './valet-step-resolver.js'
import { valetProviderArgs } from './valet-provider-args.js'

export class ValetEnvProvider implements EnvProvider {
  private readonly dev: Dev
  private readonly plugin: ValetPlugin

  constructor(args: Record<string, unknown>) {
    const parsed = valetProviderArgs(args)
    this.dev = parsed.dev
    this.plugin = parsed.plugin
  }

  envs(): Record<string, string> {
    const steps = this.dev.config.raw_().steps ?? this.dev.config.raw_().up ?? []
    if (!steps.some(step => {
      if (!('valet' in step)) return false
      rawValetConfigSchema.parse(step['valet'])
      return true
    })) return {}
    if (!this.plugin.localConfig) return {}

    const env = this.plugin.localConfig
    const linkPath = env.get('php')
    const bin = env.get('bin')
    const valetPath = env.get('path')
    const iniScanDir = this.dev.config.devPath('php.d')
    const sitesPath = join(env.get('dir'), 'Nginx')

    // Resolve symlink so PHP_DIR points to the real Cellar installation,
    // not the .dev/bin symlink directory (needed for include paths etc.)
    let realPhpBin = linkPath
    try { realPhpBin = realpathSync(linkPath) } catch { /* not a symlink or missing */ }
    const phpDir = realPhpBin.replace('/bin/php', '')

    return {
      PHP_BIN: linkPath,
      PHP_DIR: phpDir,
      HERD_OR_VALET: bin,
      VALET_BIN: bin,
      VALET_PATH: valetPath,
      SITE_PATH: sitesPath,
      VALET_OR_HERD_SITE_PATH: sitesPath,
      PHP_INI_SCAN_DIR: iniScanDir,
      LOCAL_DEV_CA_CERT_PATH: join(valetPath, 'CA', 'LaravelValetCASelfSigned.pem'),
    }
  }
}
