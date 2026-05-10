import type { Runner } from '../../../execution/runner.js'
import { BaseStep } from '../../../step/base-step.js'

type RawPackage = Record<string, string> | string

export class PackagesStep extends BaseStep {
  constructor(private readonly packages: RawPackage[]) {
    super()
  }

  id(): string { return `composer.packages.${this.formatPackages('_')}` }
  name(): string { return `Install global composer packages: ${this.formatPackages(', ')}` }

  private formatPackages(glue = ' '): string {
    return this.packages
      .map(pkg => {
        if (typeof pkg === 'string') return pkg
        const [name, version] = Object.entries(pkg)[0]!
        return `${name}:'${version}'`
      })
      .join(glue)
  }

  async run(runner: Runner): Promise<boolean> {
    return runner.exec(`composer global require ${this.formatPackages()}`)
  }

  async done(_runner: Runner): Promise<boolean> { return false }
}
