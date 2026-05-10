import type { Step } from '../../../types/step.js'
import type { Runner } from '../../../execution/runner.js'
import { createHash } from 'node:crypto'
import { SiteStep, type RawSite } from './site-step.js'
import { LinkPhpStep } from './link-php-step.js'
import { ExtensionInstallStep, type RawExtensionsMap } from './extension-install-step.js'
import type { LocalValetConfig } from '../config/local-valet-config.js'
import type { Dev } from '../../../dev.js'

type RawPhpObject = {
  version?: string
  extensions?: RawExtensionsMap
}

type RawValetConfig = {
  php?: string | RawPhpObject
  sites?: RawSite[]
}

export class ValetStep implements Step {
  private readonly subSteps: Step[]
  private readonly _id: string

  constructor(config: RawValetConfig, valetBin: string, localConfig?: LocalValetConfig, dev?: Dev) {
    this.subSteps = []
    let phpVersion: string | null = null

    if (typeof config.php === 'string') {
      // Plain string php version — link it
      phpVersion = config.php
      if (localConfig && dev) {
        this.subSteps.push(new LinkPhpStep(phpVersion, localConfig, dev))
      }
    } else if (config.php && typeof config.php === 'object') {
      const phpObj = config.php as RawPhpObject
      phpVersion = phpObj.version ?? null
      if (phpVersion && localConfig && dev) {
        this.subSteps.push(new LinkPhpStep(phpVersion, localConfig, dev))
      }
      for (const [extName, extCfg] of Object.entries(phpObj.extensions ?? {})) {
        if (dev) {
          this.subSteps.push(ExtensionInstallStep.fromMap(extName, extCfg, dev.config))
        }
      }
    }

    for (const site of config.sites ?? []) {
      // Pass localConfig so SiteStep resolves the bin lazily after PrepareValetStep runs
      this.subSteps.push(new SiteStep(site, localConfig ?? valetBin, phpVersion))
    }

    this._id = `valet-${createHash('md5').update(JSON.stringify(config)).digest('hex')}`
  }

  name(): string | null {
    return null
  }

  id(): string {
    return this._id
  }

  async done(_runner: Runner): Promise<boolean> {
    return false
  }

  async run(runner: Runner): Promise<boolean> {
    return runner.execute(this.subSteps)
  }
}
