import { existsSync, appendFileSync, chmodSync, mkdirSync, renameSync } from 'node:fs'
import type { Runner } from '../../../../execution/runner.js'
import { BaseStep } from '../../../../step/base-step.js'
import { UserException } from '../../../../exceptions.js'

const SHADOWENV_VERSION = '3.5.1'

export function shadowenvAsset(platform = process.platform, arch = process.arch): string {
  const targetPlatform = platform === 'darwin' ? 'apple-darwin' : platform === 'linux' ? 'unknown-linux-gnu' : null
  const targetArch = arch === 'arm64' ? 'aarch64' : arch === 'x64' ? 'x86_64' : null
  if (!targetPlatform || !targetArch) {
    throw new UserException(`Shadowenv does not provide a binary for ${platform}/${arch}.`)
  }
  return `shadowenv-${targetArch}-${targetPlatform}`
}

export class EnsureShadowEnvStep extends BaseStep {
  private installed = false
  private hookInstalled = false

  name(): string {
    return 'Ensure ShadowEnv is Set Up'
  }

  async run(runner: Runner): Promise<boolean> {
    if (!this.installed) {
      const target = runner.config.globalBinPath('shadowenv')
      const temporary = `${target}.download`
      const asset = shadowenvAsset()
      const url = `https://github.com/Shopify/shadowenv/releases/download/${SHADOWENV_VERSION}/${asset}`
      const response = await fetch(url)
      if (!response.ok) return false
      mkdirSync(runner.config.globalBinPath(), { recursive: true })
      await Bun.write(temporary, response)
      chmodSync(temporary, 0o755)
      renameSync(temporary, target)
      this.installed = true
    }

    if (this.hookInstalled) return true

    const shell = runner.shell(null)
    if (!shell) {
      throw new UserException('Unable to determine the current shell. Make sure you are using one of the supported shells: bash, zsh, fish.')
    }

    if (!existsSync(shell.profile)) {
      throw new UserException(`Unable to find the profile file: ${shell.profile}. Please setup Shadowenv manually.`)
    }

    const evalLine = this.evalConfig(shell.name, runner.shadowenvBin())
    try {
      appendFileSync(shell.profile, evalLine)
    } catch {
      throw new UserException(`Unable to update the profile file: ${shell.profile}. Please setup Shadowenv manually.`)
    }

    return this.done(runner)
  }

  private evalConfig(shell: string, binary: string): string {
    if (shell === 'fish') return `\n# Shadow Env\n${binary} init fish | source\n`
    return `\n# Shadow Env\neval "$(${binary} init ${shell})"\n`
  }

  async done(runner: Runner): Promise<boolean> {
    const [hookInstalled, binaryInstalled] = runner.checkShadowEnv(true)
    if (hookInstalled) {
      this.hookInstalled = true
      return true
    }

    this.installed = binaryInstalled
    return false
  }

  id(): string {
    return 'shadowenv.setup'
  }
}
