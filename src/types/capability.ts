import type { Command } from '@oclif/core'
import type { Step, StepResolver } from './step.js'
import type { RawCommand } from './config.js'

export const COMMAND_PROVIDER = 'CommandProvider'
export const CONFIG_PROVIDER = 'ConfigProvider'
export const ENV_PROVIDER = 'EnvProvider'
export const PATH_PROVIDER = 'PathProvider'

export type CapabilityKey =
  | typeof COMMAND_PROVIDER
  | typeof CONFIG_PROVIDER
  | typeof ENV_PROVIDER
  | typeof PATH_PROVIDER

export interface Capability {}

export interface CommandProvider extends Capability {
  getCommands(): Command.Class[]
  getConfigCommands(): Record<string, RawCommand>
}

export interface ConfigProvider extends Capability {
  steps(): Step[]
  validate(): boolean
  stepResolvers(): Record<string, StepResolver>
}

export interface EnvProvider extends Capability {
  envs(): Record<string, string>
}

export interface PathProvider extends Capability {
  paths(): string[]
}
