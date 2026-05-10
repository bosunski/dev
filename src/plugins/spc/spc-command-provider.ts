import type { Command } from '@oclif/core'
import type { CommandProvider } from '../../types/capability.js'
import type { RawCommand } from '../../types/config.js'
import type { Dev } from '../../dev.js'
import { SpcConfig, type RawSpcConfig } from './config/spc-config.js'

export class SpcCommandProvider implements CommandProvider {
  private readonly dev: Dev

  constructor(args: Record<string, unknown>) {
    this.dev = args['dev'] as Dev
  }

  getCommands(): Command.Class[] {
    return []
  }

  getConfigCommands(): Record<string, RawCommand> {
    const rawSteps = this.dev.config.raw_().steps ?? this.dev.config.raw_().up ?? []
    const rawSpc = rawSteps
      .filter(s => s && typeof s === 'object' && 'spc' in s)
      .map(s => (s as Record<string, unknown>)['spc'])[0]

    if (!rawSpc || typeof rawSpc !== 'object') return {}

    const config = new SpcConfig(rawSpc as RawSpcConfig, this.dev.config)
    const commands: Record<string, RawCommand> = {
      'spc:rebuild': {
        desc: 'Rebuild PHP binaries',
        run: config.buildCommand(true),
      },
      'spc:clean': {
        desc: 'Remove built binaries and downloads',
        run: ['rm', '-rf', config.phpPath()],
      },
    }

    const combine = config.combine()
    if (combine) {
      commands['spc:combine'] = {
        desc: 'Combine micro.sfx and php code together',
        run: `${config.bin()} micro:combine -M ${config.sfx()} -O ${combine.output} ${combine.input} -I 'memory_limit=1G'`,
      }
    }

    return commands
  }
}
