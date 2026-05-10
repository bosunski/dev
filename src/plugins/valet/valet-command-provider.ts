import type { Command } from '@oclif/core'
import type { CommandProvider } from '../../types/capability.js'
import type { RawCommand } from '../../types/config.js'
import type { Dev } from '../../dev.js'

export class ValetCommandProvider implements CommandProvider {
  private readonly dev: Dev

  constructor(args: Record<string, unknown>) {
    this.dev = args['dev'] as Dev
  }

  getCommands(): Command.Class[] {
    return []
  }

  getConfigCommands(): Record<string, RawCommand> {
    const rawSteps = this.dev.config.raw_().steps ?? this.dev.config.raw_().up ?? []
    const hasValet = rawSteps.some(s => s && typeof s === 'object' && 'valet' in s)
    if (!hasValet) return {}

    return {
      'valet:restart': {
        desc: 'Restart Valet services',
        run: ['$VALET_BIN', 'restart'],
      },
    }
  }
}
