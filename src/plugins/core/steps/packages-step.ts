import { createHash } from 'node:crypto'
import type { Runner } from '../../../execution/runner.js'
import { BaseStep } from '../../../step/base-step.js'
import type { PackageManager, RawPackage } from '../config/package-manager.js'

export class PackagesStep extends BaseStep {
  private readonly packages: string[]

  constructor(private readonly manager: PackageManager, packages: RawPackage[]) {
    super()
    this.packages = manager.resolve(packages)
  }

  name(): string {
    return `Install ${this.manager.name} packages: ${this.packages.join(', ')}`
  }

  async run(runner: Runner): Promise<boolean> {
    return runner.exec(this.manager.installCommand(this.packages))
  }

  async done(runner: Runner): Promise<boolean> {
    for (const pkg of this.packages) {
      if (!await runner.withoutShadowEnv().exec(this.manager.checkCommand(pkg))) return false
    }
    return true
  }

  id(): string {
    const value = `${this.manager.name}:${this.packages.join(',')}`
    return `packages.${createHash('md5').update(value).digest('hex')}`
  }
}
