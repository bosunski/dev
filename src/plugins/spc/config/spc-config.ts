import { createHash } from 'node:crypto'
import type { Config } from '../../../config/config.js'
import type { Step } from '../../../types/step.js'
import { SpcInstallStep } from '../steps/spc-install-step.js'
import { SpcInstallRequirementsStep } from '../steps/spc-install-requirements-step.js'
import { SpcCacheStep } from '../steps/spc-cache-step.js'
import { SpcDownloadStep } from '../steps/spc-download-step.js'
import { SpcBuildStep } from '../steps/spc-build-step.js'
import { SpcLinkStep } from '../steps/spc-link-step.js'

export type RawSpcConfig = {
  php: {
    version?: string
    preset?: string
    extensions?: string[]
    sources?: Record<string, string>
  }
  combine?: { input: string; output: string }
  'prefer-pre-built'?: boolean
}

const DEFAULT_EXTENSIONS = [
  'bcmath','calendar','ctype','curl','dba','dom','exif','session','filter',
  'fileinfo','iconv','mbstring','mbregex','openssl','pcntl','pdo','pdo_mysql',
  'pdo_sqlite','phar','posix','readline','simplexml','sockets','sqlite3',
  'tokenizer','xml','xmlreader','xmlwriter','zip','zlib','sodium',
]

export class SpcConfig {
  static readonly Name = 'spc'
  static readonly DefaultPhpVersion = '8.2'

  readonly phpVersion: string
  readonly extensions: string[]
  readonly sources: Record<string, string>
  readonly md5: string
  readonly preferPreBuilt: boolean

  constructor(
    private readonly config: RawSpcConfig,
    private readonly devConfig: Config,
  ) {
    this.phpVersion = config.php.version ?? SpcConfig.DefaultPhpVersion
    this.extensions = [
      ...(config.php.extensions ?? []),
      ...this.getPresetExtensions(config.php.preset ?? 'common'),
    ]
    this.sources = config.php.sources ?? {}
    this.md5 = this.computeMd5()
    this.preferPreBuilt = config['prefer-pre-built'] ?? true
  }

  private getPresetExtensions(preset: string): string[] {
    switch (preset) {
      case 'common': return DEFAULT_EXTENSIONS
      case 'minimal': return ['pcntl','posix','mbstring','tokenizer','phar']
      default: return []
    }
  }

  steps(): Step[] {
    return [
      new SpcInstallStep(),
      new SpcInstallRequirementsStep(this),
      new SpcCacheStep(this),
      new SpcDownloadStep(this),
      new SpcBuildStep(this),
      new SpcLinkStep(this),
    ]
  }

  private computeMd5(): string {
    let content = this.extensions.join(',')
    for (const [key, url] of Object.entries(this.sources)) {
      content += `::${key}:${url}`
    }
    return createHash('md5').update(content).digest('hex')
  }

  bin(): string { return this.devConfig.globalPath('bin/spc') }
  phpPath(p = ''): string { return this.devConfig.globalPath(`spc/${this.phpVersion}/${this.md5}/${p}`) }
  sfx(): string { return this.phpPath('buildroot/bin/micro.sfx') }
  cachePath(): string { return this.devConfig.globalPath(`spc/${this.phpVersion}/lock`) }

  buildCommand(rebuild = true): string[] {
    const cmd = [
      this.bin(), 'build', '--debug', '--no-strip', '--build-micro', '--build-cli', '--with-micro-fake-cli',
    ]
    if (rebuild) cmd.push('--rebuild')
    cmd.push(this.extensions.join(','))
    return cmd
  }

  combine(): { input: string; output: string } | null {
    return this.config.combine ?? null
  }
}
