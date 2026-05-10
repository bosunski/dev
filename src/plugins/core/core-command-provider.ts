import type { Command } from '@oclif/core'
import type { CommandProvider } from '../../types/capability.js'
import type { RawCommand } from '../../types/config.js'

export class CoreCommandProvider implements CommandProvider {
  getCommands(): Command.Class[] {
    // Commands are registered statically in commands/index.ts
    // CorePlugin's command classes (Clone, Cd, Up) are part of the main bundle
    return []
  }

  getConfigCommands(): Record<string, RawCommand> {
    return {}
  }
}
