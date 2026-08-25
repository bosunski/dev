import type { Command } from '@oclif/core'
import type { CommandProvider } from '../../types/capability.js'
import type { RawCommand } from '../../types/config.js'

export class CaddyCommandProvider implements CommandProvider {
  getCommands(): Command.Class[] {
    return []
  }

  getConfigCommands(): Record<string, RawCommand> {
    return {}
  }
}
