import type { Runner } from '../../../execution/runner.js'
import { BaseStep } from '../../../step/base-step.js'
import { UserException } from '../../../exceptions.js'

export class BrewStep extends BaseStep {
  constructor(private readonly packages: string[]) {
    super()
  }

  name(): string {
    return `Install brew formulae: ${this.packages.join(', ')}`
  }

  private brewBinPath(): string {
    const platform = process.platform
    if (platform === 'darwin') return '/opt/homebrew/bin/brew'
    if (platform === 'linux') return '/home/linuxbrew/.linuxbrew/bin/brew'
    throw new UserException(`Unsupported OS: ${platform}`)
  }

  async run(runner: Runner): Promise<boolean> {
    return runner.exec([this.brewBinPath(), 'install', ...this.packages], undefined, {
      HOMEBREW_NO_AUTO_UPDATE: '1',
      HOMEBREW_NO_INSTALL_UPGRADE: '1',
    })
  }

  async done(runner: Runner): Promise<boolean> {
    const proc = Bun.spawnSync([this.brewBinPath(), 'list', '--formulae', '--versions'])
    if (proc.exitCode !== 0) return false

    const installed = new TextDecoder()
      .decode(proc.stdout)
      .split('\n')
      .filter(Boolean)
      .map(line => line.split(' ')[0]!)

    return this.packages.every(pkg => installed.includes(pkg))
  }

  id(): string {
    return `brew.packages.${this.packages.join('_')}`
  }
}
