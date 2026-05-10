import type { Runner } from '../../../../execution/runner.js'
import { BaseStep } from '../../../../step/base-step.js'

export class EnsureDockerStep extends BaseStep {
  private orbStackInstalled = false
  private orbStackRunning = false
  private usingOrbStackContext = false
  private hasCorrectOrbStackVersion = false

  id(): string { return 'mysql-ensure-docker' }
  name(): string { return 'Ensure Docker is setup for MySQL' }

  async run(runner: Runner): Promise<boolean> {
    if (!this.orbStackInstalled) {
      const install = await runner.getIO().text('Using database feature depends on OrbStack, do you want to install it? (y/n)')
      if (install.toLowerCase() !== 'y') return false
      const installed = await runner.exec('brew install orbstack', undefined, { HOMEBREW_NO_AUTO_UPDATE: '1' })
      if (!installed) return false
      this.orbStackInstalled = true
    }

    if (!this.hasCorrectOrbStackVersion) {
      const upgrade = await runner.getIO().text('OrbStack needs to be upgraded to version 1.5.1, do you want to proceed? (y/n)')
      if (upgrade.toLowerCase() !== 'y') return false
      const upgraded = await runner.exec('brew upgrade --greedy orbstack', undefined, { HOMEBREW_NO_AUTO_UPDATE: '1' })
      if (!upgraded) return false
    }

    if (!this.orbStackRunning) {
      if (!await runner.exec('orbctl start')) return false
    }

    if (!this.usingOrbStackContext) {
      if (!await runner.exec('docker context use orbstack')) return false
    }

    return this.isOrbStackPowered(runner)
  }

  async done(runner: Runner): Promise<boolean> {
    if (runner.hasCommand('docker')) return true
    if (this.isOrbStackPowered(runner)) {
      this.orbStackInstalled = this.orbStackRunning = this.usingOrbStackContext = this.hasCorrectOrbStackVersion = true
      return true
    }

    const versionProc = Bun.spawnSync(['orbctl', 'version'])
    this.orbStackInstalled = versionProc.exitCode === 0
    if (!this.orbStackInstalled) return false

    const versionOutput = new TextDecoder().decode(versionProc.stdout)
    const versionMatch = versionOutput.match(/^Version: (\d+\.\d+\.\d+)/m)
    const version = versionMatch?.[1] ?? '0.0.0'
    const parts = version.split('.').map(Number)
    const minParts = [1, 5, 1]
    this.hasCorrectOrbStackVersion = parts.every((p, i) => p >= (minParts[i] ?? 0))
    if (!this.hasCorrectOrbStackVersion) return false

    const statusProc = Bun.spawnSync(['orbctl', 'status'])
    this.orbStackRunning = new TextDecoder().decode(statusProc.stdout).trim() === 'Running'
    return this.orbStackRunning
  }

  private isOrbStackPowered(runner: Runner): boolean {
    const proc = Bun.spawnSync(['docker', 'info', '--format=json'])
    if (proc.exitCode !== 0) return false
    try {
      const info = JSON.parse(new TextDecoder().decode(proc.stdout)) as Record<string, unknown>
      const clientInfo = info['ClientInfo'] as Record<string, unknown> | undefined
      return Boolean(info['ID']) && clientInfo?.['Context'] === 'orbstack'
    } catch {
      return false
    }
  }
}
