import type { Config } from './config.js'
import type { RawCommand } from '../types/config.js'

export function commandWorkingDirectory(config: Config, command: RawCommand): string {
  return command.cwd ? config.cwd(command.cwd) : config.cwd()
}
