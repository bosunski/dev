import type { Command } from '@oclif/core'
import type { CommandProvider } from '../../types/capability.js'
import type { RawCommand } from '../../types/config.js'
import type { Dev } from '../../dev.js'

export class CaddyCommandProvider implements CommandProvider {
  private readonly dev: Dev

  constructor(args: Record<string, unknown>) {
    this.dev = args['dev'] as Dev
  }

  getCommands(): Command.Class[] {
    return []
  }

  getConfigCommands(): Record<string, RawCommand> {
    const rawSteps = this.dev.config.raw_().steps ?? this.dev.config.raw_().up ?? []
    const hasCaddy = rawSteps.some(s => s && typeof s === 'object' && 'caddy' in s)
    if (!hasCaddy) return {}

    return {
      'caddy:restart': {
        desc: 'Restart Caddy',
        run: 'caddy stop; caddy start',
      },
    }
  }
}
