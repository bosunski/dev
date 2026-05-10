import type { ConfigProvider } from '../../types/capability.js'
import type { Step, StepResolver } from '../../types/step.js'
import type { Dev } from '../../dev.js'
import { EnsureShadowEnvStep } from './steps/shadowenv/ensure-shadowenv-step.js'
import { ShadowEnvStep } from './steps/shadowenv/shadowenv-step.js'
import { EnvSubstituteStep } from './steps/env-substitute-step.js'
import { PromptEnvStep } from './steps/prompt-env-step.js'
import { ScriptResolver } from './resolvers/script-resolver.js'
import { CommandResolver } from './resolvers/command-resolver.js'
import { MySqlResolver } from './resolvers/mysql-resolver.js'

export class CoreConfigProvider implements ConfigProvider {
  private readonly dev: Dev

  constructor(args: Record<string, unknown>) {
    this.dev = args['dev'] as Dev
  }

  steps(): Step[] {
    return [
      new PromptEnvStep(this.dev.config),
      new EnsureShadowEnvStep(),
      new ShadowEnvStep(this.dev),
      new EnvSubstituteStep(this.dev.config),
    ]
  }

  validate(): boolean {
    return true
  }

  stepResolvers(): Record<string, StepResolver> {
    const scriptResolver = new ScriptResolver()
    return {
      script: scriptResolver,
      custom: scriptResolver,
      command: new CommandResolver(this.dev.config.commands()),
      mysql: new MySqlResolver(this.dev),
    }
  }
}
