import { existsSync, readFileSync } from 'node:fs'
import { join } from 'node:path'
import type { Config } from '../../../config/config.js'
import { UserException } from '../../../exceptions.js'

export type LocalValetConfigData = {
  dir: string
  bin: string
  version: string
  path: string
  tld: string
  php: string
}

export class LocalValetConfig {
  private config: LocalValetConfigData

  constructor(devConfig: Config) {
    const valetDir = this.resolveValetDir(devConfig)
    this.config = {
      dir: valetDir,
      bin: join(devConfig.globalPath(), '../.config/composer/vendor/bin/valet'),
      version: '4.0.0',
      path: valetDir,
      tld: 'test',
      php: devConfig.path('bin/php'),
    }

    Object.assign(this.config, this.json(valetDir))
  }

  private resolveValetDir(config: Config): string {
    if (config.isDarwin()) return join(process.env['HOME'] ?? '', '.config/valet')
    if (config.isLinux()) return join(process.env['HOME'] ?? '', '.valet')
    throw new UserException(`Valet is not supported on this platform: ${config.platform()}`)
  }

  get<K extends keyof LocalValetConfigData>(key: K): LocalValetConfigData[K] {
    return this.config[key]
  }

  put<K extends keyof LocalValetConfigData>(key: K, value: LocalValetConfigData[K]): void {
    this.config[key] = value
  }

  private json(valetDir: string): Partial<LocalValetConfigData> {
    const configPath = join(valetDir, 'config.json')
    if (!existsSync(configPath)) return {}
    try {
      const data = JSON.parse(readFileSync(configPath, 'utf8')) as Record<string, unknown>
      if (data['domain']) {
        data['tld'] = data['domain']
        delete data['domain']
      }
      return data as Partial<LocalValetConfigData>
    } catch {
      return {}
    }
  }
}
