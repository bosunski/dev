import { join } from 'node:path'
import { existsSync, realpathSync } from 'node:fs'
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
    const sitesPath = join(env.get('dir'), 'Nginx')
    const result: Record<string, string> = {
      HERD_OR_VALET: bin,
      VALET_BIN: bin,
      VALET_PATH: valetPath,
      SITE_PATH: sitesPath,
      VALET_OR_HERD_SITE_PATH: sitesPath,
      LOCAL_DEV_CA_CERT_PATH: join(valetPath, 'CA', 'LaravelValetCASelfSigned.pem'),
    }

    // Do not hide PHP's system extension directory while Valet is still being installed.
    if (!existsSync(linkPath)) return result

    // Resolve symlink so PHP_DIR points to the real Cellar installation,
    // not the .dev/bin symlink directory (needed for include paths etc.)
    let realPhpBin = linkPath
    try { realPhpBin = realpathSync(linkPath) } catch { /* not a symlink */ }
    result['PHP_BIN'] = linkPath
    result['PHP_DIR'] = realPhpBin.replace('/bin/php', '')
    result['PHP_INI_SCAN_DIR'] = this.dev.config.devPath('php.d')
    return result
  }
}
