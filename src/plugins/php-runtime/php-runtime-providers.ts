import type { ConfigProvider, EnvProvider, PathProvider, ServeProvider } from '../../types/capability.js'
import type { Step, StepResolver } from '../../types/step.js'
import type { Dev } from '../../dev.js'
import { PhpRuntime } from './php-runtime.js'
import { PhpRuntimeStep } from './php-runtime-step.js'
import { pluginDev } from '../../plugin/plugin-args.js'

abstract class PhpProviderBase {
  protected readonly dev: Dev
  constructor(args: Record<string, unknown>) { this.dev = pluginDev(args) }
}

export class PhpRuntimeConfigProvider extends PhpProviderBase implements ConfigProvider {
  steps(): Step[] {
    return PhpRuntime.definitions(this.dev.config).map(([name]) => new PhpRuntimeStep(this.dev.config, name))
  }
  validate(): boolean { return true }
  stepResolvers(): Record<string, StepResolver> { return {} }
}

export class PhpRuntimeEnvProvider extends PhpProviderBase implements EnvProvider {
  envs(): Record<string, string> {
    const primary = PhpRuntime.primary(this.dev.config)
    if (!primary) return {}
    const runtime = PhpRuntime.resolve(this.dev.config, primary[0])
    return {
      PHP_BIN: runtime.phpBinary,
      DEV_PHP_VERSION: runtime.version,
      PHP_INI_SCAN_DIR: runtime.iniDir,
    }
  }
}

export class PhpRuntimePathProvider extends PhpProviderBase implements PathProvider {
  paths(): string[] {
    return PhpRuntime.definitions(this.dev.config)
      .map(([name]) => PhpRuntime.resolve(this.dev.config, name).binDir)
  }
}

export class PhpRuntimeServeProvider extends PhpProviderBase implements ServeProvider {
  processes(): Record<string, { run: string }> {
    return Object.fromEntries(PhpRuntime.definitions(this.dev.config).map(([name]) => {
      const runtime = PhpRuntime.resolve(this.dev.config, name)
      return [`php:${name}`, { run: `"${runtime.binDir}/php-fpm" --nodaemonize --force-stderr --fpm-config "${runtime.fpmConfig}"` }]
    }))
  }
}
