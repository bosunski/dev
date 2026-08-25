import { existsSync, readFileSync } from 'node:fs'
import { join } from 'node:path'
import type { Config } from '../../../config/config.js'
import { UserException } from '../../../exceptions.js'
import { z } from 'zod'

export type LocalValetConfigData = {
  dir: string
  bin: string
  version: string
  path: string
  tld: string
  php: string
}

const localValetConfigSchema = z.object({
  dir: z.string().optional(),
  bin: z.string().optional(),
  version: z.string().optional(),
  path: z.string().optional(),
  tld: z.string().optional(),
  domain: z.string().optional(),
  php: z.string().optional(),
})

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
      const data = localValetConfigSchema.parse(JSON.parse(readFileSync(configPath, 'utf8')))
      const config: Partial<LocalValetConfigData> = {}
      if (data.dir !== undefined) config.dir = data.dir
      if (data.bin !== undefined) config.bin = data.bin
      if (data.version !== undefined) config.version = data.version
      if (data.path !== undefined) config.path = data.path
      if (data.php !== undefined) config.php = data.php
      if (data.domain !== undefined) config.tld = data.domain
      else if (data.tld !== undefined) config.tld = data.tld
      return config
    } catch {
      return {}
    }
  }
}
