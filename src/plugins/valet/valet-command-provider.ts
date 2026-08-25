import type { Command } from '@oclif/core'
import type { CommandProvider } from '../../types/capability.js'
import type { RawCommand } from '../../types/config.js'
import type { Dev } from '../../dev.js'
import { pluginDev } from '../../plugin/plugin-args.js'
import { rawValetConfigSchema } from './valet-step-resolver.js'

export class ValetCommandProvider implements CommandProvider {
  private readonly dev: Dev

  constructor(args: Record<string, unknown>) {
    this.dev = pluginDev(args)
  }

  getCommands(): Command.Class[] {
    return []
  }

  getConfigCommands(): Record<string, RawCommand> {
    const rawSteps = this.dev.config.raw_().steps ?? this.dev.config.raw_().up ?? []
    const hasValet = rawSteps.some(step => {
      if (!('valet' in step)) return false
      rawValetConfigSchema.parse(step['valet'])
      return true
    })
    if (!hasValet) return {}

    return {
      'valet:restart': {
        desc: 'Restart Valet services',
        run: ['$VALET_BIN', 'restart'],
      },
    }
  }
}
